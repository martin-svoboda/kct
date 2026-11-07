<?php
/**
 * Testy čtení exportu z Databáze akcí KČT. Spouští se `php tests/feed-import-test.php`,
 * při chybě končí návratovým kódem 1.
 *
 * Záměrně se nepoužívá assert(): bez zend.assertions=1 se vyhodnocení vypne
 * a test by tiše prošel.
 */

declare( strict_types=1 );

require_once __DIR__ . '/../src/Import/FeedXml.php';

use Kct\Import\FeedXml;

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

/** Export o jednom pořadateli, ať se testy čtou. */
function feed( string $name, string $date = '2027-05-01' ): string {
	return '<?xml version="1.0" encoding="UTF-8"?>'
		. '<events version="3.0"><event><id>1</id><deleted>N</deleted>'
		. "<name>$name</name><start><date>$date</date></start>"
		. '</event></events>';
}

// --- escape_stray_ampersands ----------------------------------------------

check(
	'escape: osamocené & se opraví',
	FeedXml::escape_stray_ampersands( '<name>Sport & Fun</name>' ),
	'<name>Sport &amp; Fun</name>'
);

check(
	'escape: už escapované & se nezdvojí',
	FeedXml::escape_stray_ampersands( '<name>Sport &amp; Fun</name>' ),
	'<name>Sport &amp; Fun</name>'
);

check(
	'escape: číselná entita zůstane',
	FeedXml::escape_stray_ampersands( '<name>Sport &#38; Fun</name>' ),
	'<name>Sport &#38; Fun</name>'
);

check(
	'escape: šestnáctková entita zůstane',
	FeedXml::escape_stray_ampersands( '<name>Sport &#x26; Fun</name>' ),
	'<name>Sport &#x26; Fun</name>'
);

check(
	'escape: &lt; a &gt; zůstanou',
	FeedXml::escape_stray_ampersands( '<note>5 &lt; 7 &gt; 3</note>' ),
	'<note>5 &lt; 7 &gt; 3</note>'
);

check(
	'escape: & na konci řetězce',
	FeedXml::escape_stray_ampersands( 'a &' ),
	'a &amp;'
);

check(
	'escape: & před mezerou i před středníkem bez jména',
	FeedXml::escape_stray_ampersands( 'a & b &; c' ),
	'a &amp; b &amp;; c'
);

FeedXml::escape_stray_ampersands( 'a & b &amp; c & d', $count );
check( 'escape: počítá jen skutečné opravy', $count, 2 );

FeedXml::escape_stray_ampersands( 'nic k opravě &amp; tady', $count );
check( 'escape: beze změny hlásí nulu', $count, 0 );

// --- read -----------------------------------------------------------------

$ok = FeedXml::read( feed( 'Pochod' ) );
check( 'read: platný export projde', $ok['data']['event']['name'] ?? null, 'Pochod' );
check( 'read: platný export nic neopravoval', $ok['repaired'], 0 );
check( 'read: platný export nehlásí chybu', $ok['error'], '' );

$fixed = FeedXml::read( feed( 'Zadov Sport & Fun s.r.o' ) );
check( 'read: export s osamoceným & se přečte', $fixed['data']['event']['name'] ?? null, 'Zadov Sport & Fun s.r.o' );
check( 'read: a ohlásí, kolik toho opravil', $fixed['repaired'], 1 );

$broken = FeedXml::read( '<?xml version="1.0"?><events><event><id>1</id></events>' );
check( 'read: opravdu rozbité XML vrátí null', $broken['data'], null );
check( 'read: a popíše, kde to je', str_contains( $broken['error'], 'řádek' ), true );

$empty = FeedXml::read( '' );
check( 'read: prázdný vstup vrátí null', $empty['data'], null );

// --- horizon --------------------------------------------------------------

$events = array(
	array( 'start' => array( 'date' => '2027-05-01' ) ),
	array( 'start' => array( 'date' => '2028-10-02' ) ),
	array( 'start' => array( 'date' => '2026-11-06' ) ),
);
check( 'horizon: vrátí nejzazší datum', FeedXml::horizon( $events ), '2028-10-02' );

check(
	'horizon: prázdné a nesmyslné datum se přeskočí',
	FeedXml::horizon( array(
		array( 'start' => array( 'date' => '' ) ),
		array( 'start' => array() ),
		array( 'start' => array( 'date' => '0000-00-00' ) ),
		array( 'start' => array( 'date' => '2027-01-01' ) ),
	) ),
	'2027-01-01'
);

check( 'horizon: prázdný export vrátí prázdno', FeedXml::horizon( array() ), '' );
check( 'horizon: snese i jiné než pole', FeedXml::horizon( array( 'nesmysl' ) ), '' );

// --- stale_ids ------------------------------------------------------------

$rows = array(
	101 => '2025-06-01', // minulá, v exportu není
	102 => '2027-05-01', // budoucí, v exportu je
	103 => '2027-06-01', // budoucí, v exportu není → ke smazání
	104 => '2029-01-01', // za horizontem exportu
	105 => '',           // bez data
);
$imported = array( 102 );
$today    = '2026-11-07';
$horizon  = '2028-10-02';

check(
	'mazání: smaže jen budoucí akci, kterou export nenese',
	FeedXml::stale_ids( $rows, $imported, $today, $horizon ),
	array( 103 )
);

check(
	'mazání: bez naimportovaných se nemaže nic',
	FeedXml::stale_ids( $rows, array(), $today, $horizon ),
	array()
);

check(
	'mazání: bez horizontu se nemaže nic',
	FeedXml::stale_ids( $rows, $imported, $today, '' ),
	array()
);

check(
	'mazání: dnešní akce se maže jako budoucí',
	FeedXml::stale_ids( array( 201 => '2026-11-07' ), array( 999 ), '2026-11-07', '2028-10-02' ),
	array( 201 )
);

check(
	'mazání: včerejší akce zůstane',
	FeedXml::stale_ids( array( 201 => '2026-11-06' ), array( 999 ), '2026-11-07', '2028-10-02' ),
	array()
);

check(
	'mazání: akce přesně na horizontu se maže',
	FeedXml::stale_ids( array( 201 => '2028-10-02' ), array( 999 ), '2026-11-07', '2028-10-02' ),
	array( 201 )
);

check(
	'mazání: naimportované id jako řetězec se pozná',
	FeedXml::stale_ids( array( 103 => '2027-06-01' ), array( '103' ), $today, $horizon ),
	array()
);

// --- výsledek -------------------------------------------------------------
echo $failed ? "\n$failed chyb\n" : "\nvšechno OK\n";
exit( $failed ? 1 : 0 );
