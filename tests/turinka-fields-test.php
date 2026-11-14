<?php
/**
 * Čtení číselníkových polí z odpovědí Turinky (Kct\Turinka\Field).
 *
 * Pole jako `status` nebo `difficulty` dřív chodila jako holý řetězec, dnes
 * nesou kód i popisek. Plugin musí umět obojí: na starší instanci aplikace
 * nesmí spadnout a z pole nesmí udělat `Array`.
 *
 * Spouští se bez WordPressu:  php tests/turinka-fields-test.php
 *
 * @package kct
 */

require_once __DIR__ . '/../src/Turinka/Field.php';

use Kct\Turinka\Field;

$failed = 0;

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function check( string $name, $actual, $expected ): void {
	global $failed;

	if ( $actual === $expected ) {
		echo "OK    $name\n";

		return;
	}

	$failed++;
	echo "CHYBA $name\n";
	echo "      čekáno:  " . var_export( $expected, true ) . "\n";
	echo "      dostal:  " . var_export( $actual, true ) . "\n";
}

// --- kód hodnoty ----------------------------------------------------------

check( 'kód z objektu', Field::code( array( 'value' => 'easy', 'label' => 'Snadná' ) ), 'easy' );
check( 'kód z holého řetězce', Field::code( 'easy' ), 'easy' );
check( 'kód z null je prázdný', Field::code( null ), '' );
check( 'kód z objektu bez value je prázdný', Field::code( array( 'label' => 'Snadná' ) ), '' );

// --- popisek --------------------------------------------------------------

check( 'popisek z objektu', Field::label( array( 'value' => 'easy', 'label' => 'Snadná' ) ), 'Snadná' );
check( 'bez popisku se vrátí kód', Field::label( array( 'value' => 'easy' ) ), 'easy' );
check( 'popisek z holého řetězce', Field::label( 'rozhledna' ), 'rozhledna' );
check( 'popisek z null je prázdný', Field::label( null ), '' );

// --- seznamy --------------------------------------------------------------

check(
	'seznam hodnot',
	Field::labels( array(
		array( 'value' => 'families', 'label' => 'Pro rodiny s dětmi' ),
		array( 'value' => 'strollers', 'label' => 'Pro kočárky' ),
	) ),
	array( 'Pro rodiny s dětmi', 'Pro kočárky' )
);

check(
	'jediná hodnota zapsaná jako objekt je seznam o jednom prvku',
	Field::labels( array( 'value' => 'all_year', 'label' => 'Celoroční' ) ),
	array( 'Celoroční' )
);

check( 'prázdný seznam', Field::labels( array() ), array() );
check( 'null není seznam', Field::labels( null ), array() );

// --- stav položky, kvůli kterému to celé vzniklo ---------------------------

$place = array( 'status' => array( 'value' => 'pending', 'label' => 'Čeká na schválení' ) );
check( 'čekající místo se pozná podle kódu', Field::code( $place['status'] ), 'pending' );

$route = array( 'visibility' => array( 'value' => 'unlisted', 'label' => 'Jen přes odkaz' ) );
check( 'viditelnost trasy se pozná podle kódu', Field::code( $route['visibility'] ), 'unlisted' );

echo "\n";

if ( $failed ) {
	echo "Neprošlo: $failed\n";
	exit( 1 );
}

echo "Všechno prošlo\n";
