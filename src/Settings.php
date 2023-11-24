<?php

namespace Kct;

use KctDeps\Wpify\CustomFields\CustomFields;

/**
 * Class Settings
 *
 * @package Wpify\Settings
 */
class Settings {
	/**
	 * @var CustomFields
	 */
	public $wcf;

	/**
	 * @var array
	 */
	public $options = array();

	/**
	 * Option key, and option page slug
	 *
	 * @var string
	 */
	const KEY = 'kct_options';

	public function __construct( CustomFields $wcf ) {
		$this->wcf = $wcf;

		$this->setup();
	}

	public function setup() {
		$this->wcf->create_options_page( array(
			'parent_slug' => 'options-general.php',
			'page_title'  => __( 'Nastavení funkcí KČT', 'kct' ),
			'menu_title'  => __( 'KČT', 'kct' ),
			'menu_slug'   => self::KEY,
			'capability'  => 'manage_options',
			'items'       => array(
				array(
					'id'    => self::KEY,
					'type'  => 'group',
					'items' => array(
						array(
							'id'      => 'add_style',
							'type'    => 'html',
							'content' => '<style>.forminp input[type=number].small-text {width:200px}</style>',
						),
						array(
							'title' => __( 'Kalendář akcí z centrální DB', 'wp-plugin' ),
							'type'  => 'title',
						),
						array(
							'title' => __( 'Načítat akce z DB KČT', 'wp-plugin' ),
							'label' => __( 'Načítat akce z centrální Databáce akcí KČT', 'wp-plugin' ),
							'id'    => 'load_db_events',
							'type'  => 'toggle',
						),
						array(
							'title' => __( 'Kód oblasti / odboru', 'wp-plugin' ),
							'desc' => __( 'Filtrovat akce jen pro konkrétní oblast (3 číslice) nebo odbor (6 číslic). 0 = vše.', 'wp-plugin' ),
							'id'    => 'filter_events_by_department',
							'type'  => 'number',
						),
					),
				),
			),
		) );
	}
}
