<?php

namespace Kct\Features;

use DateTime;
use Kct\Models\DbEventModel;
use Kct\Models\EventModel;
use Kct\Repositories\DbEventRepository;
use Kct\Repositories\DepartmentRepository;
use Kct\Repositories\EventRepository;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;
use WP_Post;

class Events {

	public $db;

	public function __construct(
		private DbEventRepository $db_event_repository,
		private EventRepository $event_repository,
		private DepartmentRepository $department_repository,
		private SettingsRepository $settings
	) {
		global $wpdb;
		$this->db = $wpdb;

		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		//add_action( 'admin_action_load_db_events', array( $this, 'schedule_import_events' ) );
		//add_action( 'admin_action_load_db_event_types', array( $this, 'import_event_types' ) );
		add_action( 'save_post', array( $this, 'update_start_date' ), 899, 3 );
		add_action( 'save_post', array( $this, 'send_to_main_website' ), 999, 3 );

		add_action( 'kct_import_events', array( $this, 'import_db_events' ) );
		add_action( 'kct_update_events', array( $this, 'schedule_update_events' ) );

		add_action( 'admin_init', function () {
			if ( ! isset( $_REQUEST['kct-action'] ) ) {
				return;
			}

			if ( $_REQUEST['kct-action'] == 'convert-action' ) {
				if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'kct-convert-action' ) ) {
					$this->convert_event( intval( $_REQUEST['db_id'] ) );
				} else {
					die( __( 'Chyba v ověření zabezpečení.', 'kct' ) );
				}
			}

//			if ( get_query_var( 'kct-action' ) == 'load_db_events' ) {
//				//$this->schedule_import_events();
//				$this->import_db_events();
//			}
//			if ( get_query_var( 'kct-action' ) == 'load_db_event_types' ) {
//				$this->import_event_types();
//			}
//			if ( get_query_var( 'kct-action' ) == 'fix-types' ) {
//				$event_types = $this->get_event_types();
//				foreach ( $event_types as $key => $type ) {
//					$event_types[ $key ]['icon'] = str_replace( '.test', '.cz', $type['icon'] );
//				}
//				update_option( 'event_types', $event_types );
//			}

//			wp_safe_redirect( add_query_arg( array(
//				'page' => $this->settings->get_key(),
//			), admin_url( 'options-general.php' ) ), 302, 'kct' );
		} );
