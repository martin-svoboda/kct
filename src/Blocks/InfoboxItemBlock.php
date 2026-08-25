<?php

namespace Kct\Blocks;

use KctDeps\Wpify\PluginUtils\PluginUtils;
use KctDeps\Wpify\Template\WordPressTemplate;

class InfoboxItemBlock {
	private $utils;
	private $template;

	public function __construct( PluginUtils $utils, WordPressTemplate $template ) {
		$this->utils    = $utils;
		$this->template = $template;

		if ( ! kct_theme_is_active() ) {
			return;
		}

		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'register_pattern' ) );
	}

	public function register() {
		register_block_type(
			$this->utils->get_plugin_path( 'blocks/infobox-item' ),
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Pattern „Info karty" – core sloupce se třemi kartami na jedno kliknutí.
	 */
	public function register_pattern() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category( 'kct', array( 'label' => __( 'KČT', 'kct' ) ) );
		}

		$card    = '<!-- wp:kct/infobox-item /-->';
		$column  = '<!-- wp:column --><div class="wp-block-column">' . $card . '</div><!-- /wp:column -->';
		$content = '<!-- wp:columns {"className":"is-style-kct-cards"} -->'
			. '<div class="wp-block-columns is-style-kct-cards">' . str_repeat( $column, 3 ) . '</div>'
			. '<!-- /wp:columns -->';

		register_block_pattern( 'kct/info-cards', array(
			'title'       => __( 'Info karty', 'kct' ),
			'description' => __( 'Sloupce se třemi info kartami.', 'kct' ),
			'categories'  => array( 'kct' ),
			'content'     => $content,
		) );
	}

	public function render( array $attributes, string $content = '' ) {
		return $this->template->render( 'blocks/infobox-item', null, $attributes );
	}
}
