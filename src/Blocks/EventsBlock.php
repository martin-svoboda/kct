<?php

namespace Kct\Blocks;

use Kct\Plugin;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Template\WordPressTemplate;

class EventsBlock {
	private $wcf;
	private $template;

	public function __construct( CustomFields $wcf, WordPressTemplate $template ) {
		$this->wcf      = $wcf;
		$this->template = $template;

		$this->setup();
	}

	public function setup() {
		$this->wcf->create_gutenberg_block( array(
			'name'            => 'kct/events',
			'title'           => __( 'Kalendář akcí', 'kct' ),
			'category'        => 'kct',
			'icon'            => 'calendar',
			'render_callback' => array( $this, 'render' ),
			'items'           => array(
//				array(
//					'type'  => 'text',
//					'id'    => 'title',
//					'title' => __( 'Nadpis', 'kct' ),
//				),
				array(
					'id'      => 'time_period',
					'type'    => 'select',
					'label'   => __( 'Časové období', 'kct' ),
					'options' => array(
						array(
							'value' => 'future',
							'label' => 'Budoucí'
						),
						array(
							'value' => 'past',
							'label' => 'Minulé'
						)
					),
				),
				array(
					'type'  => 'number',
					'id'    => 'count',
					'title' => __( 'Počet zobrazených akcí', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'button',
					'title' => __( 'Text tlačítka na kalendář akcí', 'kct' ),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		return $this->template->render( 'blocks/events', null, $block_attributes );
	}
}
