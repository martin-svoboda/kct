<?php

namespace Kct\PostTypes;

use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\PostType\AbstractCustomPostType;
use KctDeps\Wpify\PostType\PostTypeException;

class DepartmentPostType extends AbstractCustomPostType {
	const KEY = 'odbory';

	/** @var CustomFields */
	protected $wcf;

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	public function setup() {
		$items = array(
			array(
				'type'  => 'attachment',
				'id'    => 'logo',
				'title' => __( 'Logo odboru', 'kct' ),
			),
		);

		$this->wcf->create_metabox( array(
			'id'         => 'data',
			'title'      => __( 'Data odboru', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'side',
			'priority'   => 'default',
			'items'      => $items
		) );
	}

	public function get_post_type_key(): string {
		return self::KEY;
	}

	public function register_post_type() {
		if ( ! is_main_site() ) {
			return;
		}

		if ( ! post_type_exists( $this->get_post_type_key() ) ) {
			$this->post_type = register_post_type( $this->get_post_type_key(), $this->get_args() );
		}
		if ( ! $this->post_type || is_wp_error( $this->post_type ) ) {
			throw new PostTypeException( "Cannot create post type " . $this->get_post_type_key() );
		}
	}

	public function get_args(): array {
		$singular = _x( 'Odbor', 'post type singular name', 'kct' );
		$plural   = _x( 'Odbory', 'post type name', 'kct' );

		return array(
			'label'              => $plural,
			'labels'             => $this->generate_labels( $singular, $plural ),
			'public'             => true,
			'hierarchical'       => false,
			//'taxonomies'         => array( EventTypeTaxonomy::KEY ),
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
				//'excerpt',
				'thumbnail',
				'custom-fields'
			),
		);
	}
}