//		add_action('init', function (){
//			dump(_get_cron_array());
//		});
//		add_action( 'init', function () {
//			dump( wp_get_schedules() );
//		} );
	}

	/**
	 * Adds a custom rewrite rule for the given database ID.
	 *
	 * This method adds a rewrite rule that matches the URL pattern 'akce-db/([a-z0-9-]+)[/]?$',
	 * and maps it to the query string 'index.php?db_id=$matches[1]'. The rule is added to the top
	 * of the rewrite rules list.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( 'akce-db/([a-z0-9-]+)[/]?$', 'index.php?db_id=$matches[1]', 'top' );
	}

	/**
	 * Adds a custom query variable for the database ID.
	 *
	 * This method adds the 'db_id' query variable to the given array of query variables.
	 * This allows the database ID to be used in the query string of the URL.
	 *
	 * @param array $query_vars An array of query variables.
	 *
	 * @return array The updated array of query variables with 'db_id' added.
	 */
	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'db_id';
		$query_vars[] = 'kct-action';

		return $query_vars;
	}

	/**
	 * Schedule update db events from xml feed if is toggled
	 *
	 * @param $option_name
	 * @param $old_value
	 * @param $value
	 */
	public function maybe_schedule_update_events( $old_value, $value, $option ) {
		if ( $old_value['update_db_events'] === $value['update_db_events'] ) {
			return;
		}

		$timestamp = wp_next_scheduled( 'kct_update_events' );

		if ( $value['update_db_events'] && ! $timestamp ) {
			wp_schedule_event( time(), 'daily', 'kct_update_events' );
		}

		if ( ! $value['update_db_events'] && $timestamp ) {
			wp_unschedule_event( $timestamp, 'kct_update_events' );
		}
	}

	public function schedule_import_events() {
		wp_schedule_single_event( time(), 'kct_import_events' );
	}

	public function schedule_update_events() {
		$this->import_db_events( true );
	}

	/**
	 * Imports events from a remote database.
	 *
	 * This method retrieves event data from a remote database through an XML API.
	 * The XML is converted to UTF-8 encoding, parsed into a PHP array, and then processed.
	 * The XML URL and filter settings are retrieved from the plugin's settings.
	 *
	 * https://www.akcekct.kct-db.cz/export/akceexport1.php jaen změny akcí v poslední době
	 * https://www.akcekct.kct-db.cz/export/akceexport1x.php Všechny dostupné akce
	 * https://www.akcekct.kct-db.cz/export/akceexport2.php Seznam oblastí
	 * https://www.akcekct.kct-db.cz/export/akceexport3.php Seznam odborů
	 * https://www.akcekct.kct-db.cz/export/akceexport4.php typy akcí
	 *
	 * The method iterates over each event in the XML data and performs the following steps:
	 * - Skips deleted events.
	 * - Skips empty events.
	 * - Filters events based on the specified filter settings.
	 * - Retrieves a saved event or creates a new one.
	 * - Sets the event data based on the XML data.
	 * - Saves the event to the local database.
	 *
	 * @param bool $just_updated The flag indicating whether the resource has just been updated
	 *
	 * @return void
	 */
	public function import_db_events( $just_updated = false ) {
		if ( ! is_main_site() ) {
			return;
		}

		$url = 'https://akcekct.kct-db.cz/export/' . ( $just_updated ? 'akceexport1' : 'akceexport1x' ) . '.php';
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

		$filter_by = $this->settings->code_type();
		foreach ( $xml['event'] as $xml_event ) {
			// Skip deleted events
			if ( isset( $xml_event['deleted'] ) && $xml_event['deleted'] == 'Y' ) {
				continue;
			}

			// Skip empty events
			if ( ( empty( $xml_event['name'] ) ) && ( empty( $xml_event['start'] ) ) ) {
				continue;
			}

			if (
				// pokud je nastaven fltr, přeskočit akce jemu neodpovídající
				$filter_by && (
					( 'region' === $filter_by && $filter_val != $xml_event['region'] ) ||
					( 'department' === $filter_by && $filter_val != $xml_event['department'] )
				)
			) {
				continue;
			}

			// Load saved event or crate new
			$db_event = $this->db_event_repository->get_by_db_id( $xml_event['id'] );
			if ( is_null( $db_event ) ) {
				$db_event = $this->db_event_repository->create();
			}

			// Set data
			$db_event->db_id      = intval( $xml_event['id'] );
			$db_event->date       = $xml_event['start']['date'] ?: '';
			$db_event->title      = $xml_event['name'] ?: '';
			$db_event->year       = isset( $xml_event['year'] ) && ! empty( $xml_event['year'] ) ? floatval( $xml_event['year'] ) : 0;
			$db_event->place      = isset( $xml_event['place'] ) && ! empty( $xml_event['place'] ) ? $xml_event['place'] : '';
			$db_event->district   = isset( $xml_event['district'] ) && ! empty( $xml_event['district'] ) ? $xml_event['district'] : '';
			$db_event->web        = isset( $xml_event['web'] ) && ! empty( $xml_event['web'] ) ? $xml_event['web'] : '';
			$db_event->region     = isset( $xml_event['region'] ) && ! empty( $xml_event['region'] ) ? floatval( $xml_event['region'] ) : 0;
			$db_event->department = isset( $xml_event['department'] ) && ! empty( $xml_event['department'] ) ? floatval( $xml_event['department'] ) : 0;
			$db_event->organiser  = isset( $xml_event['organiser'] ) && ! empty( $xml_event['organiser'] ) ? (array) $xml_event['organiser'] : [];
			$db_event->start      = isset( $xml_event['start'] ) && ! empty( $xml_event['start'] ) ? (array) $xml_event['start'] : [];
			$db_event->finish     = isset( $xml_event['finish'] ) && ! empty( $xml_event['finish'] ) ? (array) $xml_event['finish'] : [];
			$db_event->content    = isset( $xml_event['note'] ) && ! empty( $xml_event['note'] ) ? $xml_event['note'] : '';
			$db_event->contact    = isset( $xml_event['contact'] ) && ! empty( $xml_event['contact'] ) ? (array) $xml_event['contact'] : [];
			$db_event->details    = isset( $xml_event['detail'] ) && ! empty( $xml_event['detail'] ) ? (array) $xml_event['detail'] : [];
			$db_event->proposal   = isset( $xml_event['proposal'] ) && ! empty( $xml_event['proposal'] ) ? (array) $xml_event['proposal'] : [];

			$image = array();

			if ( isset( $xml_event['photo'] ) && is_array( $xml_event['photo'] ) ) {
				if ( is_array( reset( $xml_event['photo'] ) ) ) {
					foreach ( $xml_event['photo'] as $photo ) {
						if (
							$photo['mainfoto'] !== 'Y' ||
							! isset( $photo['url'] )
						) {
							continue;
						}

						$image = array(
							'url'    => $photo['url'],
							'author' => $photo['author'] ?? '',
							'title'  => $photo['description'] ?? '',
						);
					}

				} else {
					$image = array(
						'url'    => $xml_event['photo']['url'],
						'author' => $xml_event['photo']['author'] ?? '',
						'title'  => $xml_event['photo']['description'] ?? '',
					);
				}
			}

			$db_event->image = $image;
			// Save
			$this->db_event_repository->save( $db_event );
		}

		// import event types to options
		$this->import_event_types( true );
		exit();
	}

	/**
	 * Imports event types from a remote XML source.
	 *
	 * This method retrieves an XML file from the remote URL "https://akcekct.kct-db.cz/export/akceexport4.php",
	 * converts it to UTF-8 encoding, and parses it as a JSON object.
	 * The XML data is then processed to extract relevant information about event types.
	 *
	 * If the XML data is not available or the processing fails, the method returns early.
	 *
	 * For each event type in the XML, the method creates a subdirectory named "imagesakce" under the WordPress
	 * uploads directory, if it doesn't already exist. The method then downloads the icon file associated with
	 * the event type and saves it to the created subdirectory. If the download and save are successful, the
	 * file path in the uploads directory is stored as the icon URL. Otherwise, the original icon URL from
	 * the XML is used.
	 *
	 * Finally, the method updates the "event_types" option in the WordPress options table with the new
	 * event types array.
	 *
	 * @return void
	 */
	public function import_event_types() {
		$url = "https://akcekct.kct-db.cz/export/akceexport4.php";
		$xml = file_get_contents( $url );
		$xml = mb_convert_encoding( $xml, 'UTF-8' );
		$xml = json_decode( json_encode( simplexml_load_string( $xml ) ), true );

		if ( $xml ) {

			$event_types = [];
			foreach ( $xml['detail'] as $xml_detail ) {

				// Skip deleted
				if ( ( isset( $xml_detail['deleted'] ) && $xml_detail['deleted'] == 'Y' ) || ! isset( $xml_detail['icon'] ) ) {
					continue;
				}

				// create subdir if not exist
				$subdir = 'imagesakce';
				$dir    = wp_upload_dir()['basedir'] . '/' . $subdir . '/';
				if ( ! file_exists( $dir ) ) {
					mkdir( $dir, 0755, true );
				}

				// Get icon file data
				$file_url   = $xml_detail['icon'];
				$image_data = file_get_contents( $file_url );
				$file_name  = basename( $file_url );

				// Save icon file to the uploads directory
				$save_result = false;
				if ( $image_data !== false ) {
					$file_path   = $dir . $file_name;
					$save_result = file_put_contents( $file_path, $image_data );
				}

				// set event type data
				$event_types[ $xml_detail['detailid'] ] = array(
					'detailid' => $xml_detail['detailid'],
					'name'     => $xml_detail['name'],
					'icon'     => $save_result ? wp_upload_dir()['baseurl'] . '/' . $subdir . '/' . $file_name : $xml_detail['icon'],
					'char'     => $xml_detail['char'],
					'weight'   => $xml_detail['weight'],
				);
			}

			// update options
			update_option( 'event_types', $event_types );
		}

		exit();
	}

	/**
	 * Retrieves all events within the specified date range.
	 *
	 * This method retrieves all events by calling the `find_all_published_by_date` method on the
	 * `$event_repository` object for custom post type events, and the `find_all_by_date` method on
	 * the `$db_event_repository` object for events from the database. The retrieved events are then
	 * filtered based on the settings, and merged into a single array.
	 *
	 * @param string $date_from The start date of the range. Default is an empty string.
	 * @param string $date_to   The end date of the range. Default is an empty string.
	 *
	 * @return array An array of events within the specified date range.
	 */
	public function get_events( $date_from = '', $date_to = '', $type = '', $department = '' ): array {
		// Získání všech akcí
		$post_events = $department ? [] : $this->event_repository->find_all_published_by_date( $date_from, $date_to, $type );
		$db_events   = $this->db_event_repository->find_all_by_date( $date_from, $date_to, $type );
		$to_exclude  = [];

		// filtr z nastavení
		$filter_val = $this->settings->get_option( 'id_code' );
		$filter_by  = $this->settings->code_type();

		if ( $department ) {
			$filter_val = $department;
			$filter_by  = 'department';
		}

		// Data CPT akcí převedeme na array a případně sloučíme se spárovanýni akcemi z DB
		$events = array();
		/** @var EventModel $post_event */
		foreach ( $post_events as $post_event ) {
			$post_data = $post_event->to_array();

			$post_data['details'] = $this->merge_event_details_data( $post_data['details'] );

			if ( $post_event->db_id ) {
				$event_db      = $this->db_event_repository->get_by_db_id( (int) $post_event->db_id );
				$event_db_data = $event_db->to_array();
				$event         = [];
				foreach ( $post_data as $key => $value ) {
					$event[ $key ] = ! empty( $value ) ? $value : ( $event_db_data[ $key ] ?? null );
				}
				$events[]     = $event;
				$to_exclude[] = $event_db->db_id;
			} else {
				$events[] = $post_data;
			}
		}

		// Data zbylích DB akcí převedeme na array a přidáme do pole akcí
		if ( $db_events ) {
			/** @var DbEventModel $db_event */
			foreach ( $db_events as $db_event ) {
				if (
					// Přeskočit akce sloučené s CPT
					in_array( $db_event->db_id, $to_exclude ) ||
					// pokud je nastaven fltr, přeskočit akce jemu neodpovídající
					$filter_by && (
						( 'region' === $filter_by && $filter_val != $db_event->region ) ||
						( 'department' === $filter_by && $filter_val != $db_event->department )
					)
				) {
					continue;
				}

				$events[] = $db_event->to_array();
			}
		}

// Akce seřadíme podle data
		usort( $events, fn( $a, $b ) => strtotime( $a['date'] ) - strtotime( $b['date'] ) );

		return $events;
	}

	/**
	 * Retrieves event data based on the provided ID and database ID.
	 *
	 * This method retrieves event data from the custom post type if the ID is valid
	 * and the post type matches. It also retrieves event data from the database event
	 * if the database ID is provided. The retrieved data is merged together into a single
	 * array and returned.
	 *
	 * @param int    $id    The event ID.
	 * @param string $bd_id The database ID.
	 *
	 * @return array The event data array.
	 */
	public function get_event( $id, $bd_id ) {
		$post_data     = [];
		$event_db_data = [];

		// získání dat z custom post type pokud jsou
		if ( $id && get_post_type( $id ) == $this->event_repository->post_type() ) {
			$post      = $this->event_repository->get( $id );
			$post_data = $post->to_array();
			$bd_id     = empty( $bd_id ) && ! empty( $post->db_id ) ? $post->db_id : $bd_id;

			$post_data['details'] = $this->merge_event_details_data( $post_data['details'] );
		}

		// Získání dat z databázové akce pokud jsou
		if ( $bd_id ) {
			$event_db      = $this->db_event_repository->get_by_db_id( (int) $bd_id );
			$event_db_data = $event_db->to_array();
		}


		// Sloučení dat
		$event = [];
		if ( $post_data && $event_db_data ) {
			foreach ( $post_data as $key => $value ) {
				if ( in_array( $key, array( 'start', 'finish' ) ) && empty( $value['date'] ) ) {
					$value = null;
				}

				$event[ $key ] = ! empty( $value ) ? $value : ( $event_db_data[ $key ] ?? null );
			}
		} else {
			$event = $post_data ?: $event_db_data;
		}

//		foreach ( $event as $data ) {
//			if ()
//		}

		return $event;
	}

	public function get_event_types() {
		$event_types = get_option( 'event_types' );

		if ( empty( $event_types ) && is_multisite() ) {
			switch_to_blog( 1 );
			$event_types = get_option( 'event_types' );
			restore_current_blog();
		}

		return (array) $event_types;
	}

	public function merge_event_details_data( $post_details ) {
		$event_types = $this->get_event_types();
		$new_details = [];

		if ( ! $post_details || empty( $event_types ) ) {
			return $post_details;
		}

		foreach ( $post_details as $detail ) {
			foreach ( $event_types as $type ) {
				if ( $detail['detailid'] == $type['detailid'] ) {
					// Sloučení detailu akce s uloženými informacemi z options
					$new_detail    = array_merge( $detail, $type );
					$new_details[] = $new_detail;
					break;
				}
			}
		}

		return $new_details;
	}

	/**
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 *
	 * @return void
	 */
	function update_start_date( $post_id, $post, $update ) {
		if ( $post->post_type !== $this->event_repository->post_type() ) {
			return;
		}

		// Načtení meta hodnoty "start" pro aktualizovaný nebo nově vytvořený příspěvek
		$start_data = get_post_meta( $post_id, 'start', true );

		// Získání data začátku akce ze serializovaného pole
		$start_date = isset( $start_data['date'] ) ? $start_data['date'] : '';

		// Aktualizace hodnoty "start_date"
		if ( ! empty( $start_date ) ) {
			update_post_meta( $post_id, 'date', $start_date );
		}
	}

	/**
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @param bool    $update
	 *
	 * @return void
	 */
	function send_to_main_website( $post_id, $post, $update ) {
		if ( $post->post_type !== $this->event_repository->post_type() ) {
			return;
		}

		$event = $this->event_repository->get( $post_id );

		if (
			! $event->main_page_connection
			|| ! $event->main_page_connection['connect']
		) {
			return;
		}

		$department       = $this->settings->get_option( 'id_code' );
		$db_event_id      = intval( $department * 10000 + $event->id );
		$current_home_url = home_url();
		$image_id         = $event->get_featured_image_id();
		$organiser        = array( 'name' => '' );
		$image            = array();

		if ( $image_id ) {
			$image = array(
				'url'    => get_attachment_link( $image_id ),
				'author' => '',
				'title'  => $event->title,
			);
		}


		// Load saved event or crate new
		$db_event = $this->db_event_repository->get_by_db_id( $db_event_id );

		switch_to_blog( 1 );

		if ( is_null( $db_event ) ) {
			$db_event = $this->db_event_repository->create();
		}

		$main_department = $this->department_repository->get_by_department_id( $department );

		restore_current_blog();

		if ( $main_department ) {
			$organiser['name'] = $main_department->title;
			$organiser['web']  = $current_home_url;
		}

		// Set data
		$db_event->db_id      = intval( $db_event_id );
		$db_event->date       = $event->date;
		$db_event->title      = $event->title;
		$db_event->year       = $event->year;
		$db_event->place      = $event->place;
		$db_event->district   = $event->district;
		$db_event->web        = $event->permalink;
		$db_event->region     = substr( $department, 0, 3 );
		$db_event->department = $department;
		$db_event->organiser  = $organiser;
		$db_event->start      = $event->start;
		$db_event->finish     = $event->finish;
		$db_event->content    = $event->main_page_connection['promo_text'];
		$db_event->contact    = $event->contact;
		$db_event->details    = $this->merge_event_details_data( $event->details );
		$db_event->proposal   = $event->proposal;
		$db_event->image      = $image;

		// Save
		switch_to_blog( 1 );
		$this->db_event_repository->save( $db_event );
		restore_current_blog();
	}

	private function convert_event( $db_id ) {
		/** @var EventModel $event */
		$event = $this->event_repository->create();
		/** @var DbEventModel $db_event */
		$db_event = $this->db_event_repository->get_by_db_id( $db_id );

		$event->db_id = $db_id;
		$event->title = $db_event->title;
		$event->date  = $db_event->date;

		if ( $db_event->year ) {
			$event->slug = sanitize_title( $db_event->year . ' ' . $db_event->title );
		}

		$this->event_repository->save( $event );

		$url = get_edit_post_link( $event->id );
		if ( ! $url ) {
			$url = add_query_arg( array(
				'post'   => $event->id,
				'action' => 'edit',
			), admin_url( 'post.php' ) );
		}

		wp_safe_redirect( $url, 302, 'kct' );
	}
}
