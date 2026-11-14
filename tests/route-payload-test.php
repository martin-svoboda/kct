<?php
/**
 * Testy čisté logiky zápisu trasy do Turinky. Spouští se
 * `php tests/route-payload-test.php`, při chybě končí návratovým kódem 1.
 *
 * Záměrně se nepoužívá assert(): bez zend.assertions=1 se vyhodnocení vypne
 * a test by tiše prošel.
 */

declare( strict_types=1 );

// Záslepky funkcí WordPressu, na kterých testovaná logika stojí.
function untrailingslashit( string $value ): string {
	return rtrim( $value, '/\\' );
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_strip_all_tags( string $value ): string {
	return trim( strip_tags( $value ) );
}

require_once __DIR__ . '/../src/Turinka/Config.php';
require_once __DIR__ . '/../src/Turinka/RoutePayload.php';

use Kct\Turinka\Config;
use Kct\Turinka\RoutePayload;

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

/** Config s přepsanými konstantami prostředí. */
function config( string $url, string $host ): Config {
	return new class( $url, $host ) extends Config {
		public function __construct( private string $url, private string $host ) {
		}

		public function base_url(): string {
			return untrailingslashit( $this->url );
		}

		public function host_header(): string {
			return $this->host;
		}
	};
}

$local = config( 'http://ddev-turi-web', 'turi.ddev.site' );

check(
	'rebase přepíše hostitele kontejneru na veřejné jméno',
	$local->rebase( 'http://ddev-turi-web/integrace/formular/abc' ),
	'https://turi.ddev.site/integrace/formular/abc'
);

check(
	'rebase nechá dotaz i fragment',
	$local->rebase( 'http://ddev-turi-web/vlozit/trasa/x?moduly=map,info#profil' ),
	'https://turi.ddev.site/vlozit/trasa/x?moduly=map,info#profil'
);

check(
	'rebase povýší na https i adresu se správným jménem',
	$local->rebase( 'http://turi.ddev.site/integrace/formular/abc' ),
	'https://turi.ddev.site/integrace/formular/abc'
);

check(
	'rebase nesahá na cizí hostitele',
	$local->rebase( 'https://example.com/a' ),
	'https://example.com/a'
);

check(
	'rebase snese prázdnou adresu',
	$local->rebase( '' ),
	''
);

$production = config( 'https://turinka.cz', '' );

check(
	'bez hlavičky Host se nic nemění',
	$production->rebase( 'https://turinka.cz/integrace/formular/abc' ),
	'https://turinka.cz/integrace/formular/abc'
);

$payload = new RoutePayload();

check(
	'publikovaná trasa posílá identitu, název, text i odkaz',
	$payload->route( 42, 'Okruh kolem rybníka', '<p>Text</p>', 'https://example.test/trasa/okruh/', true ),
	array(
		'source_id'   => '42',
		'title'       => 'Okruh kolem rybníka',
		'description' => '<p>Text</p>',
		'source_url'  => 'https://example.test/trasa/okruh/',
	)
);

check(
	'odpublikovaná trasa odkaz výslovně ruší hodnotou null',
	$payload->route( 42, 'Okruh', '<p>Text</p>', 'https://example.test/trasa/okruh/', false ),
	array(
		'source_id'   => '42',
		'title'       => 'Okruh',
		'description' => '<p>Text</p>',
		'source_url'  => null,
	)
);

check(
	'prázdný text se nevynucuje',
	$payload->route( 42, 'Okruh', "  \n ", '', true ),
	array(
		'source_id'  => '42',
		'title'      => 'Okruh',
		'source_url' => null,
	)
);

check(
	'text jen z prázdných značek je prázdný text',
	$payload->route( 42, 'Okruh', '<p> </p>', '', true ),
	array(
		'source_id'  => '42',
		'title'      => 'Okruh',
		'source_url' => null,
	)
);

// Název vlastní formulář Turinky; web ho posílá jedině při prvním odeslání.
check(
	'další odeslání název vynechává, aby nepřebilo formulář',
	$payload->route( 42, 'Okruh kolem rybníka', '<p>Text</p>', 'https://example.test/trasa/okruh/', true, false ),
	array(
		'source_id'   => '42',
		'description' => '<p>Text</p>',
		'source_url'  => 'https://example.test/trasa/okruh/',
	)
);

check(
	'první odeslání název pošle',
	array_key_exists( 'title', $payload->route( 42, 'Okruh', '<p>Text</p>', '', true, true ) ),
	true
);

check(
	'odpublikování bez názvu ruší jen odkaz',
	$payload->route( 42, 'Okruh', '<p>Text</p>', 'https://example.test/trasa/okruh/', false, false ),
	array(
		'source_id'   => '42',
		'description' => '<p>Text</p>',
		'source_url'  => null,
	)
);

check(
	'prefill nese jen název',
	$payload->prefill( 'Okruh kolem rybníka' ),
	array( 'title' => 'Okruh kolem rybníka' )
);

check(
	'prefill bez názvu je prázdný',
	$payload->prefill( '   ' ),
	array()
);

echo $failed ? "\n$failed chyb\n" : "\nVšechno prošlo\n";
exit( $failed ? 1 : 0 );
