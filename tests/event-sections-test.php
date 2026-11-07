<?php
/**
 * Testy čisté logiky sekcí detailu akce. Spouští se `php tests/event-sections-test.php`,
 * při chybě končí návratovým kódem 1.
 *
 * Záměrně se nepoužívá assert(): bez zend.assertions=1 se vyhodnocení vypne
 * a test by tiše prošel.
 */

declare( strict_types=1 );

require_once __DIR__ . '/../themes/kct/inc/event-sections.php';

$failed = 0;

function check( string $name, mixed $actual, mixed $expected ): void {
	global $failed;

	if ( $actual === $expected ) {
		echo "OK    $name\n";

		return;
	}

	$failed++;
	echo "CHYBA $name\n";
	echo '      čekáno:  ' . var_export( $expected, true ) . "\n";
	echo '      dostal:  ' . var_export( $actual, true ) . "\n";
}

/** Zkratka na akci; jen klíče, na kterých logika stojí. */
function ev( array $over = array() ): array {
	return array_merge( array(
		'post_id'       => 0,
		'db_id'         => 0,
		'title'         => '',
		'db_title'      => '',
		'db_department' => 0,
		'year'          => 0,
		'date'          => '',
		'details'       => array(),
	), $over );
}

// --- kct_event_normalize_title --------------------------------------------
check( 'název na malá písmena', kct_event_normalize_title( 'Barrande A Trilobit' ), 'barrande a trilobit' );
check( 'název se sjednocenými mezerami', kct_event_normalize_title( "  Do   Okoře\tbez oře " ), 'do okoře bez oře' );

// --- kct_event_series_key -------------------------------------------------
check(
	'klíč bere název z databáze',
	kct_event_series_key( ev( array( 'title' => '105 let Posázavské stezky', 'db_title' => '105. výročí Posázavské stezky' ) ) ),
	'105. výročí posázavské stezky|0'
);
check(
	'klíč bere název příspěvku, když databázový chybí',
	kct_event_series_key( ev( array( 'title' => 'Testovací pochod bez databáze' ) ) ),
	'testovací pochod bez databáze|0'
);
check(
	'klíč odděluje stejný název různých odborů',
	kct_event_series_key( ev( array( 'db_title' => 'Tříkrálový pochod', 'db_department' => 102045 ) ) )
		=== kct_event_series_key( ev( array( 'db_title' => 'Tříkrálový pochod', 'db_department' => 102104 ) ) ),
	false
);

// --- kct_event_is_same ----------------------------------------------------
check( 'shoda podle příspěvku', kct_event_is_same( ev( array( 'post_id' => 2044 ) ), ev( array( 'post_id' => 2044 ) ) ), true );
check( 'shoda podle db_id', kct_event_is_same( ev( array( 'db_id' => 24044 ) ), ev( array( 'db_id' => 24044 ) ) ), true );
check(
	'stejné id z různých zdrojů není shoda',
	kct_event_is_same( ev( array( 'post_id' => 195 ) ), ev( array( 'db_id' => 195 ) ) ),
	false
);
check( 'dvě nespárované akce nejsou shoda', kct_event_is_same( ev(), ev() ), false );

// --- kct_event_find -------------------------------------------------------
$list = array(
	ev( array( 'db_id' => 21684, 'date' => '2024-05-18' ) ),
	ev( array( 'post_id' => 2044, 'db_id' => 24065, 'date' => '2026-06-21' ) ),
	ev( array( 'db_id' => 24893, 'date' => '2027-05-15' ) ),
);
check( 'najde databázovou akci', kct_event_find( $list, 0, 21684 )['date'], '2024-05-18' );
check( 'najde spárovanou akci podle příspěvku', kct_event_find( $list, 2044, 0 )['date'], '2026-06-21' );
check( 'nenajde akci mimo seznam', kct_event_find( $list, 0, 99999 ), null );

// --- kct_event_neighbours -------------------------------------------------
$n = kct_event_neighbours( $list, ev( array( 'post_id' => 2044, 'db_id' => 24065 ) ) );
check( 'předchozí je dřívější datum', $n['prev']['date'], '2024-05-18' );
check( 'další je pozdější datum', $n['next']['date'], '2027-05-15' );

$n = kct_event_neighbours( $list, ev( array( 'db_id' => 21684 ) ) );
check( 'nejstarší akce nemá předchozí', $n['prev'], null );
check( 'nejstarší akce má další', $n['next']['date'], '2026-06-21' );

$n = kct_event_neighbours( $list, ev( array( 'db_id' => 24893 ) ) );
check( 'nejnovější akce nemá další', $n['next'], null );

$n = kct_event_neighbours( $list, ev( array( 'db_id' => 55555 ) ) );
check( 'akce mimo seznam nemá sousedy', array( $n['prev'], $n['next'] ), array( null, null ) );

