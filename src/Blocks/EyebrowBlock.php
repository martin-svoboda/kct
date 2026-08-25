<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;

class EyebrowBlock {
	private $utils;

	public function __construct( PluginUtils $utils ) {
		$this->utils = $utils;

		if ( ! kct_theme_is_active() ) {
			return;
		}

		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Statický blok — render zajišťuje JS (save), žádný render_callback.
	 */
	public function register() {
		register_block_type( $this->utils->get_plugin_path( 'blocks/eyebrow' ) );
	}
}
