<?php
/*
 * Plugin Name:       Kct
 * Description:       Plugin skeleton with theme by WPify
 * Version:           KCT_VERSION
 * Requires PHP:      7.3.0
 * Requires at least: 5.3.0
 * Author:            WPify
 * Author URI:        https://www.wpify.io/
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
