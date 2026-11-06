<?php
/**
 * Evidence instalací. Jeden řádek na doménu se současným stavem, plus
 * historie dotazů s krátkou retencí.
 *
 * IP adresa se sem vědomě nezapisuje — v access logu Apache je i tak, s
 * vlastní rotací, a tady by jen přidávala osobní údaj bez užitku.
 */

declare( strict_types=1 );

function kct_db( array $config ): PDO {
	if ( ! is_dir( $config['data_dir'] ) ) {
		mkdir( $config['data_dir'], 0775, true );
	}

	$db = new PDO( 'sqlite:' . $config['data_dir'] . '/updates.sqlite' );
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

	// WAL, aby čtení přehledu neblokovalo zápis dotazu.
	$db->exec( 'PRAGMA journal_mode = WAL' );

	$db->exec(
		'CREATE TABLE IF NOT EXISTS sites (
			domain         TEXT PRIMARY KEY,
			plugin_version TEXT,
			wp_version     TEXT,
			php_version    TEXT,
			is_multisite   INTEGER,
			sites_count    INTEGER,
			locale         TEXT,
			first_seen     TEXT,
			last_seen      TEXT,
			request_count  INTEGER DEFAULT 0
		)'
	);

	$db->exec(
		'CREATE TABLE IF NOT EXISTS requests (
			id             INTEGER PRIMARY KEY AUTOINCREMENT,
			domain         TEXT,
			plugin_version TEXT,
			wp_version     TEXT,
			php_version    TEXT,
			created_at     TEXT
		)'
	);

	return $db;
}

/**
 * Zapíše dotaz. Doména bez hostname (dotaz z prohlížeče, přepsaný
 * User-Agent) se nezaznamenává — řádek „“ by v přehledu jen šuměl.
 */
function kct_record_request( PDO $db, array $visit, array $config ): void {
	if ( $visit['domain'] === '' ) {
		return;
	}

	$now = gmdate( 'Y-m-d H:i:s' );

	$db->prepare(
		'INSERT INTO sites
			(domain, plugin_version, wp_version, php_version, is_multisite, sites_count, locale, first_seen, last_seen, request_count)
		VALUES
			(:domain, :plugin, :wp, :php, :ms, :count, :locale, :now, :now, 1)
		ON CONFLICT(domain) DO UPDATE SET
			plugin_version = :plugin,
			wp_version     = :wp,
			php_version    = :php,
			is_multisite   = :ms,
			sites_count    = :count,
			locale         = :locale,
			last_seen      = :now,
			request_count  = request_count + 1'
	)->execute(
		array(
			':domain' => $visit['domain'],
			':plugin' => $visit['plugin_version'],
			':wp'     => $visit['wp_version'],
			':php'    => $visit['php_version'],
			':ms'     => $visit['is_multisite'],
			':count'  => $visit['sites_count'],
			':locale' => $visit['locale'],
			':now'    => $now,
		)
	);

	$db->prepare(
		'INSERT INTO requests (domain, plugin_version, wp_version, php_version, created_at)
		VALUES (?, ?, ?, ?, ?)'
	)->execute(
		array( $visit['domain'], $visit['plugin_version'], $visit['wp_version'], $visit['php_version'], $now )
	);

	// Úklid bez cronu. Při desítkách webů a dvou dotazech denně to vyjde
	// řádově na jeden úklid za týden — na nic víc není důvod.
	if ( random_int( 1, 200 ) === 1 ) {
		$db->prepare( 'DELETE FROM requests WHERE created_at < ?' )
			->execute( array( gmdate( 'Y-m-d H:i:s', time() - $config['retention_days'] * 86400 ) ) );
	}
}

/**
 * Všechny známé domény, od těch, co se ozvaly naposledy.
 */
function kct_sites( PDO $db ): array {
	return $db->query( 'SELECT * FROM sites ORDER BY last_seen DESC' )->fetchAll( PDO::FETCH_ASSOC );
}
