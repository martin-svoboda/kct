<?php

namespace Kct\Taxonomies;

use Kct\PostTypes\EventPostType;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\Taxonomy\AbstractCustomTaxonomy;

class PublisherTaxonomy extends AbstractCustomTaxonomy {
	const KEY = 'publisher';

	/** @var CustomFields */
	protected $wcf;

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	public function setup() {
		$this->wcf->create_taxonomy_options( array(
			'taxonomy' => $this->get_taxonomy_key(),
			'items'    => array(
				array(
					'type'  => 'url',
					'id'    => 'url',
					'title' => __( 'URL', 'kct' ),
				),
				array(
					'type'  => 'attachment',
					'id'    => 'logo',
					'title' => __( 'Logo', 'kct' ),
				),
			),
		) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_taxonomy_key(): string {
		return self::KEY;
	}

	/**
	 * @inheritDoc
	 */
	public function get_post_types(): array {
		return array( EventPostType::KEY );
	}

	public function get_args(): array {
		$singular = _x( 'Publisher', 'post type singular name', 'kct' );
		$plural   = _x( 'Publishers', 'post type name', 'kct' );

		return array(
			'labels'            => $this->generate_labels( $singular, $plural ),
			'description'       => __( 'Book publishers are responsible for overseeing the selection, production, marketing and distribution processes involved with new works of writing.', 'kct' ),
			'public'            => true,
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'show_tagcloud'     => false,
		);
	}
}
