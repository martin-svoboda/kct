<?php
/**
 * Endpoint, na který se ptá plugin-update-checker v pluginu KČT.
 *
 * Adresa: https://updates.sokct.cz/kct
 */

declare( strict_types=1 );

// Pozor: `require`, ne `require_once` — config.php vrací pole a při druhém
// zavedení by require_once vrátil jen true.
$config = require __DIR__ . '/config.php';

$path = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );

// Jen pro lokální vývoj, a musí to být tady — před hlavičkami i před
// zavedením knihoven.
//
// `php -S 127.0.0.1:8099 index.php` posílá přes tenhle soubor i dotazy na
// existující soubory, takže by prehled.php skončil na 404 níž. Vrácené false
// předá obsluhu vestavěnému serveru, ten ale spustí prehled.php ve stejném
// procesu — kdyby se stihly poslat hlavičky, dostal by přehled
// Content-Type: application/json.
//
// Na Apachi se sem takový dotaz nedostane: .htaccess posílá na index.php
// jen to, co na disku není.
if ( PHP_SAPI === 'cli-server' && $path !== '/' && is_file( __DIR__ . $path ) ) {
	return false;
}

// require_once, protože při lokálním běhu zavádí tytéž soubory i prehled.php
// ve stejném procesu.
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/github.php';
require_once __DIR__ . '/storage.php';

// Odpověď musí být čistý JSON. Kdyby měl server zapnuté display_errors,
// jakákoli poznámka nebo deprecation z PHP by se vypsala před tělo odpovědi
// a plugin-update-checker by dostal nerozparsovatelný text — tedy tichý
// výpadek aktualizací na cizích webech. Do logu se chyby zapisují dál.
ini_set( 'display_errors', '0' );

header( 'Content-Type: application/json; charset=utf-8' );

// Bez no-store by mezicache (proxy, CDN) odpovídala za nás a dotazy, které
// chceme počítat, by se k nám nedostaly.
header( 'Cache-Control: no-store' );

if ( trim( $path, '/' ) !== $config['slug'] ) {
	http_response_code( 404 );
	echo json_encode( array( 'error' => 'Neznámý plugin.' ) );
	exit;
}

try {
	$latest   = kct_latest_release( $config );
	$metadata = kct_build_metadata( $latest['release'], $latest['plugin_file'], $config, (string) ( $latest['readme'] ?? '' ) );
} catch ( Throwable $e ) {
	error_log( 'kct-updates: metadata se nepodařilo složit — ' . $e->getMessage() );

	// 503, ne prázdná odpověď. Odpověď „žádná novější verze není“ by schovala
	// aktualizaci, která už byla nabídnutá; po 503 si PUC drží předchozí stav.
	http_response_code( 503 );
	echo json_encode( array( 'error' => 'Metadata nejsou k dispozici.' ) );
	exit;
}

try {
	kct_record_request(
		kct_db( $config ),
		kct_visit( $_GET, (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
		$config
	);
} catch ( Throwable $e ) {
	// Statistika nesmí shodit odpověď: bez metadat se weby neaktualizují,
	// bez záznamu se jen nedozvím, kdo se ozval.
	error_log( 'kct-updates: zápis statistiky selhal — ' . $e->getMessage() );
}

echo json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