check(
	'akce bez data se jako soused nepočítá',
	kct_event_neighbours(
		array(
			ev( array( 'db_id' => 1, 'date' => '' ) ),
			ev( array( 'db_id' => 2, 'date' => '2026-01-01' ) ),
			ev( array( 'db_id' => 3, 'date' => '2027-01-01' ) ),
		),
		ev( array( 'db_id' => 2 ) )
	)['prev'],
	null
);

// --- kct_event_series -----------------------------------------------------
$serie = array(
	ev( array( 'db_id' => 21684, 'db_title' => 'Barrande a trilobit', 'db_department' => 102131, 'year' => 25, 'date' => '2024-05-18' ) ),
	ev( array( 'db_id' => 22864, 'db_title' => 'Barrande a trilobit', 'db_department' => 102131, 'year' => 26, 'date' => '2025-05-24' ) ),
	ev( array( 'db_id' => 24044, 'db_title' => 'Barrande a trilobit', 'db_department' => 102131, 'year' => 27, 'date' => '2026-05-16' ) ),
	ev( array( 'db_id' => 24893, 'db_title' => 'Barrande a trilobit', 'db_department' => 102131, 'year' => 28, 'date' => '2027-05-15' ) ),
	ev( array( 'db_id' => 24088, 'db_title' => 'Tříkrálový pochod', 'db_department' => 102045, 'year' => 47, 'date' => '2026-01-10' ) ),
);
$out = kct_event_series( $serie, $serie[2] );
check( 'série bez aktuálního ročníku', count( $out ), 3 );
check( 'série od nejnovějšího', array_column( $out, 'year' ), array( 28, 26, 25 ) );
check( 'cizí odbor se do série nepletl', array_column( $out, 'db_title' ), array( 'Barrande a trilobit', 'Barrande a trilobit', 'Barrande a trilobit' ) );

$dupl = array(
	ev( array( 'db_id' => 23953, 'db_title' => 'Putování za mistrem Janem', 'db_department' => 102131, 'year' => 43, 'date' => '2026-07-04' ) ),
	ev( array( 'db_id' => 24584, 'db_title' => 'Putování za mistrem Janem', 'db_department' => 102131, 'year' => 44, 'date' => '2027-07-04' ) ),
	ev( array( 'db_id' => 24585, 'db_title' => 'Putování za mistrem Janem', 'db_department' => 102131, 'year' => 44, 'date' => '2027-07-04' ) ),
	ev( array( 'db_id' => 24894, 'db_title' => 'Putování za mistrem Janem', 'db_department' => 102131, 'year' => 44, 'date' => '2027-07-03' ) ),
);
$out = kct_event_series( $dupl, $dupl[0] );
check( 'duplicitní ročník jedním řádkem', count( $out ), 1 );
check( 'z duplicit vyhrává nejvyšší db_id', $out[0]['db_id'], 24894 );

$vlastni = array(
	ev( array( 'post_id' => 5, 'title' => 'Testovací pochod bez databáze', 'year' => 1, 'date' => '2025-05-10' ) ),
	ev( array( 'post_id' => 6, 'title' => 'Testovací pochod bez databáze', 'year' => 2, 'date' => '2026-05-09' ) ),
	ev( array( 'post_id' => 7, 'title' => 'Testovací pochod bez databáze', 'year' => 3, 'date' => '2027-05-08' ) ),
);
$out = kct_event_series( $vlastni, $vlastni[1] );
check( 'série drží i bez databázového záznamu', array_column( $out, 'year' ), array( 3, 1 ) );

$bez_rocniku = array(
	ev( array( 'post_id' => 11, 'title' => 'Sraz', 'year' => 0, 'date' => '2025-03-01' ) ),
	ev( array( 'post_id' => 12, 'title' => 'Sraz', 'year' => 0, 'date' => '2026-05-02' ) ),
	ev( array( 'post_id' => 13, 'title' => 'Sraz', 'year' => 0, 'date' => '2026-09-02' ) ),
);
$out = kct_event_series( $bez_rocniku, $bez_rocniku[0] );
check( 'bez ročníku se seskupuje podle roku v datu', count( $out ), 1 );
check( 'ze stejného roku bez ročníku vyhrává dřívější datum', $out[0]['date'], '2026-05-02' );

