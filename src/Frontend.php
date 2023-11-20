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
		$this->setup_assets();
	}

	public function setup() {
		//add_action( 'wp_body_open', array( $this, 'print_plugin_info' ) );
	}

	public function setup_theme() {
		register_theme_directory( $this->utils->get_plugin_path( 'themes' ) );
	}

	public function setup_assets() {
//		$this->asset_factory->url( 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js?ver=LK12' );
//		$this->asset_factory->url( 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/css/bootstrap.min.css' );
//		$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/plugin.js' ), array(
//			'variables'     => array( 'test_plugin' => array( 'aaa' ) ),
//			'script_before' => 'console.log("script before plugin")',
//			'script_after'  => 'console.log("script after plugin")',
//		) );
		$this->asset_factory->wp_script( $this->utils->get_plugin_path( 'build/plugin.css' ) );
//		$this->asset_factory->theme( 'style.css' );
//		$this->asset_factory->parent_theme( 'style.css' );
//		$this->asset_factory->url( $this->utils->get_plugin_url( 'js/test.js' ) );
	}

	public function print_plugin_info() {
		echo '<pre style="font-size: 10px;background-color: rgba(0, 0, 0, 0.5); color: white;">';
		var_dump( array(
			'name'        => $this->utils->get_plugin_name(),
			'version'     => $this->utils->get_plugin_version(),
			'description' => $this->utils->get_plugin_description(),
			'basename'    => $this->utils->get_plugin_basename(),
			'slug'        => $this->utils->get_plugin_slug(),
			'text_domain' => $this->utils->get_text_domain(),
		) );

		echo '</pre>';
	}
}
