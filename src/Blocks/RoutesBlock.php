<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

class RoutesBlock {
	public function __construct(
		private PluginUtils $utils,
		private WordPressTemplate $template
	) {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		register_block_type(
			$this->utils->get_plugin_path( 'blocks/routes' ),
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	public function render( array $attributes, string $content = '' ) {
		return $this->template->render( 'blocks/routes', null, $attributes );
	}
}
