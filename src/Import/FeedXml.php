<?php

namespace Kct\Import;

/**
 * Čtení exportu z centrální Databáze akcí KČT a rozhodnutí, které uložené
 * akce už export nenese.
 *
 * Záměrně bez jediného volání WordPressu — tím je to testovatelné bez
 * databáze i bez sítě (tests/feed-import-test.php). Volající (Features\Events)
 * se stará o stažení, ukládání a hlášení.
 */
class FeedXml {

	/**
	 * Doplní `&amp;` tam, kde v exportu stojí samotné `&`.
	 *
	 * Export z ústředí není vždy validní XML: stačí jeden pořadatel, který se
	 * jmenuje „Zadov Sport & Fun s.r.o“, a `simplexml_load_string()` vrátí
	 * false — s ním padne celý import, ne jen ta jedna akce. Opravit si to při
	 * čtení je levnější než čekat, než se to v centrální databázi přepíše.
	 *
	 * Escapuje se jen `&`, které nezačíná platnou entitu (`&amp;`, `&#38;`,
	 * `&#x26;`). Už správně zapsané entity tedy zůstanou, nezdvojí se.
	 *
	 * @param int|null $count Počet opravených znaků, pro hlášení.
	 */
	public static function escape_stray_ampersands( string $xml, ?int &$count = null ): string {
		$count   = 0;
		$pattern = '/&(?!(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z_:][A-Za-z0-9._:-]*);)/';
		$out     = preg_replace( $pattern, '&amp;', $xml, -1, $count );

		// preg_replace vrací null, když narazí na svůj limit (u megabajtového
		// exportu to není nemyslitelné). Pak je lepší nechat vstup být a nechat
		// chybu vyplavat z parseru než vrátit prázdný řetězec.
		if ( null === $out ) {
			$count = 0;

			return $xml;
		}

		return $out;
	}

	/**
	 * Přečte stažený export do pole.
	 *
	 * Postup je stejný, jaký měl import od začátku (převod na UTF-8, SimpleXML,
	 * kolečko přes JSON na obyčejné pole) — přibylo jen escapování `&` a to, že
	 * se chyba parseru dá přečíst místo tichého `false`.
	 *
	 * @return array{data: ?array, repaired: int, error: string}
	 */
	public static function read( string $raw ): array {
		$xml = mb_convert_encoding( $raw, 'UTF-8' );
		$xml = self::escape_stray_ampersands( $xml, $repaired );

		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();

		$sx     = simplexml_load_string( $xml );
		$errors = libxml_get_errors();

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $sx ) {
			$first = $errors[0] ?? null;

			return array(
				'data'     => null,
				'repaired' => $repaired,
				'error'    => $first
					? sprintf( 'řádek %d: %s', $first->line, trim( $first->message ) )
					: 'neznámá chyba parseru',
			);
		}

		$data = json_decode( json_encode( $sx ), true );

		return array(
			'data'     => is_array( $data ) ? $data : null,
			'repaired' => $repaired,
			'error'    => is_array( $data ) ? '' : 'XML se nepodařilo převést na pole',
		);
	}

	/**
	 * Nejzazší datum startu, které export nese.
	 *
	 * Slouží jako strop pro mazání: co leží za ním, o tom export nic neříká,
	 * a tak se toho nesaháme. Kdyby ústředí někdy exportovalo jen pevný výhled
	 * dopředu, bez tohohle stropu by se akce za jeho hranicí smazaly jako
	 * neexistující.
	 *
	 * @param array $events Prvky `event` z přečteného exportu.
	 */
	public static function horizon( array $events ): string {
		$max = '';

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$date = (string) ( $event['start']['date'] ?? '' );

			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $date > $max ) {
				$max = $date;
			}
		}

		return $max;
	}

	/**
	 * Uložené akce, které plný export už nenese a mají se smazat.
	 *
	 * Maže se **jen dopředu**. Co se stalo, zůstává: archiv proběhlých akcí je
	 * jediné místo, kde ta data ještě jsou, a export je po čase přestane nést
	 * sám od sebe — mazat podle něj minulost by archiv vyprázdnilo.
	 *
	 * Prázdný `$imported_ids` mazání zastaví úplně. Znamená to, že se z exportu
	 * nenaimportovalo nic (změněný filtr, oříznutý soubor), a v takové chvíli
	 * by „co v exportu není" znamenalo celou tabulku.
	 *
	 * @param array<int,string> $rows         Uložené akce jako db_id => datum.
	 * @param int[]             $imported_ids db_id akcí, které tenhle běh uložil.
	 * @param string            $today        Dnešek ve tvaru Y-m-d.
	 * @param string            $horizon      Nejzazší datum v exportu, viz horizon().
	 *
	 * @return int[] db_id ke smazání.
	 */
	public static function stale_ids( array $rows, array $imported_ids, string $today, string $horizon ): array {
		if ( ! $imported_ids || ! $horizon ) {
			return array();
		}

		$keep  = array_flip( array_map( 'intval', $imported_ids ) );
		$stale = array();

		foreach ( $rows as $db_id => $date ) {
			$db_id = (int) $db_id;
			$date  = (string) $date;

			// Prázdné i neúplné datum spadne pod dnešek, takže se nemaže.
			if ( $date < $today || $date > $horizon ) {
				continue;
			}

			if ( isset( $keep[ $db_id ] ) ) {
				continue;
			}

			$stale[] = $db_id;
		}

		return $stale;
	}
}
