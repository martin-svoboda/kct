<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class ImageWithContentBlock {
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
			'name'            => 'kct/image-content',
			'title'           => __( 'Obrázek s obsahem vedle', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'align-pull-left',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
				array(
					'type'            => 'attachment',
					'id'              => 'background',
					'title'           => __( 'Obrázek na pozadí', 'kct' ),
					'attachment_type' => 'image',
				),
				array(
					'type'            => 'attachment',
					'id'              => 'image',
					'title'           => __( 'Obrázek do popředí', 'kct' ),
					'attachment_type' => 'image',
				),
				array(
					'id'      => 'image_position',
					'type'    => 'select',
					'label'   => __( 'Pozice obrázku', 'kct' ),
					'options' => array(
						array(
							'value' => 'left',
							'label' => 'Vlevo uvnitř kontejneru'
						),
						array(
							'value' => 'left-absolute',
							'label' => 'Vlevo bez odsazení'
						),
						array(
							'value' => 'right',
							'label' => 'Vpravo uvnitř kontejneru'
						),
						array(
							'value' => 'right-absolute',
							'label' => 'Vpravo bez odsazení'
						)
					),
				),
				array(
					'type'           => 'inner_blocks',
					'id'             => 'content',
					'title'          => __( 'Obsah bloku', 'kct' ),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		$block_attributes['content'] = $content;
		return $this->template->render( 'blocks/image-with-content', null, $block_attributes );
	}
}
