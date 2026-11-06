<?php
/**
 * Testy čisté logiky endpointu. Spouští se `php test.php`, při chybě končí
 * návratovým kódem 1.
 *
 * Záměrně se nepoužívá assert(): bez zend.assertions=1 se vyhodnocení vypne
 * a test by tiše prošel.
 */

declare( strict_types=1 );

require_once __DIR__ . '/lib.php';

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

// --- kct_host -------------------------------------------------------------
check( 'host z adresy', kct_host( 'https://Odbor.Example.CZ/web/' ), 'odbor.example.cz' );
check( 'host z nesmyslu', kct_host( 'nic' ), '' );
check( 'host z prázdna', kct_host( '' ), '' );

// --- kct_version_from_tag -------------------------------------------------
check( 'tag bez v', kct_version_from_tag( '2.1.0' ), '2.1.0' );
check( 'tag s v', kct_version_from_tag( 'v2.1.0' ), '2.1.0' );

// --- kct_plugin_header ----------------------------------------------------
$header = " * Requires PHP:      8.0\n * Requires at least: 5.3.0\n";
check( 'hlavička PHP', kct_plugin_header( $header, 'Requires PHP' ), '8.0' );
check( 'hlavička WP', kct_plugin_header( $header, 'Requires at least' ), '5.3.0' );
check( 'hlavička chybí', kct_plugin_header( $header, 'Update URI' ), '' );

// --- kct_find_asset -------------------------------------------------------
$assets = array(
	array( 'name' => 'Source code.zip', 'browser_download_url' => 'https://example.com/src.zip' ),
	array( 'name' => 'kct-2.1.0.zip', 'browser_download_url' => 'https://example.com/kct.zip' ),
);
check( 'ZIP pluginu', kct_find_asset( $assets, '/^kct-.+\.zip$/' ), 'https://example.com/kct.zip' );
check( 'ZIP chybí', kct_find_asset( array(), '/^kct-.+\.zip$/' ), '' );

// --- kct_build_metadata ---------------------------------------------------
$config  = require __DIR__ . '/config.php';
$release = array(
	'tag_name'     => 'v2.1.0',
	'published_at' => '2026-11-06T10:00:00Z',
	'body_html'    => '<p>Opraveno</p>',
	'assets'       => $assets,
);

$metadata = kct_build_metadata( $release, $header, $config );
check( 'metadata: verze', $metadata['version'], '2.1.0' );
check( 'metadata: ZIP', $metadata['download_url'], 'https://example.com/kct.zip' );
check( 'metadata: requires_php', $metadata['requires_php'], '8.0' );
check( 'metadata: changelog', $metadata['sections']['changelog'], '<p>Opraveno</p>' );
check( 'metadata: čas vydání', $metadata['last_updated'], '2026-11-06 10:00:00' );

$fallback = kct_build_metadata(
	array( 'tag_name' => '2.1.0', 'body' => '# Opraveno', 'assets' => $assets ),
	$header,
	$config
);
check( 'metadata: changelog bez HTML', $fallback['sections']['changelog'], '<pre># Opraveno</pre>' );

$threw = false;
try {
	kct_build_metadata( array( 'tag_name' => '2.1.0', 'assets' => array() ), $header, $config );
} catch ( RuntimeException $e ) {
	$threw = true;
}
check( 'metadata: vydání bez ZIPu vyhodí výjimku', $threw, true );

// --- kct_visit ------------------------------------------------------------
$visit = kct_visit(
	array(
		'site'              => 'https://odbor.example.cz/',
		'installed_version' => '2.0.0',
		'wp'                => '6.8.1',
		'php'               => '8.2.10',
		'multisite'         => '1',
		'sites_count'       => '12',
		'locale'            => 'cs_CZ',
	),
	'WordPress/6.8.1; https://jine.example.cz'
);
check( 'dotaz: doména z parametru', $visit['domain'], 'odbor.example.cz' );
check( 'dotaz: multisite', $visit['is_multisite'], 1 );
check( 'dotaz: počet webů', $visit['sites_count'], 12 );

$from_ua = kct_visit( array(), 'WordPress/6.8.1; https://odbor2.example.cz' );
check( 'dotaz: doména z User-Agentu', $from_ua['domain'], 'odbor2.example.cz' );
check( 'dotaz: bez multisite', $from_ua['is_multisite'], 0 );

// network_home_url() umí na multisite vrátit „http:///“, když WordPress nezná
// doménu sítě. Parametr je neprázdný, ale hostname v něm není — musí se sáhnout
// po User-Agentu, jinak by se ta instalace do evidence nikdy nedostala.
$rozbita = kct_visit( array( 'site' => 'http:///' ), 'WordPress/6.8.1; https://odbor3.example.cz' );
check( 'dotaz: nesmyslné site → doména z User-Agentu', $rozbita['domain'], 'odbor3.example.cz' );

$oboje_spatne = kct_visit( array( 'site' => 'http:///' ), 'WordPress/6.8.1' );
check( 'dotaz: nesmyslné site i User-Agent → prázdno', $oboje_spatne['domain'], '' );

echo "\n" . ( $failed === 0 ? "Všechno prošlo.\n" : "Selhalo testů: $failed\n" );
exit( $failed === 0 ? 0 : 1 );
