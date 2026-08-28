<?php

namespace Kct\Images;

/**
 * Úklid metadat v obrázcích.
 *
 * Obrázky z fotoaparátů a z grafických programů si s sebou nesou EXIF, XMP,
 * IPTC a barevné profily. Pro web jsou k ničemu a u některých souborů tvoří
 * skoro celý objem — v knihovně KČT je logo, kde 551 kB z 557 kB připadá na
 * tiskový CMYK profil „U.S. Web Coated (SWOP) v2“; vlastní obrázek 300×53 px
 * má 6 kB. WordPress metadata z originálu kopíruje i do všech odvozených
 * velikostí (WP_Image_Editor_Imagick::strip_meta() barevný profil záměrně
 * zachovává, aby nepokazil barvy), takže se ta zátěž znásobí.
 *
 * Třída dělá jen bezpečnou část práce, a to bez jakékoli ztráty kvality:
 * přepisuje se struktura souboru, obrazová data se nedekódují ani znovu
 * nekomprimují. Soubory, u kterých by odstranění profilu posunulo barvy
 * (Display P3, DCI-P3, Apple Wide Color, CMYK), se vědomě přeskakují a jen
 * hlásí — jejich převod do sRGB potřebuje cílový profil a dekódování obrazu,
 * což je jiná operace s jinou mírou rizika.
 *
 * Nic nemaže na disku a nesahá do databáze; pracuje výhradně nad obsahem
 * jednoho souboru a vrací, co by se stalo (viz clean(), $write).
 */
class MetadataCleaner {

	/**
	 * Popisy profilů, po jejichž odstranění zůstanou barvy beze změny.
	 *
	 * sRGB je barevný prostor, který prohlížeč předpokládá i bez profilu, takže
	 * jeho vypsání do souboru nic nepřidává. Porovnává se malými písmeny a bez
	 * mezer, protože se v knihovně vyskytují varianty jako „sRGB IEC61966-2.1“
	 * i holé „sRGB“.
	 */
	private const SAFE_PROFILES = array(
		'srgbiec61966-2.1',
		'srgb',
		'srgbbuiltin',
		'sRGBIEC6196621',
	);

	/** JPEG markery, které se zahazují: APP1 (EXIF/XMP), APP2 (ICC), APP13 (Photoshop/IPTC), COM. */
	private const DROP_MARKERS = array( 0xE1, 0xE2, 0xED, 0xFE );

	/**
	 * Vyčistí jeden soubor.
	 *
	 * @param string $path  Cesta k souboru.
	 * @param bool   $write Zapsat výsledek na disk. false = jen spočítat, co by úklid udělal.
	 *
	 * @return array{status: string, before: int, after: int, note: string}
	 *               status: 'cleaned' | 'skipped' | 'unchanged' | 'error'
	 */
	public function clean( string $path, bool $write = false ): array {
		$before = is_readable( $path ) ? (int) filesize( $path ) : 0;

		if ( ! $before ) {
			return $this->result( 'error', 0, 0, 'soubor nelze číst' );
		}

		$data = file_get_contents( $path );

		if ( false === $data ) {
			return $this->result( 'error', $before, $before, 'soubor nelze číst' );
		}

		// Formát podle obsahu, ne podle přípony. V knihovně leží 325 map
		// z importu akcí (uploads/maps/map_*.jpg), které jsou ve skutečnosti
		// PNG — podle přípony by se přeskočily jako poškozené.
		if ( str_starts_with( $data, "\xFF\xD8" ) ) {
			$cleaned = $this->clean_jpeg( $data );
		} elseif ( str_starts_with( $data, "\x89PNG\r\n\x1A\n" ) ) {
			$cleaned = $this->clean_png( $data );
		} else {
			return $this->result( 'skipped', $before, $before, 'není JPEG ani PNG' );
		}

		if ( is_string( $cleaned['reason'] ) ) {
			return $this->result( 'skipped', $before, $before, $cleaned['reason'] );
		}

		$after = strlen( $cleaned['data'] );

		// Rovnost nebo nárůst znamená, že nebylo co zahodit. Zápis by jen
		// zbytečně změnil čas souboru a vyhodil ho z cache.
		if ( $after >= $before ) {
			return $this->result( 'unchanged', $before, $before, '' );
		}

		// Pojistka před zápisem: výsledek se musí dát načíst jako obrázek
		// a mít stejné rozměry i typ jako originál. Chyba v přepisu struktury
		// by se tím projevila tady, ne až v prohlížeči návštěvníka.
		$was = getimagesizefromstring( $data );
		$is  = getimagesizefromstring( $cleaned['data'] );

		if ( ! $is || ! $was || $is[0] !== $was[0] || $is[1] !== $was[1] || $is[2] !== $was[2] ) {
			return $this->result( 'skipped', $before, $before, 'kontrola po úklidu neprošla' );
		}

		if ( $write && false === file_put_contents( $path, $cleaned['data'] ) ) {
			return $this->result( 'error', $before, $before, 'zápis selhal' );
		}

		return $this->result( 'cleaned', $before, $after, '' );
	}

