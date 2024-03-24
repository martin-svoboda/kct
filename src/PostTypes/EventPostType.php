<?php

namespace Kct\PostTypes;

use Kct\Repositories\EventRepository;
use Kct\Taxonomies\EventTypeTaxonomy;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\PostType\AbstractCustomPostType;

class EventPostType extends AbstractCustomPostType {
	const KEY = 'akce';

	/** @var CustomFields */
	protected $wcf;

	public function __construct(
		CustomFields $wcf,
		private EventRepository $event_repository
	) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	public function setup() {
		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
			return;
		}

		$items = array(
			array(
				'type'  => 'number',
				'id'    => 'db_id',
				'title' => __( 'ID akce z kct-db', 'kct' ),
				'desc'  => __( 'ID akce z centrální Databáze turistických akcí KČT pro spárování dat ("0" pokud akce není vedená v centrální databázi).', 'kct' ),
			),
		);

		$event = null;
		if ( isset( $_GET['post'] ) ) {
			$event = $this->event_repository->get( $_GET['post'] );
		}

		if ( $event && $event->db_id ) {
			$items = array_merge( $items, array(
				array(
					'type'    => 'html',
					'id'      => 'db_info',
					'content' => __( '<p style="color: red">Veškerá data o akci se načítají z databáze akcí. Pokud některá data chcete upravit, tak to prosím udělejte tam.</p><p>Zde tedy jen upravujte vlastní obsah, případně náhledový obrázek, pro vaši akci.</p>', 'kct' ),
				),
			) );
		} else {
			$items = array_merge( $items, array(
				array(
					'type'  => 'number',
					'id'    => 'year',
					'title' => __( 'Ročník', 'kct' ),
					'desc'  => __( 'Pokud se jedná o pravidelnou akci, můžete uvést ročník (nepovinné).', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'place',
					'title' => __( 'Místo akce', 'kct' ),
					'desc'  => __( 'Obecné uvedení místa kde se akce koná (nepovinné).', 'kct' ),
				),
				array(
					'type'  => 'text',
					'id'    => 'district',
					'title' => __( 'Okres', 'kct' ),
					'desc'  => __( 'Okres místa kde se akce koná (nepovinné).', 'kct' ),
				),
				array(
					'id'    => 'start',
					'type'  => 'group',
					'title' => __( 'Start', 'kct' ),
					'items' => array(
						array(
							'type'              => 'date',
							'id'                => 'date',
							'title'             => __( 'Datum startu <span style="color:red">(vyžadováno)</span>', 'kct' ),
							'desc'              => __( 'Bez uvedení data startu se akce nezobrazí ve výpise akcí.', 'kct' ),
							'custom_attributes' => array( 'required' => 'required' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'time',
							'title' => __( 'Čas startu', 'kct' ),
							'desc'  => __( 'Libovolný údaj pro upřesnění času startu (nepovinné).', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'place',
							'title' => __( 'Místo startu', 'kct' ),
							'desc'  => __( 'Libovolný údaj pro upřesnění místa startu (nepovinné).', 'kct' ),
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
							'desc'  => __( 'Datum cíle (nepovinné - v případě nevyplnění se použije datum startu).', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'time',
							'title' => __( 'Čas cíle', 'kct' ),
							'desc'  => __( 'Libovolný údaj pro upřesnění času cíle (nepovinné).', 'kct' ),
						),
						array(
							'type'  => 'text',
							'id'    => 'place',
							'title' => __( 'Místo cíle', 'kct' ),
							'desc'  => __( 'Libovolný údaj pro upřesnění místa cíle (nepovinné).', 'kct' ),
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
							'type'    => 'select',
							'id'      => 'detailid',
							'title'   => __( 'Typ akce', 'kct' ),
							'options' => $this->get_post_type_options(),
						),
						array(
							'type'  => 'text',
							'id'    => 'km',
							'title' => __( 'Trasa (kilometry)', 'kct' ),
						),
					)
				),
			) );
		}

		$this->wcf->create_metabox( array(
			'id'         => 'data',
			'title'      => __( 'Data akce', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'advanced',
			'priority'   => 'high',
			'items'      => $items
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

	public function get_post_type_options() {
		// Načteme typy akcí z hlavního webu
		switch_to_blog( 1 );
		$event_types = get_option( 'event_types' );
		restore_current_blog();

		if ( ! $event_types ) {
			return [];
		}

		$options = [];
		foreach ( $event_types as $event_type ) {
			$options[] = [
				'label' => $event_type['name'],
				'value' => $event_type['detailid'],
			];
		}

		return $options;

	}
}
