<?php

namespace Kct\Features;

/**
 * Hledání kontaktů v textu a jejich kódování — čistá logika, bez WordPressu.
 *
 * Samostatně proto, že právě tahle část se může mýlit: regulární výraz nad
 * cizím textem je heuristika a potřebuje testy (`tests/contact-guard-test.php`).
 * Soubor se dá vyžádat bez autoloaderu a bez databáze, takže test je obyčejný
 * PHP skript.
 *
 * Escapování sem nepatří — to umí jen vrstva, která zná WordPress. Scanner
 * dostává vykreslovací funkce zvenčí a sám HTML nesestavuje.
 *
 * @see ContactGuard
 */
final class ContactScanner {
	/**
	 * E-mail. Bez pokusu o plnou RFC — ta připouští tvary, které se v obsahu
	 * webu nevyskytují, a každý další povolený znak je jen víc příležitostí
	 * spolknout kus okolního textu.
	 */
	private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9](?:[A-Za-z0-9.\-]*[A-Za-z0-9])?\.[A-Za-z]{2,}/u';

	/**
	 * Telefon: devět číslic, volitelně s předvolbou, buď v jednom kuse, nebo
	 * členěné po trojicích.
	 *
	 * Úzké je to záměrně. V témže odstavci bývá IČO, PSČ, rok, částka i GPS,
	 * takže „nějaká čísla s mezerami" by sebralo půl textu. Osmimístné IČO
	 * ani pětimístné PSČ se do devíti číslic nevejde; `(?<!\d)` a `(?!\d)`
	 * brání tomu, aby se devítka vykrojila z delšího čísla.
	 *
	 * Lomítko za číslem vylučuje číslo účtu (`123456789/0800`), které jinak
	 * devět číslic splňuje.
	 */
	private const PHONE = '/(?<![\d])(?:(?:\+|00)420[\s\x{00A0}]?)?(?:\d{3}(?:[\s\x{00A0}]|&nbsp;)?\d{3}(?:[\s\x{00A0}]|&nbsp;)?\d{3})(?![\d])(?![\s\x{00A0}]*\/\s*\d)/u';

	/**
	 * Označení, po kterých číslo není telefon.
	 *
	 * Hledá se v textu krátce před nálezem. Devět číslic totiž může být i
	 * číslo účtu nebo variabilní symbol a tam ochrana nejen nepomáhá, ale
	 * rozbíjí údaj, který má být čitelný.
	 */
	private const NOT_PHONE_LABELS = array(
		'ičo',
		'ič',
		'ico',
		'dič',
		'dic',
		'účet',
		'ucet',
		'účtu',
		'uctu',
		'č. ú',
		'č.ú',
		'c. u',
		'c.u',
		'symbol',
		'bank',
	);

	/** Kolik znaků před nálezem se prohledá na označení z NOT_PHONE_LABELS. */
	private const LABEL_LOOKBEHIND = 24;

	/** Prvky, jejichž obsah se neprohledává. */
	private const SKIP_ELEMENTS = array( 'script', 'style', 'pre', 'code', 'textarea', 'svg' );

	/**
	 * Zakóduje hodnotu do hexu.
	 *
	 * XOR jedním bajtem, klíč je první bajt výstupu. Není to šifra — klíč leží
	 * v téže stránce a rozebere ho skript v prohlížeči. Jde o to, aby ve zdroji
	 * nezbyl žádný *vzorek*: pevná záměna (`[zav]`, `(at)`) se dá hledat
	 * jedním výrazem, tohle ne.
	 *
	 * Hodnota se před XORem procentuálně zakóduje, takže do hexu jde vždy
	 * jen ASCII a skript ji v prohlížeči složí zpět i s diakritikou, aniž by
	 * musel skládat UTF-8 po bajtech.
	 *
	 * @param string   $value Hodnota k zakódování.
	 * @param int|null $key   Klíč 1–255; jen pro testy, jinak náhodný.
	 */
	public static function encode( string $value, ?int $key = null ): string {
		$key = $key ?? random_int( 1, 255 );

		$payload = rawurlencode( $value );
		$out     = sprintf( '%02x', $key );

		$length = strlen( $payload );
		for ( $i = 0; $i < $length; $i ++ ) {
			$out .= sprintf( '%02x', ord( $payload[ $i ] ) ^ $key );
		}

		return $out;
	}

	/**
	 * Rozebere, co zakódoval encode(). Je tu pro testy — v provozu to dělá
	 * skript v prohlížeči.
	 */
	public static function decode( string $hex ): string {
		if ( strlen( $hex ) < 2 || strlen( $hex ) % 2 !== 0 || ! ctype_xdigit( $hex ) ) {
			return '';
		}

		$key     = (int) hexdec( substr( $hex, 0, 2 ) );
		$payload = '';

		for ( $i = 2; $i < strlen( $hex ); $i += 2 ) {
			$payload .= chr( (int) hexdec( substr( $hex, $i, 2 ) ) ^ $key );
		}

		return rawurldecode( $payload );
	}

	/**
	 * Rozdělí e-mail na části pro čitelnou zálohu.
	 *
	 * Záloha v `<noscript>` je ve zdroji HTML pro každého, i pro sběrače —
	 * `<noscript>` nic neschovává, jen přepíná, kdo to vidí vykreslené. Kdyby
	 * tam adresa stála v jednom kuse, byla by ochrana zbytečná; kdyby tam
	 * stálo „zavinac", stačí na ni jedno pravidlo, stejně jako na `[zav]`.
	 *
	 * Vrací se proto části, které vrstva nad tím slepí prázdnými komentáři.
	 * Člověk uvidí správnou adresu (komentář se nevykresluje), ale v surovém
	 * zdroji není `@` vedle domény, takže obecný výraz na e-mail nic nenajde.
	 *
	 * @return string[] Části; `@` znamená zavináč, mezera oddělovač.
	 */
	public static function split_email( string $email ): array {
		$at = strrpos( $email, '@' );

		if ( false === $at ) {
			return array( $email );
		}

		return array( substr( $email, 0, $at ), '@', substr( $email, $at + 1 ) );
	}

	/**
	 * Rozdělí telefon na části pro čitelnou zálohu, po trojicích od konce.
	 *
	 * Totéž co u e-mailu, jen vzorkem je tady devět číslic za sebou. Trojice
	 * jsou zvyk, kterým se česká čísla píšou, takže se čte stejně jako dřív.
	 *
	 * @return string[] Části; mezera znamená oddělovač.
	 */
	public static function split_phone( string $phone ): array {
		$prefix = '';
		$digits = $phone;

		if ( preg_match( '/^((?:\+|00)420)[\s\x{00A0}]*(.*)$/u', $phone, $m ) ) {
			$prefix = $m[1];
			$digits = $m[2];
		}

		$digits = preg_replace( '/[^\d]/', '', $digits ) ?? '';

		if ( '' === $digits ) {
			return array( $phone );
		}

		$groups = str_split( strrev( $digits ), 3 );
		$groups = array_reverse( array_map( 'strrev', $groups ) );

		if ( '' !== $prefix ) {
			array_unshift( $groups, $prefix );
		}

		$parts = array();
		foreach ( $groups as $i => $group ) {
			if ( $i > 0 ) {
				$parts[] = ' ';
			}
			$parts[] = $group;
		}

		return $parts;
	}

	/**
	 * Projde HTML a textové nálezy nahradí tím, co vrátí vykreslovací funkce.
	 *
	 * Pracuje jen nad textem mezi značkami — do atributů nesahá, takže se
	 * nerozbije `href`, `src` ani `alt`. Obsah prvků ze SKIP_ELEMENTS a prvků,
	 * které už jsou chráněné, přeskakuje; druhé proto, aby druhý průchod
	 * (filtr nad obsahem i nad blokem téhož obsahu) nekódoval už zakódované.
	 *
	 * Odkazy `mailto:` a `tel:` se vyměňují celé ještě před průchodem textem —
	 * jinak by po nich zůstala adresa v `href`.
	 *
	 * @param string   $html     Vstupní HTML.
	 * @param callable $email_cb fn(string $email): string
	 * @param callable $phone_cb fn(string $phone): string
	 */
	public static function protect( string $html, callable $email_cb, callable $phone_cb ): string {
		if ( '' === trim( $html ) ) {
			return $html;
		}

		// Filtr běží nad každým blokem každé stránky, a kontakt je na většině
		// z nich žádný. Tenhle test je o řád levnější než rozebrání HTML.
		if ( ! str_contains( $html, '@' ) && ! preg_match( '/\d{3}/', $html ) ) {
			return $html;
		}

		// Odkazy `mailto:`/`tel:` se vyměňují první, ale jen za zástupky. Kdyby
		// se sem hned dosadil hotový výstup, prošel by ještě průchodem textem —
		// a jestli se v něm něco najde podruhé, by záviselo na tom, co
		// vykreslovací funkce vrátila. Tahle vrstva o tom nemá co vědět.
		$placeholders = array();
		$html         = self::protect_links( $html, $email_cb, $phone_cb, $placeholders );

		// Komentář je vlastní druh úseku, ne značka. Bez něj by `<[^>]*>` sahal
		// od `<!--` k prvnímu `>`, který náhodou potká — tedy kamkoli, podle
		// toho, co v komentáři stojí.
		//
		// Obsah komentáře se **nechává být**. Vidět není, takže uživateli nic
		// nechybí, a zapsat do něj chráněný prvek nelze: čitelná záloha slepuje
		// části prázdným komentářem a jeho `-->` by komentář ukončilo dřív —
		// zbytek zakomentovaného textu by se tím vysypal na stránku. Kontakt
		// v komentáři je zbytek po redakci a patří smazat v obsahu, ne obejít
		// v kódu.
		$parts = preg_split( '/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $parts ) ) {
			return $html;
		}

		$out       = '';
		$skip_tag  = '';
		$skip_deep = 0;

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( '<' === $part[0] ) {
				$out .= $part;

				$tag = self::tag_name( $part );

				if ( '' === $tag ) {
					continue;
				}

				if ( '' !== $skip_tag ) {
					// Uvnitř přeskakovaného prvku se počítá jen hloubka téhož
					// jména, aby ho ukončila až jeho vlastní koncová značka.
					if ( $tag === $skip_tag ) {
						$skip_deep += self::is_closing( $part ) ? -1 : 1;

						if ( $skip_deep <= 0 ) {
							$skip_tag  = '';
							$skip_deep = 0;
						}
					}

					continue;
				}

				if ( self::is_closing( $part ) || self::is_self_closing( $part ) ) {
					continue;
				}

				if ( in_array( $tag, self::SKIP_ELEMENTS, true ) || self::is_protected_tag( $part ) ) {
					$skip_tag  = $tag;
					$skip_deep = 1;
				}

				continue;
			}

			$out .= ( '' === $skip_tag ) ? self::protect_text( $part, $email_cb, $phone_cb ) : $part;
		}

		return $placeholders ? strtr( $out, $placeholders ) : $out;
	}

	/**
	 * Vymění celé odkazy `mailto:` a `tel:`.
	 *
	 * Text odkazu se zahodí a nahradí adresou z `href`. Odkaz se totiž často
	 * jmenuje jinak než adresa („napište nám"), ale chráněný zápis má vést na
	 * tu adresu, na kterou vedl původní odkaz — ne na to, co se zobrazovalo.
	 */
	private static function protect_links( string $html, callable $email_cb, callable $phone_cb, array &$placeholders ): string {
		$result = preg_replace_callback(
			'#<a\b[^>]*\bhref\s*=\s*([\'"])\s*mailto:([^\'"?]+)[^\'"]*\1[^>]*>.*?</a\s*>#isu',
			static function ( array $m ) use ( $email_cb, &$placeholders ) {
				$email = trim( html_entity_decode( rawurldecode( $m[2] ), ENT_QUOTES, 'UTF-8' ) );

				if ( ! preg_match( self::EMAIL, $email ) ) {
					return $m[0];
				}

				return self::placeholder( (string) $email_cb( $email ), $placeholders );
			},
			$html
		);

		$html = is_string( $result ) ? $result : $html;

		$result = preg_replace_callback(
			'#<a\b[^>]*\bhref\s*=\s*([\'"])\s*tel:([^\'"?]+)[^\'"]*\1[^>]*>.*?</a\s*>#isu',
			static function ( array $m ) use ( $phone_cb, &$placeholders ) {
				$phone = trim( html_entity_decode( rawurldecode( $m[2] ), ENT_QUOTES, 'UTF-8' ) );

				if ( '' === $phone ) {
					return $m[0];
				}

				return self::placeholder( (string) $phone_cb( $phone ), $placeholders );
			},
			$html
		);

		return is_string( $result ) ? $result : $html;
	}

	/**
	 * Odloží hotový výstup a vrátí zástupku, která průchodem textem projde.
	 *
	 * Zástupka stojí mezi nulovými bajty — ty se v HTML stránky nevyskytují,
	 * takže si ji nejde s obsahem splést.
	 */
	private static function placeholder( string $rendered, array &$placeholders ): string {
		$key                  = "\x00kct-contact-" . count( $placeholders ) . "\x00";
		$placeholders[ $key ] = $rendered;

		return $key;
	}

	/**
	 * Nahradí nálezy v jednom textovém úseku.
	 *
	 * Nálezy se nejdřív posbírají s pozicemi ({@see find()}) a až pak se text
	 * skládá znovu. Postupná záměna by jinak hledala i ve vlastním výstupu —
	 * a ten je plný číslic.
	 */
	private static function protect_text( string $text, callable $email_cb, callable $phone_cb ): string {
		$found = self::find( $text );

		if ( ! $found ) {
			return $text;
		}

		$out    = '';
		$cursor = 0;

		foreach ( $found as $item ) {
			if ( $item['start'] < $cursor ) {
				continue;
			}

			$render = 'email' === $item['kind'] ? $email_cb : $phone_cb;

			$out   .= substr( $text, $cursor, $item['start'] - $cursor );
			$out   .= (string) $render( $item['value'] );
			$cursor = $item['start'] + $item['length'];
		}

		return $out . substr( $text, $cursor );
	}

	/**
	 * Je v textu kontakt?
	 *
	 * Pro výstupy, do kterých chráněný prvek vložit nejde a kontakt v nich
	 * tedy nemá co dělat — třeba popisek stránky pro vyhledávače, který je
	 * čistý text bez odkazů.
	 */
	public static function has_contact( string $text ): bool {
		return array() !== self::find( $text );
	}

	/**
	 * Nálezy v textu, seřazené podle pozice.
	 *
	 * Vrací `start`, `length`, `value` a `kind` (`email`/`phone`) — bez
	 * vykreslení, aby stejné hledání posloužilo i pouhé otázce, jestli v textu
	 * kontakt je.
	 *
	 * E-maily mají přednost: adresa jako `kct123456789@…` obsahuje devět
	 * číslic a bez přednosti by se rozpadla na telefon uvnitř e-mailu.
	 *
	 * @return array<int, array{start: int, length: int, value: string, kind: string}>
	 */
	private static function find( string $text ): array {
		if ( ! str_contains( $text, '@' ) && ! preg_match( '/\d{3}/', $text ) ) {
			return array();
		}

		$found = array();

		if ( preg_match_all( self::EMAIL, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$found[] = array(
					'start'  => $match[1],
					'length' => strlen( $match[0] ),
					'value'  => $match[0],
					'kind'   => 'email',
				);
			}
		}

		if ( preg_match_all( self::PHONE, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				if ( self::overlaps( $found, $match[1], strlen( $match[0] ) ) ) {
					continue;
				}

				if ( self::labelled_as_other_number( $text, $match[1] ) ) {
					continue;
				}

				$found[] = array(
					'start'  => $match[1],
					'length' => strlen( $match[0] ),
					'value'  => $match[0],
					'kind'   => 'phone',
				);
			}
		}

		usort( $found, static fn( array $a, array $b ) => $a['start'] <=> $b['start'] );

		return $found;
	}

	/** Překrývá se nález s některým už nalezeným? */
	private static function overlaps( array $found, int $start, int $length ): bool {
		foreach ( $found as $item ) {
			if ( $start < $item['start'] + $item['length'] && $item['start'] < $start + $length ) {
				return true;
			}
		}

		return false;
	}

	/** Stojí krátce před číslem označení, které z něj dělá jiný údaj než telefon? */
	private static function labelled_as_other_number( string $text, int $start ): bool {
		$from   = max( 0, $start - self::LABEL_LOOKBEHIND );
		$before = mb_strtolower( substr( $text, $from, $start - $from ), 'UTF-8' );

		foreach ( self::NOT_PHONE_LABELS as $label ) {
			if ( str_contains( $before, $label ) ) {
				return true;
			}
		}

		return false;
	}

	/** Jméno značky malými písmeny, nebo prázdno (komentář, doctype). */
	private static function tag_name( string $tag ): string {
		return preg_match( '#^<\s*/?\s*([a-zA-Z][a-zA-Z0-9-]*)#', $tag, $m ) ? strtolower( $m[1] ) : '';
	}

	private static function is_closing( string $tag ): bool {
		return (bool) preg_match( '#^<\s*/#', $tag );
	}

	private static function is_self_closing( string $tag ): bool {
		return (bool) preg_match( '#/\s*>$#', $tag );
	}

	/** Je prvek už chráněný, takže se do něj nemá sahat podruhé? */
	private static function is_protected_tag( string $tag ): bool {
		return str_contains( $tag, 'data-kct-email' )
			|| str_contains( $tag, 'data-kct-tel' )
			|| str_contains( $tag, 'kct-contact' );
	}
}
