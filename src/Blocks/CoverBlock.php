<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class CoverBlock {
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
			'name'            => 'kct/cover',
			'title'           => __( 'Úvodní obrázek', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'cover-image',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
				array(
					'type'            => 'attachment',
					'id'              => 'background',
					'title'           => __( 'Obrázek na pozadí', 'kct' ),
					'attachment_type' => 'image',
				),
				array(
					'type'  => 'text',
					'id'    => 'title',
					'title' => __( 'Nadpis', 'kct' ),
				),
				array(
					'type'  => 'textarea',
					'id'    => 'text',
					'title' => __( 'Text', 'kct' ),
				),
				array(
					'type'  => 'link',
					'id'    => 'link',
					'title' => __( 'Odkaz tlačítka', 'kct' ),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		return $this->template->render( 'blocks/cover', null, $block_attributes );
	}
}
