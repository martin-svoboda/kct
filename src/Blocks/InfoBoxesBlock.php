<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class InfoBoxesBlock {
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
			'name'            => 'kct/infobox',
			'title'           => __( 'Info boxy', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'info',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
				array(
					'id'    => 'boxes',
					'type'  => 'multi_group',
					'title' => 'Karty',
					'items' => array(

						array(
							'type'            => 'attachment',
							'id'              => 'image',
							'title'           => __( 'Obrázek / ikona', 'kct' ),
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
						array(
							'type'    => 'select',
							'id'      => 'color',
							'title'   => __( 'Barva boxu', 'kct' ),
							'options' => array(
								array(
									'value' => '--primary-color',
									'label' => __( 'Primární barva', 'kct' ),
								),
								array(
									'value' => '--secondary-color',
									'label' => __( 'Sekundární barva', 'kct' ),
								),
								array(
									'value' => '--red-color',
									'label' => __( 'Červená', 'kct' ),
								),
								array(
									'value' => '--blue-color',
									'label' => __( 'Modrá', 'kct' ),
								),
								array(
									'value' => '--green-color',
									'label' => __( 'Zelená', 'kct' ),
								),
								array(
									'value' => '--yellow-color',
									'label' => __( 'Žlutá', 'kct' ),
								),
							)
						),
					),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		return $this->template->render( 'blocks/infoboxes', null, $block_attributes );
	}
}
