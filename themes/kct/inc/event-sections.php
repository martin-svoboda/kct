<?php
/**
 * Čistá logika sekcí detailu akce — výběr sousedů, ročníků a doporučených akcí
 * ze seznamu, který vrací Events::get_events().
 *
 * Záměrně bez jediného volání WordPressu: tím je to testovatelné bez databáze
 * (tests/event-sections-test.php) a šablony pak jen vykreslují, nerozhodují.
 *
 * Všechny funkce počítají s tím, že seznam je seřazený podle data vzestupně —
 * tak ho get_events() vrací. Na tom pořadí stojí kct_event_neighbours() i
 * pořadí výsledku kct_event_related().
 *
 * @package kct
 */

if ( ! function_exists( 'kct_event_normalize_title' ) ) :
	/**
	 * Název akce pro porovnávání: malá písmena, sjednocené mezery.
	 *
	 * Žádné volnější porovnávání (bez diakritiky, podobnost) — názvy se mezi
	 * ročníky nemění a volné párování by chybně slepilo různé akce.
	 */
	function kct_event_normalize_title( string $title ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $title ) ) );
	}
endif;

if ( ! function_exists( 'kct_event_series_key' ) ) :
	/**
	 * Klíč série, tedy „tatáž akce v jiných letech“.
	 *
	 * Název se bere z databázového záznamu, když existuje: sloučená položka
	 * spárované akce má v `title` název příspěvku, který se od databázového
	 * může lišit (akce 1766 „105 let Posázavské stezky…“ vs. databázové
	 * „105. výročí Posázavské stezky“). Podle názvu příspěvku by se série
	 * rozpadla.
	 *
	 * Odbor je v klíči proto, že stejný název nesou i různé akce —
	 * `Tříkrálový pochod` je Slaný (102045) i Čelákovice (102104).
	 * U oblastních i čistě vlastních akcí je 0.
	 */
	function kct_event_series_key( array $event ): string {
		$title = ! empty( $event['db_title'] ) ? $event['db_title'] : ( $event['title'] ?? '' );

		return kct_event_normalize_title( (string) $title ) . '|' . (int) ( $event['db_department'] ?? 0 );
	}
endif;

if ( ! function_exists( 'kct_event_is_same' ) ) :
	/**
	 * Jsou to dvě položky téže akce?
	 *
	 * Porovnává se zvlášť `post_id` a `db_id`, nikdy `id`: u položky z
	 * příspěvku je `id` ID příspěvku, u databázové je to primární klíč
	 * tabulky, takže se čísla napříč zdroji překrývají (příspěvek 195 a
	 * řádek 195 jsou různé akce).
	 */
	function kct_event_is_same( array $a, array $b ): bool {
		$a_post = (int) ( $a['post_id'] ?? 0 );
		$b_post = (int) ( $b['post_id'] ?? 0 );

		if ( $a_post > 0 && $a_post === $b_post ) {
			return true;
		}

		$a_db = (int) ( $a['db_id'] ?? 0 );
		$b_db = (int) ( $b['db_id'] ?? 0 );

		return $a_db > 0 && $a_db === $b_db;
	}
endif;

if ( ! function_exists( 'kct_event_find' ) ) :
	/**
	 * Najde ve seznamu akci podle ID příspěvku nebo databázového ID.
	 *
	 * `null` znamená, že tenhle web tuhle akci nevypisuje — třeba akci cizího
	 * odboru na odborovém webu, kde /akce-db/{id} vědomě vrací 200. Sekce
	 * detailu se pak nevypíšou; odkazovat do seznamu, který akci neobsahuje,
	 * by nedávalo smysl.
	 */
	function kct_event_find( array $events, int $post_id, int $db_id ): ?array {
		$needle = array( 'post_id' => $post_id, 'db_id' => $db_id );

		foreach ( $events as $event ) {
			if ( kct_event_is_same( $event, $needle ) ) {
				return $event;
			}
		}

		return null;
	}
endif;

if ( ! function_exists( 'kct_event_neighbours' ) ) :
	/**
	 * Akce o jedno dřív a o jedno později v kalendáři.
	 *
	 * @return array{prev: ?array, next: ?array}
	 */
	function kct_event_neighbours( array $events, array $current ): array {
		$dated = array_values( array_filter( $events, static function ( $event ) {
			return ! empty( $event['date'] );
		} ) );

		foreach ( $dated as $i => $event ) {
			if ( kct_event_is_same( $event, $current ) ) {
				return array(
					'prev' => $dated[ $i - 1 ] ?? null,
					'next' => $dated[ $i + 1 ] ?? null,
				);
			}
		}

		return array( 'prev' => null, 'next' => null );
	}
endif;

