<?php
/*
 * Plugin Name:       Kct
 * Description:       Plugin se šablonou pro Obory a Oblasti KČT
 * Version:           KCT_VERSION
 * Requires PHP:      8.0
 * Requires at least: 5.3.0
 * Author:            Martin Svoboda
 * Author URI:        https://martin-svoboda.cz
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kct
 * Domain Path:       /languages
*/

use Kct\Plugin;
use KctDeps\DI\Container;
use KctDeps\DI\ContainerBuilder;

if ( ! defined( 'KCT_MIN_PHP_VERSION' ) ) {
	define( 'KCT_MIN_PHP_VERSION', '7.3.0' );
}

/**
 * @return Plugin
 * @throws Exception
 */
function kct(): Plugin {
	return kct_container()->get( Plugin::class );
}

/**
 * @return Container
 * @throws Exception
 */
function kct_container(): Container {
	static $container;

	if ( empty( $container ) ) {
		$is_production    = ! WP_DEBUG;
		$file_data        = get_file_data( __FILE__, array( 'version' => 'Version' ) );
		$definition       = require_once __DIR__ . '/config.php';
		$containerBuilder = new ContainerBuilder();
		$containerBuilder->addDefinitions( $definition );

		if ( $is_production ) {
			$containerBuilder->enableCompilation( WP_CONTENT_DIR . '/cache/' . dirname( plugin_basename( __FILE__ ) ) . '/' . $file_data['version'], 'KctCompiledContainer' );
		}

		$container = $containerBuilder->build();
	}

	return $container;
}

/**
 * Naformátuje datum akce pro výpis (karta data).
 *
 * @param string $date   Datum začátku (Y-m-d).
 * @param string $finish Datum konce (Y-m-d), volitelné — u vícedenních akcí.
 *
 * @return array Pole s částmi data pro kartu i pro původní textový výpis.
 */
function kct_format_event_date( string $date, string $finish = '' ): array {
	$start_ts   = strtotime( $date );
	$finish_ts  = $finish ? strtotime( $finish ) : 0;
	$start_day  = strtotime( date( 'Y-m-d', $start_ts ) );
	$finish_day = $finish_ts ? strtotime( date( 'Y-m-d', $finish_ts ) ) : 0;
	$is_range   = $finish_day && $finish_day > $start_day;

	$data = array(
		// Původní klíče (zpětná kompatibilita)
		'day_name'   => date_i18n( 'l', $start_ts ),
		'number'     => date_i18n( 'j. n.', $start_ts ),
		'year'       => date_i18n( 'Y', $start_ts ),
		// Karta data
		'day_abbr'   => date_i18n( 'D', $start_ts ), // "So"
		'day'        => date_i18n( 'j', $start_ts ),  // "29"
		'month'      => date_i18n( 'M', $start_ts ),  // "srp"
		'is_range'   => $is_range,
		'end_day'    => '',
		'end_month'  => '',
		'days'       => 0,
		'days_label' => '',
	);

	if ( $is_range ) {
		$days               = (int) round( ( $finish_day - $start_day ) / DAY_IN_SECONDS ) + 1;
		$data['end_day']    = date_i18n( 'j', $finish_ts );
		$data['end_month']  = date_i18n( 'M', $finish_ts );
		$data['days']       = $days;
		$data['days_label'] = $days . ' ' . ( 1 === $days ? 'den' : ( $days <= 4 ? 'dny' : 'dní' ) );
	}

	return $data;
}

function kct_activate( $network_wide ) {
	kct()->activate( $network_wide );
}

function kct_deactivate( $network_wide ) {
	kct()->deactivate( $network_wide );
}

function kct_uninstall() {
	kct()->uninstall();
}

function kct_theme_is_active() {
	$theme = wp_get_theme();
	return 'kct' === $theme->name || 'kct' === $theme->parent_theme;
}

function kct_php_upgrade_notice() {
	$info = get_plugin_data( __FILE__ );

	echo sprintf(
		__( '<div class="error notice"><p>Opps! %s requires a minimum PHP version of %s. Your current version is: %s. Please contact your host to upgrade.</p></div>', 'kct' ),
		$info['Name'],
		KCT_MIN_PHP_VERSION,
		PHP_VERSION
	);
}

function kct_php_vendor_missing() {
	$info = get_plugin_data( __FILE__ );

	echo sprintf(
		__( '<div class="error notice"><p>Opps! %s is corrupted it seems, please re-install the plugin.</p></div>', 'kct' ),
		$info['Name']
	);
}

if ( version_compare( PHP_VERSION, KCT_MIN_PHP_VERSION ) < 0 ) {
	add_action( 'admin_notices', 'kct_php_upgrade_notice' );
} else {
	$deps_loaded   = false;
	$vendor_loaded = false;

	$deps = array_filter( array( __DIR__ . '/deps/scoper-autoload.php', __DIR__ . '/deps/autoload.php' ), function ( $path ) {
		return file_exists( $path );
	} );

	foreach ( $deps as $dep ) {
		include_once $dep;
		$deps_loaded = true;
	}

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		include_once __DIR__ . '/vendor/autoload.php';
		$vendor_loaded = true;
	}

	if ( $deps_loaded && $vendor_loaded ) {
		add_action( 'plugins_loaded', 'kct', 11 );
		register_activation_hook( __FILE__, 'kct_activate' );
		register_deactivation_hook( __FILE__, 'kct_deactivate' );
		register_uninstall_hook( __FILE__, 'kct_uninstall' );
	} else {
		add_action( 'admin_notices', 'kct_php_vendor_missing' );
	}
}