	/**
	 * Přepíše JPEG bez nepotřebných segmentů.
	 *
	 * Prochází se jen hlavička po markerech a kopírují se bajty tak, jak jsou.
	 * Od SOS (0xDA) dál leží komprimovaná obrazová data, ta se berou vcelku —
	 * proto je operace bezztrátová.
	 *
	 * Co se schválně nechává:
	 *   APP0 (JFIF)  — hustota bodů, některé prohlížeče na ni spoléhají;
	 *   APP14 (Adobe) — nese příznak barevné transformace. Bez něj se YCCK
	 *                   a CMYK JPEG dekóduje s invertovanými barvami.
	 *
	 * @return array{data: string, reason: string|null}
	 */
	private function clean_jpeg( string $d ): array {
		if ( substr( $d, 0, 2 ) !== "\xFF\xD8" ) {
			return array( 'data' => $d, 'reason' => 'není JPEG' );
		}

		$icc = $this->jpeg_icc( $d );

		if ( null !== $icc && ! $this->is_safe_profile( $icc ) ) {
			return array( 'data' => $d, 'reason' => sprintf( 'profil „%s“ — nutný převod do sRGB', $icc ) );
		}

		$out = "\xFF\xD8";
		$i   = 2;
		$len = strlen( $d );

		while ( $i + 1 < $len ) {
			if ( "\xFF" !== $d[ $i ] ) {
				return array( 'data' => $d, 'reason' => 'poškozená struktura souboru' );
			}

			$marker = ord( $d[ $i + 1 ] );

			// Začátek obrazových dat — zbytek souboru jde beze změny.
			if ( 0xDA === $marker ) {
				$out .= substr( $d, $i );
				break;
			}

			// Markery bez délkového pole.
			if ( 0xD8 === $marker || 0xD9 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) || 0x01 === $marker ) {
				$out .= substr( $d, $i, 2 );
				$i   += 2;
				continue;
			}

			if ( $i + 4 > $len ) {
				return array( 'data' => $d, 'reason' => 'poškozená struktura souboru' );
			}

			$size = unpack( 'n', substr( $d, $i + 2, 2 ) )[1];

			if ( $size < 2 || $i + 2 + $size > $len ) {
				return array( 'data' => $d, 'reason' => 'poškozená struktura souboru' );
			}

			if ( ! in_array( $marker, self::DROP_MARKERS, true ) ) {
				$out .= substr( $d, $i, 2 + $size );
			}

			$i += 2 + $size;
		}

