<?php

namespace Kct\Features;

use Kct\Models\DepartmentModel;
use Kct\Repositories\DepartmentRepository;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;

class Departments {

	public $db;

	public function __construct(
		private DepartmentRepository $department_repository,
		private SettingsRepository $settings
	) {
		global $wpdb;
		$this->db = $wpdb;

//		add_action('init', function (){
//			dump(_get_cron_array());
//		});
//		add_action( 'init', function () {
//			dump( wp_get_schedules() );
//		} );
	}

	/**
	 * Imports departments from a remote database.
	 *
	 * This method retrieves department data from a remote database through an XML API.
	 * The XML is converted to UTF-8 encoding, parsed into a PHP array, and then processed.
	 *
	 * https://www.akcekct.kct-db.cz/export/akceexport2.php Seznam oblastí
	 * https://www.akcekct.kct-db.cz/export/akceexport3.php Seznam odborů
	 *
	 * @param bool $just_updated The flag indicating whether the resource has just been updated
	 *
	 * @return void
	 */
	public function import_departments( $just_updated = false ) {
		$url = 'https://www.akcekct.kct-db.cz/export/akceexport3.php';
		$xml = file_get_contents( $url );
		$xml = mb_convert_encoding( $xml, 'UTF-8' );
		$xml = json_decode( json_encode( simplexml_load_string( $xml ) ), true );

		if ( ! $xml ) {
			return;
		}

		// filtr z nastavení
		$filter_val = $this->settings->get_option( 'id_code' );
		if ( ! $filter_val ) {
			return;
		}

		foreach ( $xml['department'] as $xml_department ) {
			// Skip departments outside region
			if ( $filter_val != $xml_department['region_id'] ) {
				continue;
			}

			// Load saved department or create new
			/** @var DepartmentModel $department */
			$department = $this->department_repository->get_by_department_id( $xml_department['department_id'] );
			if ( is_null( $department ) ) {
				// Skip empty or deleted departments
				if (
					empty( $xml_department['name'] ) || empty( $xml_department['department_id'] ) ||
					( isset( $xml_department['deleted'] ) && $xml_department['deleted'] == 'Y' )
				) {
					continue;
				}

				// Create new post
				$department                = $this->department_repository->create();
				$department->department_id = intval( $xml_department['department_id'] );

			} elseif ( $department->changed && $department->changed == $xml_department['changed'] ) {
				// Skip unchanged departments
				continue;
			}

			// Set data
			if ( isset( $xml_department['deleted'] ) && $xml_department['deleted'] == 'Y' ) {
				$department->deleted     = strval( $xml_department['deleted'] );
				$department->post_status = 'trash';
			} else {
				$department->region_id   = intval( $xml_department['region_id'] ?: 0 );
				$department->title       = strval( $xml_department['name'] ?: '' );
				$department->name        = strval( $xml_department['name'] ?: '' );
				$department->street      = strval( $xml_department['street'] ?: '' );
				$department->zip         = strval( $xml_department['zip'] ?: '' );
				$department->town        = strval( $xml_department['town'] ?: '' );
				$department->state       = strval( $xml_department['state'] ?: '' );
				$department->web         = strval( is_array( $xml_department['web'] ) && ! empty( $xml_department['web'] ) ? $xml_department['web'][0] : $xml_department['web'] );
				$department->phones      = is_array( $xml_department['phone'] ) ? $xml_department['phone'] : [ $xml_department['phone'] ];
				$department->emails      = is_array( $xml_department['email'] ) ? $xml_department['email'] : [ $xml_department['email'] ];
				$department->lng         = floatval( $xml_department['gps_e'] ?: 0 );
				$department->lat         = floatval( $xml_department['gps_n'] ?: 0 );
				$department->post_status = 'publish';
			}
			$department->changed = strval( $xml_department['changed'] ?: '' );

			// Save
			$this->department_repository->save( $department );
		}
	}
}
