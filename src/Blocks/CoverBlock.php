<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

class CoverBlock {
	private $utils;
	private $template;

	public function __construct( PluginUtils $utils, WordPressTemplate $template ) {
		$this->utils    = $utils;
		$this->template = $template;

		if ( ! kct_theme_is_active() ) {
			return;
		}

		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Nativní registrace bloku z block.json. Atributy čte WP z block.json,
	 * render zůstává na PHP (dynamický blok).
	 */
	public function register() {
		register_block_type(
			$this->utils->get_plugin_path( 'blocks/cover' ),
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	public function render( array $attributes, string $content = '' ) {
		return $this->template->render( 'blocks/cover', null, $attributes );
	}
}