		return array( 'data' => $out, 'reason' => null );
	}

	/**
	 * Přepíše PNG bez nepotřebných chunků.
	 *
	 * Zahazuje se iCCP (barevný profil), eXIf a textové chunky. Obrazová data
	 * (IDAT) i vše ostatní se kopírují beze změny, takže ani tady nevzniká
	 * ztráta. Kontrolní součty se nepřepočítávají — každý chunk si nese vlastní
	 * CRC a ten se kopíruje s ním.
	 *
	 * @return array{data: string, reason: string|null}
	 */
	private function clean_png( string $d ): array {
		if ( substr( $d, 0, 8 ) !== "\x89PNG\r\n\x1A\n" ) {
			return array( 'data' => $d, 'reason' => 'není PNG' );
		}

		$drop = array( 'iCCP', 'eXIf', 'tEXt', 'iTXt', 'zTXt', 'tIME' );
		$out  = substr( $d, 0, 8 );
		$i    = 8;
		$len  = strlen( $d );

		while ( $i + 8 <= $len ) {
			$size = unpack( 'N', substr( $d, $i, 4 ) )[1];
			$type = substr( $d, $i + 4, 4 );

			if ( $i + 12 + $size > $len ) {
				return array( 'data' => $d, 'reason' => 'poškozená struktura souboru' );
			}

			// PNG s profilem se přeskočí ze stejného důvodu jako u JPEGu —
			// popis profilu je uvnitř zkomprimovaného chunku, takže se tu
			// nedá levně přečíst a rozhodnout o bezpečnosti.
			if ( 'iCCP' === $type ) {
				$name = strstr( substr( $d, $i + 8, min( $size, 80 ) ), "\0", true );

				if ( ! is_string( $name ) || ! $this->is_safe_profile( $name ) ) {
					return array( 'data' => $d, 'reason' => sprintf( 'profil „%s“ — nutný převod do sRGB', is_string( $name ) ? $name : '?' ) );
				}
			}

			if ( ! in_array( $type, $drop, true ) ) {
				$out .= substr( $d, $i, 12 + $size );
			}

			$i += 12 + $size;

			if ( 'IEND' === $type ) {
				break;
			}
		}

		return array( 'data' => $out, 'reason' => null );
	}

	/**
	 * Popis barevného profilu v JPEGu, nebo null když žádný nemá.
	 *
	 * Profil může být rozdělený do víc APP2 segmentů, proto se skládají
	 * dohromady. Popis leží v tagu 'desc', ve dvou možných podobách podle
	 * verze ICC: starší 'desc' (ASCII) a novější 'mluc' (UTF-16BE).
	 */
	private function jpeg_icc( string $d ): ?string {
		$icc = '';
		$i   = 2;
		$len = strlen( $d );

		while ( $i + 4 <= $len && "\xFF" === $d[ $i ] ) {
			$marker = ord( $d[ $i + 1 ] );

			if ( 0xDA === $marker ) {
				break;
			}

			if ( 0xD8 === $marker || 0xD9 === $marker || ( $marker >= 0xD0 && $marker <= 0xD7 ) || 0x01 === $marker ) {
				$i += 2;
				continue;
			}

			$size = unpack( 'n', substr( $d, $i + 2, 2 ) )[1];

			if ( $size < 2 || $i + 2 + $size > $len ) {
				break;
			}

			$segment = substr( $d, $i + 4, $size - 2 );

			if ( 0xE2 === $marker && str_starts_with( $segment, "ICC_PROFILE\0" ) ) {
				$icc .= substr( $segment, 14 );
			}

			$i += 2 + $size;
		}

		if ( strlen( $icc ) < 132 ) {
			return null;
		}

		$count = unpack( 'N', substr( $icc, 128, 4 ) )[1];

		for ( $k = 0; $k < $count; $k++ ) {
			$entry = substr( $icc, 132 + $k * 12, 12 );

			if ( strlen( $entry ) < 12 ) {
				break;
			}

			$tag = unpack( 'a4sig/Noffset/Nsize', $entry );

			if ( 'desc' !== $tag['sig'] ) {
				continue;
			}

			$blob = substr( $icc, $tag['offset'], $tag['size'] );

			if ( str_starts_with( $blob, 'mluc' ) && strlen( $blob ) >= 28 ) {
				$head = unpack( 'Nlen/Noffset', substr( $blob, 20, 8 ) );
				$text = substr( $blob, $head['offset'], $head['len'] );

				return trim( (string) mb_convert_encoding( $text, 'UTF-8', 'UTF-16BE' ) );
			}

			if ( str_starts_with( $blob, 'desc' ) && strlen( $blob ) >= 12 ) {
				$size = unpack( 'N', substr( $blob, 8, 4 ) )[1];

				return trim( rtrim( substr( $blob, 12, $size ), "\0" ) );
			}
		}

		return '?';
	}

	/** Lze profil s tímhle popisem zahodit, aniž se změní barvy? */
	private function is_safe_profile( string $desc ): bool {
		$key = strtolower( (string) preg_replace( '/\s+/', '', $desc ) );

		return in_array( $key, array_map( 'strtolower', self::SAFE_PROFILES ), true );
	}

	/** @return array{status: string, before: int, after: int, note: string} */
	private function result( string $status, int $before, int $after, string $note ): array {
		return array(
			'status' => $status,
			'before' => $before,
			'after'  => $after,
			'note'   => $note,
		);
	}
}