if ( ! function_exists( 'kct_event_series_wins' ) ) :
	/**
	 * Který ze dvou záznamů téhož ročníku se vypíše?
	 *
	 * Centrální databáze má u některých akcí na jeden ročník víc záznamů a
	 * ty přebývající jsou neplatné — náš import se o jejich zrušení nemá jak
	 * dozvědět (viz spec, oddíl „Mimo rozsah“). Vyhrává nejvyšší `db_id`,
	 * tedy naposledy zavedený záznam: u jediné takové duplicity, jejíž akce
	 * je ještě v budoucnosti, je v plném exportu právě ten.
	 *
	 * Při shodném `db_id` (dvě čistě vlastní akce téhož roku, obě 0) vyhrává
	 * dřívější datum — ať je výsledek stabilní a nezáleží na pořadí vstupu.
	 */
	function kct_event_series_wins( array $candidate, array $incumbent ): bool {
		$c_db = (int) ( $candidate['db_id'] ?? 0 );
		$i_db = (int) ( $incumbent['db_id'] ?? 0 );

		if ( $c_db !== $i_db ) {
			return $c_db > $i_db;
		}

		return strcmp( (string) $candidate['date'], (string) $incumbent['date'] ) < 0;
	}
endif;

if ( ! function_exists( 'kct_event_series' ) ) :
	/**
	 * Ostatní ročníky téže akce, od nejnovějšího k nejstaršímu.
	 *
	 * Skupina je dvojice KALENDÁŘNÍ ROK + ročník, ne jen ročník. Seskupovat
	 * jen podle ročníku nejde: centrální databáze má akce, kde dva různé roky
	 * nesou shodné číslo ročníku (Silvestrovské kolečko má 20. ročník
	 * u 31. 12. 2024 i u 31. 12. 2025), a jeden z těch roků by ze seznamu
	 * tiše zmizel. Seskupovat jen podle kalendářního roku taky ne: akce
	 * pořádaná v jednom roce dvakrát má dva ročníky a oba tam patří.
	 *
	 * Dvojice zvládne obojí a pořád slepí skutečné duplicity — ty mají shodný
	 * rok i ročník (Putování za mistrem Janem má na 44. ročník v roce 2027
	 * tři záznamy). Z každé skupiny projde jeden, viz kct_event_series_wins().
	 */
	function kct_event_series( array $events, array $current ): array {
		$key    = kct_event_series_key( $current );
		$groups = array();

		foreach ( $events as $event ) {
			if ( empty( $event['date'] ) || kct_event_series_key( $event ) !== $key || kct_event_is_same( $event, $current ) ) {
				continue;
			}

			$group = substr( (string) $event['date'], 0, 4 ) . '|' . (int) ( $event['year'] ?? 0 );

			if ( ! isset( $groups[ $group ] ) || kct_event_series_wins( $event, $groups[ $group ] ) ) {
				$groups[ $group ] = $event;
			}
		}

		$series = array_values( $groups );

		usort( $series, static function ( $a, $b ) {
			return strcmp( (string) $b['date'], (string) $a['date'] );
		} );

		return $series;
	}
endif;

if ( ! function_exists( 'kct_event_type_ids' ) ) :
	/**
	 * ID typů akce (`detailid`) jako pole řetězců.
	 *
	 * Feed posílá jediný detail jako plochou položku, ne jako pole položek, a
	 * prázdný prvek jako prázdné pole, ne jako prázdný řetězec — obojí je
	 * potřeba snést, jinak se detail akce shodí (viz poznámky v
	 * content-akce.php a inc/template-tags.php).
	 */
	function kct_event_type_ids( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) ) {
			return array();
		}

		if ( isset( $details['detailid'] ) ) {
			$details = array( $details );
		}

		$ids = array();
		foreach ( $details as $detail ) {
			if ( is_array( $detail ) && ! empty( $detail['detailid'] ) ) {
				$ids[] = (string) $detail['detailid'];
			}
		}

		return array_values( array_unique( $ids ) );
	}
endif;

if ( ! function_exists( 'kct_event_related' ) ) :
	/**
	 * Akce do sekce „Mohlo by vás zajímat“.
	 *
	 * Nejbližší budoucí akce, přednostně se společným typem; doplněné
	 * nejbližšími bez ohledu na typ. Aktuální akce a ostatní ročníky téže
	 * série jsou vynechané — ročníky má vedle sebe postranní oddíl a
	 * duplikovat je nemá cenu.
	 *
	 * @param string $today Dnešní datum ve tvaru Y-m-d.
	 */
	function kct_event_related( array $events, array $current, string $today, int $count = 3 ): array {
		$key      = kct_event_series_key( $current );
		$my_types = kct_event_type_ids( $current );

		$same_type = array();
		$rest      = array();

		foreach ( $events as $event ) {
			if ( empty( $event['date'] ) || strcmp( (string) $event['date'], $today ) < 0 ) {
				continue;
			}

			if ( kct_event_is_same( $event, $current ) || kct_event_series_key( $event ) === $key ) {
				continue;
			}

			if ( $my_types && array_intersect( $my_types, kct_event_type_ids( $event ) ) ) {
				$same_type[] = $event;
			} else {
				$rest[] = $event;
			}
		}

		return array_slice( array_merge( $same_type, $rest ), 0, $count );
	}
endif;
