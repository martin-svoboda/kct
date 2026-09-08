<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

class EventsBlock {
	private $utils;
	private $template;

	public function __construct( PluginUtils $utils, WordPressTemplate $template ) {
		$this->utils    = $utils;
		$this->template = $template;

		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		register_block_type(
			$this->utils->get_plugin_path( 'blocks/events' ),
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	public function render( array $attributes, string $content = '' ) {
		return $this->template->render( 'blocks/events', null, $attributes );
	}
}
