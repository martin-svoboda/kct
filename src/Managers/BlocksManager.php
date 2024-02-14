<?php

namespace Kct\Managers;

use Kct\Blocks\ActionBlock;
use Kct\Blocks\CoverBlock;
use Kct\Blocks\EventsBlock;
use Kct\Blocks\InfoBoxesBlock;
use Kct\Blocks\NewsBlock;
use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;

final class BlocksManager {
	private $utils;
	private $asset_factory;

	public function __construct(
		PluginUtils $utils,
		AssetFactory $asset_factory,
		CoverBlock $cover_block,
		ActionBlock $action_block,
		InfoBoxesBlock $info_boxes_block,
		EventsBlock $events_block,
		NewsBlock $news_block
	) {
		$this->utils         = $utils;
		$this->asset_factory = $asset_factory;

		$this->setup();
	}

	public function setup() {
		add_action( 'after_setup_theme', array( $this, 'editor_styles' ) );
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
			add_filter( 'block_categories_all', array( $this, 'block_categories' ) );
		} else {
			add_filter( 'block_categories', array( $this, 'block_categories' ) );
		}

		$this->asset_factory->admin_wp_script( $this->utils->get_plugin_path( 'build/block-editor.js' ) );
	}

	public function editor_styles() {
		add_theme_support( 'editor-styles' );
		add_theme_support( 'dark-editor-style' );
		add_editor_style( 'editor-style.css' );
	}

	public function block_categories( $categories ) {
		array_splice( $categories, 1, 0, array(
			array(
				'slug'  => 'kct',
				'title' => __( 'KČT', 'kct' ),
				'icon'  => null,
			)
		) );

		return $categories;
	}
}
