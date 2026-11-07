<?php

namespace Kct\PostTypes;

use Kct\Help\DocsLinks;
use KctDeps\Wpify\CustomFields\CustomFields;
use KctDeps\Wpify\PostType\AbstractCustomPostType;
use KctDeps\Wpify\PostType\PostTypeException;

class DepartmentPostType extends AbstractCustomPostType {
	const KEY = 'odbory';

	/** @var CustomFields */
	protected $wcf;

	public function __construct( CustomFields $wcf, private DocsLinks $docs ) {
		$this->wcf = $wcf;

		parent::__construct();
	}

	/**
	 * Pole odboru.
	 *
	 * Všechna data odboru jsou editovatelná, i u odborů založených importem.
	 * Na rozdíl od akcí se u nich nic nezamyká: XML export z centrální
	 * databáze KČT je mrtvý, takže „opravte to v centrální databázi“ už není
	 * kam poslat — jediné místo, kde se údaje odboru dají opravit, je tenhle
	 * formulář.
	 *
	 * Pole jsou naschvál plochá (žádná `group`), protože každé z nich odpovídá
	 * jednomu metapoli v DepartmentModel — skupina by je uložila zanořeně pod
	 * jeden klíč a model by je přestal vidět.
	 */
	public function setup() {
		$items = array(
			array(
				'type'    => 'html',
				'id'      => 'kct_department_docs',
				'content' => sprintf(
					'<p style="margin:0 0 8px">%s</p>',
					$this->docs->inline_link( 'department_data', __( 'Jak se vyplňuje odbor', 'kct' ) )
				),
			),
			array(
				'type'  => 'number',
				'id'    => 'department_id',
				'title' => __( 'Číslo odboru', 'kct' ),
				'desc'  => __( 'Registrační číslo odboru v KČT. Zobrazuje se v záhlaví stránky odboru a ve výpisu.', 'kct' ),
			),
			array(
				'type'  => 'number',
				'id'    => 'region_id',
				'title' => __( 'Číslo oblasti', 'kct' ),
				'desc'  => __( 'Číslo oblasti, pod kterou odbor patří.', 'kct' ),
			),
			array(
				'type'    => 'html',
				'id'      => 'kct_department_address_title',
				'content' => sprintf( '<h4 style="margin:16px 0 0">%s</h4>', esc_html__( 'Adresa', 'kct' ) ),
			),
			array(
				'type'  => 'text',
				'id'    => 'street',
				'title' => __( 'Ulice a číslo popisné', 'kct' ),
			),
			array(
				'type'  => 'text',
				'id'    => 'zip',
				'title' => __( 'PSČ', 'kct' ),
			),
			array(
				'type'  => 'text',
				'id'    => 'town',
				'title' => __( 'Obec', 'kct' ),
			),
			array(
				'type'  => 'text',
				'id'    => 'state',
				'title' => __( 'Stát', 'kct' ),
			),
			array(
				'type'    => 'html',
				'id'      => 'kct_department_contact_title',
				'content' => sprintf( '<h4 style="margin:16px 0 0">%s</h4>', esc_html__( 'Kontakty', 'kct' ) ),
			),
			array(
				'type'    => 'multi_tel',
				'id'      => 'phones',
				'title'   => __( 'Telefony', 'kct' ),
				'desc'    => __( 'Vypíšou se v bočním sloupci stránky odboru jako odkazy k vytočení.', 'kct' ),
				'buttons' => array( 'add' => __( 'Přidat telefon', 'kct' ) ),
			),
			array(
				'type'    => 'multi_email',
				'id'      => 'emails',
				'title'   => __( 'E-maily', 'kct' ),
				'desc'    => __( 'Na webu se vypisují skrytě, aby je nesbírali roboti.', 'kct' ),
				'buttons' => array( 'add' => __( 'Přidat e-mail', 'kct' ) ),
			),
			array(
				'type'  => 'url',
				'id'    => 'web',
				'title' => __( 'Web odboru', 'kct' ),
				'desc'  => __( 'Adresa včetně https://. Zobrazí se jako tlačítko „Web odboru“.', 'kct' ),
			),
			array(
				'type'    => 'html',
				'id'      => 'kct_department_gps_title',
				'content' => sprintf( '<h4 style="margin:16px 0 0">%s</h4>', esc_html__( 'Poloha', 'kct' ) ),
			),
			array(
				'type'              => 'number',
				'id'                => 'lat',
				'title'             => __( 'Zeměpisná šířka', 'kct' ),
				'desc'              => __( 'Desetinné číslo, např. 50.2306. Bez obou souřadnic se mapka u odboru nezobrazí.', 'kct' ),
				'custom_attributes' => array( 'step' => 'any' ),
			),
			array(
				'type'              => 'number',
				'id'                => 'lng',
				'title'             => __( 'Zeměpisná délka', 'kct' ),
				'desc'              => __( 'Desetinné číslo, např. 14.0875.', 'kct' ),
				'custom_attributes' => array( 'step' => 'any' ),
			),
		);

		$this->wcf->create_metabox( array(
			'id'         => 'data',
			'title'      => __( 'Data odboru', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'advanced',
			'priority'   => 'high',
			'items'      => $items
		) );

		$this->wcf->create_metabox( array(
			'id'         => 'logo',
			'title'      => __( 'Logo odboru', 'kct' ),
			'post_types' => array( $this->get_post_type_key() ),
			'context'    => 'side',
			'priority'   => 'default',
			'items'      => array(
				array(
					'type'  => 'attachment',
					'id'    => 'logo',
					'title' => __( 'Logo odboru', 'kct' ),
				),
			)
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
		$labels = CzechLabels::build(
			array(
				'rod'   => 'm',
				'nom_j' => 'Odbor',
				'acc_j' => 'odbor',
				'gen_j' => 'odboru',
				'lok_j' => 'odboru',
				'nom_m' => 'Odbory',
				'gen_m' => 'odborů',
				'acc_m' => 'odbory',
			)
		);

		return array(
			'label'              => $labels['name'],
			'labels'             => $labels,
			'menu_icon'          => 'dashicons-groups',
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
