<?php
/**
 * Přehled instalací pluginu.
 *
 * Autorizace je basic auth z .htaccess, ne tady — soubor sám o přihlášení
 * neví. Jediný důvod, proč to zmiňovat: bez toho pravidla je tahle stránka
 * veřejná.
 */

declare( strict_types=1 );

// `require` u config.php (vrací pole), `require_once` u knihoven — při
// lokálním běhu je zavádí i index.php ve stejném procesu.
$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/github.php';
require_once __DIR__ . '/storage.php';

// Verze k porovnání se bere z cache, ne novým dotazem na GitHub: přehled se
// otevírá zřídka a není důvod na něm čekat.
$latest_version = '';
$cached         = kct_read_cache( $config['data_dir'] . '/latest.json' );

if ( $cached !== null ) {
	$latest_version = kct_version_from_tag( (string) ( $cached['release']['tag_name'] ?? '' ) );
}

$sites      = kct_sites( kct_db( $config ) );
$stale_ts   = time() - $config['stale_days'] * 86400;
$by_version = array();

foreach ( $sites as $site ) {
	$key                = $site['plugin_version'] ?: '?';
	$by_version[ $key ] = ( $by_version[ $key ] ?? 0 ) + 1;
}

krsort( $by_version );

function e( ?string $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
	<meta charset="utf-8">
	<title>Instalace pluginu KČT</title>
	<style>
		body { font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #222; }
		table { border-collapse: collapse; width: 100%; }
		th, td { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #ddd; }
		th { background: #f4f4f4; }
		tr.stale { background: #fff6f6; }
		tr.stale td:first-child::after { content: ' — neozval se'; color: #b00; font-size: .85em; }
		td.behind { color: #b00; font-weight: 600; }
		.summary { margin-bottom: 1.5rem; }
	</style>
</head>
<body>
<h1>Instalace pluginu KČT</h1>

<div class="summary">
	<p>
		Domén: <strong><?= count( $sites ) ?></strong>.
		Poslední vydání: <strong><?= e( $latest_version ?: 'neznámé' ) ?></strong>.
	</p>
	<p>
		Podle verze:
		<?php foreach ( $by_version as $version => $count ) : ?>
			<?= e( (string) $version ) ?> → <?= (int) $count ?>&nbsp;&nbsp;
		<?php endforeach; ?>
	</p>
</div>

<table>
	<thead>
	<tr>
		<th>Doména</th>
		<th>Verze pluginu</th>
		<th>WordPress</th>
		<th>PHP</th>
		<th>Síť</th>
		<th>Dotazů</th>
		<th>První kontakt</th>
		<th>Poslední kontakt</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $sites as $site ) : ?>
		<?php
		$is_stale  = strtotime( (string) $site['last_seen'] ) < $stale_ts;
		$is_behind = $latest_version !== ''
			&& $site['plugin_version']
			&& version_compare( (string) $site['plugin_version'], $latest_version, '<' );
		?>
		<tr class="<?= $is_stale ? 'stale' : '' ?>">
			<td><?= e( $site['domain'] ) ?></td>
			<td class="<?= $is_behind ? 'behind' : '' ?>"><?= e( $site['plugin_version'] ) ?></td>
			<td><?= e( $site['wp_version'] ) ?></td>
			<td><?= e( $site['php_version'] ) ?></td>
			<td><?= $site['is_multisite'] ? 'ano (' . (int) $site['sites_count'] . ')' : 'ne' ?></td>
			<td><?= (int) $site['request_count'] ?></td>
			<td><?= e( $site['first_seen'] ) ?></td>
			<td><?= e( $site['last_seen'] ) ?></td>
		</tr>
	<?php endforeach; ?>
	<?php if ( ! $sites ) : ?>
		<tr><td colspan="8">Zatím se neozval žádný web.</td></tr>
	<?php endif; ?>
	</tbody>
</table>
</body>
</html>
