<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class ActionBlock {
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
			'name'            => 'kct/action',
			'title'           => __( 'CTA blok', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'migrate',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
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
					'type'    => 'toggle',
					'id'      => 'gradient',
					'title'   => __( 'S horním prolnutím', 'kct' ),
					'default' => true,
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		return $this->template->render( 'blocks/action', null, $block_attributes );
	}
}