// Centrální databáze má akce, kde dva různé roky nesou shodné číslo ročníku
// (Silvestrovské kolečko: 20. ročník u 31. 12. 2024 i u 31. 12. 2025).
// Seskupení jen podle ročníku by jeden z těch roků tiše zahodilo.
$spatny_rocnik = array(
	ev( array( 'db_id' => 21638, 'db_title' => 'Silvestrovské kolečko', 'year' => 20, 'date' => '2024-12-31' ) ),
	ev( array( 'db_id' => 22820, 'db_title' => 'Silvestrovské kolečko', 'year' => 20, 'date' => '2025-12-31' ) ),
	ev( array( 'db_id' => 23988, 'db_title' => 'Silvestrovské kolečko', 'year' => 22, 'date' => '2026-12-31' ) ),
	ev( array( 'db_id' => 24944, 'db_title' => 'Silvestrovské kolečko', 'year' => 23, 'date' => '2027-12-31' ) ),
);
$out = kct_event_series( $spatny_rocnik, $spatny_rocnik[3] );
check( 'shodný ročník v různých letech se nezahazuje', array_column( $out, 'date' ), array( '2026-12-31', '2025-12-31', '2024-12-31' ) );

// Akce pořádaná dvakrát v jednom roce má dva ročníky a oba tam patří.
$dva_v_roce = array(
	ev( array( 'db_id' => 31, 'db_title' => 'Dvakrát do roka', 'year' => 5, 'date' => '2026-04-11' ) ),
	ev( array( 'db_id' => 32, 'db_title' => 'Dvakrát do roka', 'year' => 6, 'date' => '2026-10-10' ) ),
	ev( array( 'db_id' => 33, 'db_title' => 'Dvakrát do roka', 'year' => 7, 'date' => '2027-04-10' ) ),
);
check( 'dva ročníky v jednom roce zůstanou oba', count( kct_event_series( $dva_v_roce, $dva_v_roce[2] ) ), 2 );

check( 'jednorázovka nemá sérii', kct_event_series( array( $serie[4] ), $serie[4] ), array() );

// --- kct_event_type_ids ---------------------------------------------------
check(
	'typy z pole položek',
	kct_event_type_ids( ev( array( 'details' => array( array( 'detailid' => '1' ), array( 'detailid' => '7' ) ) ) ) ),
	array( '1', '7' )
);
check(
	'jediný typ přijde z feedu plochý',
	kct_event_type_ids( ev( array( 'details' => array( 'detailid' => '3', 'name' => 'Pěší' ) ) ) ),
	array( '3' )
);
check( 'prázdné details', kct_event_type_ids( ev() ), array() );
check(
	'prázdný prvek feedu je pole, ne řetězec',
	kct_event_type_ids( ev( array( 'details' => array( array(), array( 'detailid' => '5' ) ) ) ) ),
	array( '5' )
);

// --- kct_event_related ----------------------------------------------------
// Pořadí je podle data vzestupně — tak seznam vrací get_events() a na tom
// pořadí kct_event_related() stojí.
$pool = array(
	ev( array( 'db_id' => 1, 'db_title' => 'Minulá', 'date' => '2026-01-01', 'details' => array( array( 'detailid' => '1' ) ) ) ),
	ev( array( 'db_id' => 2, 'db_title' => 'Aktuální', 'date' => '2026-12-01', 'details' => array( array( 'detailid' => '1' ) ) ) ),
	ev( array( 'db_id' => 4, 'db_title' => 'Jiná bez typu', 'date' => '2026-12-05', 'details' => array() ) ),
	ev( array( 'db_id' => 5, 'db_title' => 'Jiná se typem', 'date' => '2026-12-10', 'details' => array( array( 'detailid' => '1' ) ) ) ),
	ev( array( 'db_id' => 6, 'db_title' => 'Jiná s jiným typem', 'date' => '2026-12-15', 'details' => array( array( 'detailid' => '9' ) ) ) ),
	ev( array( 'db_id' => 7, 'db_title' => 'Další se typem', 'date' => '2026-12-20', 'details' => array( array( 'detailid' => '1' ) ) ) ),
	ev( array( 'db_id' => 3, 'db_title' => 'Aktuální', 'date' => '2027-12-01', 'details' => array( array( 'detailid' => '1' ) ) ) ),
);
$out = kct_event_related( $pool, $pool[1], '2026-11-07', 3 );
check( 'doporučené: aktuální akce vynechaná', in_array( 2, array_column( $out, 'db_id' ), true ), false );
check( 'doporučené: jiný ročník téže akce vynechaný', in_array( 3, array_column( $out, 'db_id' ), true ), false );
check( 'doporučené: proběhlá akce vynechaná', in_array( 1, array_column( $out, 'db_id' ), true ), false );
check( 'doporučené: stejný typ má přednost', array_column( $out, 'db_id' ), array( 5, 7, 4 ) );
check( 'doporučené: počet se dodrží', count( kct_event_related( $pool, $pool[1], '2026-11-07', 2 ) ), 2 );
check( 'doporučené: akce bez typu bere nejbližší', array_column( kct_event_related( $pool, $pool[2], '2026-11-07', 2 ), 'db_id' ), array( 2, 5 ) );

// --- výsledek -------------------------------------------------------------
echo $failed ? "\n$failed chyb\n" : "\nvšechno OK\n";
exit( $failed ? 1 : 0 );
