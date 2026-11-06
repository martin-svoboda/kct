<?php
/**
 * Čistá logika endpointu: parsování a skládání dat. Nic tady nesmí sahat na
 * síť, disk ani databázi — celý soubor je testovaný `test.php` bez přípravy
 * jakéhokoli prostředí.
 */

declare( strict_types=1 );

/**
 * Hostname z adresy webu, malými písmeny. Prázdný řetězec, když adresa
 * nedává smysl.
 */
function kct_host( string $url ): string {
	$host = parse_url( trim( $url ), PHP_URL_HOST );

	return is_string( $host ) ? strtolower( $host ) : '';
}

/**
 * Verze z tagu vydání.
 *
 * Vedoucí „v“ se zahazuje: version_compare() ho nečeká a „v2.1.0“ by vyšlo
 * jako nižší než „2.1.0“, takže by se aktualizace nikdy nenabídla.
 */
function kct_version_from_tag( string $tag ): string {
	return ltrim( trim( $tag ), 'vV' );
}

/**
 * Hodnota hlavičky z hlavního souboru pluginu.
 *
 * Čte se z kct.php na GitHubu, ne z konfigurace endpointu — jinak by minimální
 * verze PHP byla na dvou místech a časem se rozešla.
 */
function kct_plugin_header( string $contents, string $header ): string {
	$pattern = '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':(.*)$/mi';

	return preg_match( $pattern, $contents, $match ) ? trim( $match[1] ) : '';
}

/**
 * Adresa instalačního ZIPu z příloh vydání.
 */
function kct_find_asset( array $assets, string $pattern ): string {
	foreach ( $assets as $asset ) {
		if ( preg_match( $pattern, (string) ( $asset['name'] ?? '' ) ) ) {
			return (string) ( $asset['browser_download_url'] ?? '' );
		}
	}

	return '';
}

/**
 * Čas vydání ve formátu, který čeká WordPress.
 */
function kct_mysql_time( string $iso ): string {
	$timestamp = strtotime( $iso );

	return gmdate( 'Y-m-d H:i:s', $timestamp ?: time() );
}

/**
 * Metadata pro plugin-update-checker.
 *
 * @throws RuntimeException Vydání bez verze nebo bez instalačního ZIPu.
 */
function kct_build_metadata( array $release, string $plugin_file, array $config ): array {
	$version = kct_version_from_tag( (string) ( $release['tag_name'] ?? '' ) );
	$zip     = kct_find_asset( (array) ( $release['assets'] ?? array() ), $config['asset_pattern'] );

	// Nabídnout aktualizaci bez souboru ke stažení znamená chybu v administraci
	// cizího webu, kterou tam nikdo neumí vyřešit. Lepší je neodpovědět.
	if ( $version === '' || $zip === '' ) {
		throw new RuntimeException( 'Vydání nemá použitelný tag nebo instalační ZIP.' );
	}

	// GitHub posílá changelog jako HTML, když se o to požádá hlavičkou Accept
	// (viz github.php). Když ne, projde se aspoň surový Markdown — modál
	// s detaily má být čitelný, i kdyby GitHub media type přestal podporovat.
	$changelog = (string) ( $release['body_html'] ?? '' );

	if ( $changelog === '' ) {
		$changelog = '<pre>' . htmlspecialchars( (string) ( $release['body'] ?? '' ), ENT_QUOTES, 'UTF-8' ) . '</pre>';
	}

	return array(
		'name'            => $config['name'],
		'slug'            => $config['slug'],
		'version'         => $version,
		'download_url'    => $zip,
		'homepage'        => $config['homepage'],
		'author'          => $config['author'],
		'author_homepage' => $config['author_homepage'],
		'requires'        => kct_plugin_header( $plugin_file, 'Requires at least' ),
		'requires_php'    => kct_plugin_header( $plugin_file, 'Requires PHP' ),
		'last_updated'    => kct_mysql_time( (string) ( $release['published_at'] ?? '' ) ),
		'sections'        => array(
			'description' => $config['description'],
			'changelog'   => $changelog,
		),
	);
}

/**
 * Adresa webu z hlavičky User-Agent, kterou posílá WordPress HTTP API
 * („WordPress/6.8; https://example.cz“).
 *
 * Je to záloha pro případ, že parametr `site` nepřijde.
 */
function kct_site_from_user_agent( string $user_agent ): string {
	return preg_match( '~https?://\S+~', $user_agent, $match ) ? $match[0] : '';
}

/**
 * Údaje o webu z dotazu.
 *
 * `installed_version`, `php` a `locale` posílá PUC sám, zbytek přidává
 * UpdateChecker v pluginu. Všechno se zkracuje — do databáze jde cizí vstup.
 */
function kct_visit( array $query, string $user_agent ): array {
	// Záloha se bere, i když parametr `site` přijde neprázdný, ale nesmyslný.
	// Stává se to: na multisite může network_home_url() vrátit „http:///“,
	// když WordPress nezná doménu sítě. Bez téhle pojistky by se taková
	// instalace do evidence nikdy nedostala.
	$domain = kct_host( (string) ( $query['site'] ?? '' ) );

	if ( $domain === '' ) {
		$domain = kct_host( kct_site_from_user_agent( $user_agent ) );
	}

	return array(
		'domain'         => $domain,
		'plugin_version' => substr( (string) ( $query['installed_version'] ?? '' ), 0, 32 ),
		'wp_version'     => substr( (string) ( $query['wp'] ?? '' ), 0, 32 ),
		'php_version'    => substr( (string) ( $query['php'] ?? '' ), 0, 32 ),
		'is_multisite'   => empty( $query['multisite'] ) ? 0 : 1,
		'sites_count'    => max( 0, (int) ( $query['sites_count'] ?? 0 ) ),
		'locale'         => substr( (string) ( $query['locale'] ?? '' ), 0, 16 ),
	);
}
