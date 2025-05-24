<?php

namespace Kct;

use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;

class Frontend {
	/** @var PluginUtils */
	private $utils;

	/** @var AssetFactory */
	private $asset_factory;

	public function __construct(
		PluginUtils $utils,
		AssetFactory $asset_factory
	) {
		$this->utils         = $utils;
		$this->asset_factory = $asset_factory;

		$this->setup();
		$this->setup_theme();

		add_action( 'wp_enqueue_scripts', array( $this, 'setup_assets' ) );
		add_filter( 'excerpt_length', function () {
			return 20;
		} );
		add_filter( 'upload_mimes', array( $this, 'allow_gpx_upload' ) );
		//$this->setup_assets();

		add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			if (strtolower($ext) === 'gpx') {
				$data['ext'] = 'gpx';
				$data['type'] = 'application/gpx+xml';
				$data['proper_filename'] = $filename;
			}
			return $data;
		}, 10, 4);
	}

	public function setup() {
	}

	public function setup_theme() {
		register_theme_directory( $this->utils->get_plugin_path( 'themes' ) );
	}

	public function setup_assets() {
		$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/plugin.js' ), array(
			'in_footer' => true,
		) );

		if ( is_post_type_archive( 'akce' ) ) {
			$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/events.js' ), array(
				'variables'    => array(
					'site_rl'    => site_url(),
					'assets_url' => $this->utils->get_plugin_url( 'assets' ),
				),
				'script_after' => 'console.log("events app loaded")',
				'in_footer'    => true,
			) );
		}
		if ( is_post_type_archive( 'odbory' ) ) {
			$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/departments.js' ), array(
				'variables'    => array(
					'site_rl'    => site_url(),
					'assets_url' => $this->utils->get_plugin_url( 'assets' ),
				),
				'script_after' => 'console.log("departments app loaded")',
				'in_footer'    => true,
			) );
		}
	}

	public function allow_gpx_upload( $mimes ) {
		$mimes['gpx'] = 'application/gpx+xml';

		return $mimes;
	}
}
