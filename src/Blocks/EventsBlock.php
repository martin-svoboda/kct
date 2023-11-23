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
				array(
					'type'  => 'text',
					'id'    => 'title',
					'title' => __( 'Nadpis', 'kct' ),
				),
			),
		) );
	}

	public function render( array $block_attributes, string $content ) {
		$block_attributes['count'] = 5;
		return $this->template->render( 'blocks/events', null, $block_attributes );
	}
}
