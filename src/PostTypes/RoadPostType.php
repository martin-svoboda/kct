<?php

namespace Kct\PostTypes;

use KctDeps\Wpify\PostType\AbstractCustomPostType;
use KctDeps\Wpify\PostType\PostTypeException;

class RoadPostType extends AbstractCustomPostType {
	const KEY = 'trasa';

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Vlastní pole trasa nemá — data drží Turinka a zadávají se jejím
	 * formulářem. Metabox propojení staví Features\Routes, protože není
	 * z polí, ale ze stavu a tlačítek.
	 */
	public function setup() {
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
		$labels = CzechLabels::build(
			array(
				'rod'   => 'f',
				'nom_j' => 'Trasa',
				'acc_j' => 'trasu',
				'gen_j' => 'trasy',
				'lok_j' => 'trase',
				'nom_m' => 'Trasy',
				'gen_m' => 'tras',
				'acc_m' => 'trasy',
			)
		);

		return array(
			'label'              => $labels['name'],
			'labels'             => $labels,
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
				'excerpt',
				'thumbnail',
				'custom-fields'
			),
		);
	}
}
