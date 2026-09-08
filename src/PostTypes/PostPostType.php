<?php

namespace Kct\PostTypes;

use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\PostType\AbstractBuiltinPostType;

class PostPostType extends AbstractBuiltinPostType {
	const KEY = 'post';

	/** @var CustomFields */
	protected $wcf;

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	public function setup() {
		if ( ! kct_theme_is_active() ) {
			return;
		}

		$this->wcf->create_metabox( array(
			'id'         => 'kct_page_layout',
			'title'      => __( 'Nastavení stránky', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'side',
			'priority'   => 'high',
			'items'      => array(
				array(
					'type'  => 'toggle',
					'id'    => 'short_news',
					'label' => __( 'Krátká aktualita', 'kct' ),
					'desc'  => __( 'U krátké aktuality se ve výpise zobrazí celý obsah a nemá proklik na detail.', 'kct' ),
				),
//				array(
//					'type'  => 'toggle',
//					'id'    => 'hide_sidebar',
//					'label' => __( 'Bez bočního panelu', 'kct' ),
//				),
//				array(
//					'type'  => 'toggle',
//					'id'    => 'no-top-padding',
//					'label' => __( 'Bez horního odsazení', 'kct' ),
//				),
//				array(
//					'type'  => 'toggle',
//					'id'    => 'no-bottom-padding',
//					'label' => __( 'Bez spodního odsazení', 'kct' ),
//				),
			),
		) );
	}

	public function get_post_type_key(): string {
		return self::KEY;
	}
}
