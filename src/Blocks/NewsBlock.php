<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class NewsBlock {
	private $wcf;
	private $template;

	public function __construct( CustomFields $wcf, WordPressTemplate $template ) {
		$this->wcf      = $wcf;
		$this->template = $template;

		if ( ! kct_theme_is_active() ) {
			return;
		}

		$this->setup();
	}

	public function setup() {
		$this->wcf->create_gutenberg_block( array(
			'name'            => 'kct/news',
			'title'           => __( 'Aktuality / Novinky', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'post',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
				array(
					'type'  => 'text',
					'id'    => 'button',
					'title' => __( 'Text tlačítka na archiv', 'kct' ),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		return $this->template->render( 'blocks/news', null, $block_attributes );
	}
}
