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

		add_action( 'admin_notices', array( $this, 'settings_notices' ) );
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
			array(
				'title' => __( 'Kód oblasti / odboru', 'kct' ),
				'desc'  => __( 'Zadejte kód vaší oblasti (3 číslice) nebo odboru (6 číslic).', 'kct' ),
				'id'    => 'id_code',
				'type'  => 'number',
			),
		);

//		if ( kct_container()->get( SettingsRepository::class )->code_type() ) {
//			$event_types = get_option('event_types');
//			$event_types_list = [];
//			if ($event_types) {
//				foreach ( $event_types as $event_type ) {
//					$event_types_list[] = sprintf( '<img src="%s" title="%s"/> ', $event_type["icon"], $event_type["name"] );
//				}
//			}
//
//			$schedule_timestamp = wp_next_scheduled( 'kct_update_events' ) ?: __( 'nenaplánovano', 'kct' );
//
//			$settings = array_merge( $settings, array(
//				array(
//					'title' => __( 'Kalendář akcí z centrální DB', 'kct' ),
//					'type'  => 'title',
//				),
//				array(
//					'label' => __( 'Načíst akce z DB KČT', 'kct' ),
//					'desc'  => __( 'Načíst všechny dostupné akce pro váš odbor / oblast z centrální Databáze akcí KČT. (Akce může chvíli trvat.)', 'kct' ),
//					'id'    => 'load_db_events',
//					'type'  => 'button',
//					'url'   => add_query_arg( array( 'kct-action' => 'load_db_events' ), home_url() ),
//				),
//				array(
//					'title' => __( 'Pravidelně aktualizovat akce', 'kct' ),
//					'label' => sprintf( __( 'Pravidelně aktualizovat a načítat nové akce z centrální Databáce akcí KČT. Další aktualizace naplánována na: %s', 'kct' ), is_integer( $schedule_timestamp ) ? date( 'j. n. Y. H:i', $schedule_timestamp ) : __( 'nenaplánovano', 'kct' ) ),
//					'id'    => 'update_db_events',
//					'type'  => 'toggle',
//				),
//				array(
//					'label' => __( 'Načíst tipy akcí z DB KČT', 'kct' ),
//					'desc'  => __( 'Načíst všechny dostupné tipy akcí z centrální Databáze KČT. (Akce může chvíli trvat.)', 'kct' ),
//					'id'    => 'load_db_event_types',
//					'type'  => 'button',
//					'url'   => add_query_arg( array( 'kct-action' => 'load_db_event_types' ), home_url() ),
//				),
//				array(
//					'label' => __( 'Uložené tipy akcí', 'kct' ),
//					'id'    => 'event_types_list',
//					'type'  => 'html',
//					'content'  => implode( ' ', $event_types_list),
//				),
//			) );
//		}

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

	/**
	 * Method to display success updated events notice on the plugin options page.
	 *
	 * @return void
	 */
	public function settings_notices(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] != $this::KEY ) {
			return;
		}

		if ( isset( $_GET['events_loaded'] ) && $_GET['events_loaded'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Načtení akcí z centrální Databáze akcí KČT byla úspěšná.', 'kct' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['eventtypes_loaded'] ) && $_GET['eventtypes_loaded'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php _e( 'Načtení typů akcí z centrální Databáze akcí KČT byla úspěšná.', 'kct' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Method to get the URL of the plugin settings page.
	 *
	 * @return string The URL of the plugin settings page.
	 */
	public function get_settings_url(): string {
		return add_query_arg( [ 'page' => $this::KEY ], admin_url( 'options-general.php' ) );
	}
}
