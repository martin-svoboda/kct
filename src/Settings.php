<?php

namespace Kct;

use Kct\Repositories\SettingsRepository;
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

	/**
	 * Method to set up the plugin options page.
	 *
	 * @return void
	 */
	public function setup() {

		$settings = array(
			array(
				'id'      => 'add_style',
				'type'    => 'html',
				'content' => '<style>.forminp input[type=number].small-text {width:200px}</style>',
			),
//			array(
//				'title'   => __( 'Typ webu a fukcí', 'kct' ),
//				'desc'    => __( 'Vyberte typ webu pro jaký budou uzpůsobeny funkce pluginu a šablony.', 'kct' ),
//				'id'      => 'site_type',
//				'type'    => 'select',
//				'options' => array(
//					'region'     => __( 'Oblast', 'kct' ),
//					'department' => __( 'Odbor', 'kct' ),
//				),
//			),
			array(
				'title' => __( 'Kód oblasti / odboru', 'kct' ),
				'desc'  => __( 'Zadejte kód vaší oblasti (3 číslice) nebo odboru (6 číslic).', 'kct' ),
				'id'    => 'id_code',
				'type'  => 'number',
			),
		);

		if ( $this->code_type() ) {
			$settings = array_merge( $settings, array(
				array(
					'title' => __( 'Kalendář akcí z centrální DB', 'kct' ),
					'type'  => 'title',
				),
				array(
					'title' => __( 'Načítat akce z DB KČT', 'kct' ),
					'label' => __( 'Načítat akce z centrální Databáce akcí KČT', 'kct' ),
					'id'    => 'load_db_events',
					'type'  => 'toggle',
				),
			) );
		}

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
					'items' => $settings,
				),
			),
		) );
	}
}
