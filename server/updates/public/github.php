<?php
/**
 * Metadata posledního vydání z GitHubu, s cache do souboru.
 */

declare( strict_types=1 );

/**
 * Jeden GET na GitHub.
 *
 * @throws RuntimeException Nedostupnost, timeout nebo jiný stav než 200.
 */
function kct_github_get( string $url, string $accept ): string {
	$curl = curl_init( $url );

	curl_setopt_array(
		$curl,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT        => 10,
			// Bez User-Agentu odpovídá GitHub API 403.
			CURLOPT_USERAGENT      => 'kct-updates (+https://updates.sokct.cz)',
			CURLOPT_HTTPHEADER     => array( 'Accept: ' . $accept ),
		)
	);

	$body  = curl_exec( $curl );
	$code  = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
	$error = curl_error( $curl );
	curl_close( $curl );

	if ( ! is_string( $body ) || $code !== 200 ) {
		throw new RuntimeException( sprintf( '%s → HTTP %d %s', $url, $code, $error ) );
	}

	return $body;
}

/**
 * Cache z disku, nebo null, když není nebo je poškozená.
 */
function kct_read_cache( string $file ): ?array {
	if ( ! is_readable( $file ) ) {
		return null;
	}

	$data = json_decode( (string) file_get_contents( $file ), true );

	// `readme` se nevyžaduje: cache uložená dřív, než se changelog začal brát
	// z readme.txt, je pořád platná — jen se z ní changelog poskládá postaru.
	return is_array( $data ) && isset( $data['fetched_at'], $data['release'], $data['plugin_file'] )
		? $data
		: null;
}

/**
 * Poslední vydání a k němu hlavní soubor pluginu v daném tagu.
 *
 * Při nedostupnosti GitHubu se vrátí cache bez ohledu na věk: verze v ní je
 * pořád platná a stará metadata jsou lepší než žádná. Není-li cache vůbec,
 * výjimka propadne volajícímu, který na ni odpoví 503.
 *
 * @throws RuntimeException
 */
function kct_latest_release( array $config ): array {
	$cache_file = $config['data_dir'] . '/latest.json';
	$cached     = kct_read_cache( $cache_file );

	if ( $cached !== null && ( time() - (int) $cached['fetched_at'] ) < $config['cache_ttl'] ) {
		return $cached;
	}

	try {
		// Accept s „html+json“ znamená, že GitHub přiloží i body_html —
		// changelog tedy nemusíme převádět z Markdownu sami.
		$release = json_decode(
			kct_github_get(
				'https://api.github.com/repos/' . $config['repo'] . '/releases/latest',
				'application/vnd.github.html+json'
			),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		$tag = (string) ( $release['tag_name'] ?? '' );

		if ( $tag === '' ) {
			throw new RuntimeException( 'Vydání bez tagu.' );
		}

		$plugin_file = kct_github_get(
			'https://raw.githubusercontent.com/' . $config['repo'] . '/' . rawurlencode( $tag ) . '/kct.php',
			'text/plain'
		);

		// readme.txt nese changelog. Vydání starší než readme ho nemá, a to není
		// chyba — proto se jeho nedostupnost nepropisuje do celé odpovědi.
		try {
			$readme = kct_github_get(
				'https://raw.githubusercontent.com/' . $config['repo'] . '/' . rawurlencode( $tag ) . '/readme.txt',
				'text/plain'
			);
		} catch ( Throwable $e ) {
			$readme = '';
		}

		$fresh = array(
			'fetched_at'  => time(),
			'release'     => $release,
			'plugin_file' => $plugin_file,
			'readme'      => $readme,
		);

		if ( ! is_dir( $config['data_dir'] ) ) {
			mkdir( $config['data_dir'], 0775, true );
		}

		file_put_contents( $cache_file, json_encode( $fresh ), LOCK_EX );

		return $fresh;
	} catch ( Throwable $e ) {
		error_log( 'kct-updates: GitHub se nepodařilo načíst — ' . $e->getMessage() );

		if ( $cached !== null ) {
			return $cached;
		}

		throw new RuntimeException( 'Metadata nejsou k dispozici a cache není.', 0, $e );
	}
}
