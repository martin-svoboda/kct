<?php

namespace Kct\PostTypes;

use Kct\Taxonomies\PublisherTaxonomy;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\PostType\AbstractCustomPostType;

class EventPostType extends AbstractCustomPostType {
	const KEY = 'akce';

	/** @var CustomFields */
	protected $wcf;

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	public function setup() {
		$this->wcf->create_metabox( array(
			'id'         => 'akce',
			'title'      => __( 'Detail akce', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'advanced',
			'priority'   => 'high',
			'items'      => array(
				array(
					'type'  => 'text',
					'id'    => 'isbn',
					'title' => __( 'ISBN', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'author_name',
					'title' => __( 'Author', 'kct' ),
				),
				array(
					'type'  => 'number',
					'id'    => 'rating',
					'title' => __( 'Rating', 'kct' ),
				),
			),
		) );
	}

	public function get_post_type_key(): string {
		return self::KEY;
	}

	public function get_args(): array {
		$singular = _x( 'Akce', 'post type singular name', 'kct' );
		$plural   = _x( 'Akce', 'post type name', 'kct' );

		return array(
			'label'              => $plural,
			'labels'             => $this->generate_labels( $singular, $plural ),
			'public'             => true,
			'hierarchical'       => false,
			'taxonomies'         => array( PublisherTaxonomy::KEY ),
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'supports'           => array(
				'title',
				'editor',
				'revisions',
				'excerpt',
				'thumbnail',
				'custom-fields'
			),
		);
	}
}
