<?php

namespace Kct\PostTypes;

use Kct\Taxonomies\EventTypeTaxonomy;
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
			'id'         => 'data',
			'title'      => __( 'Data akce', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'advanced',
			'priority'   => 'high',
			'items'      => array(
				array(
					'type'  => 'number',
					'id'    => 'db_id',
					'title' => __( 'ID akce z kct-db', 'kct' ),
					'desc'  => __( 'ID akce z centrální Databáze turistických akcí KČT pro spárování dat', 'kct' ),
				),
				array(
					'type'  => 'number',
					'id'    => 'year',
					'title' => __( 'Ročník', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'place',
					'title' => __( 'Místo akce', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'district',
					'title' => __( 'Okres', 'kct' ),
				),
				array(
					'id'    => 'start',
					'type'  => 'group',
					'title' => __( 'Start', 'kct' ),
					'items' => array(
						array(
							'type'  => 'date',
							'id'    => 'date',
							'title' => __( 'Datum startu', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'time',
							'title' => __( 'Čas startu', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'place',
							'title' => __( 'Místo startu', 'kct' ),
						),
					)
				),
				array(
					'id'    => 'finish',
					'type'  => 'group',
					'title' => __( 'Cíl', 'kct' ),
					'items' => array(
						array(
							'type'  => 'date',
							'id'    => 'date',
							'title' => __( 'Datum cíle', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'time',
							'title' => __( 'Čas cíle', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'place',
							'title' => __( 'Místo cíle', 'kct' ),
						),
					)
				),
				array(
					'id'      => 'contact',
					'type'    => 'multi_group',
					'title'   => __( 'Kontakty', 'kct' ),
					'buttons' => array( 'add' => __( 'Přidat kontakt', 'kct' ) ),
					'items'   => array(
						array(
							'type'  => 'text',
							'id'    => 'person',
							'title' => __( 'Osoba (Jméno a příjmení)', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'address',
							'title' => __( 'Adresa', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'phone',
							'title' => __( 'Telefon', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'email',
							'title' => __( 'E-mail', 'kct' ),
						),
					)
				),
				array(
					'id'      => 'details',
					'type'    => 'multi_group',
					'title'   => __( 'Detaily (tipy) akce', 'kct' ),
					'buttons' => array( 'add' => __( 'Přidat typ akce', 'kct' ) ),
					'items'   => array(
						array(
							'type'  => 'select',
							'id'    => 'type',
							'title' => __( 'Typ akce', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'km',
							'title' => __( 'Trasa (kilometry)', 'kct' ),
						),
					)
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
			'taxonomies'         => array( EventTypeTaxonomy::KEY ),
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

	public function get_post_type_options() {
		$event_types = get_option( 'event_types' );

		if ( ! $event_types ) {
			return [];
		}

		$options = [];
		foreach ( $event_types as $id => $event_type ) {
			$options[] = [$id => $event_type['name'] ];
		}

		return $options;

	}
}
