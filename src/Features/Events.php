<?php

namespace Kct\Features;

use Kct\Models\DbEventModel;
use Kct\Models\EventModel;
use Kct\Repositories\DbEventRepository;
use Kct\Repositories\EventRepository;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;
use KctDeps\Wpify\Model\Exceptions\KeyNotFoundException;
use KctDeps\Wpify\Model\Exceptions\PrimaryKeyException;
use KctDeps\Wpify\Model\Exceptions\RepositoryNotInitialized;
use KctDeps\Wpify\Model\Exceptions\SqlException;

class Events {

	public $db;

	public function __construct(
		private DbEventRepository $db_event_repository,
		private EventRepository $event_repository,
		private SettingsRepository $settings
	) {
		global $wpdb;
		$this->db = $wpdb;

		//add_action( 'init', array( $this, 'import_db_events' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

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

		return $query_vars;
	}

	/**
	 * Imports events from a remote database.
	 *
	 * This method retrieves event data from a remote database through an XML API.
	 * The XML is converted to UTF-8 encoding, parsed into a PHP array, and then processed.
	 * The XML URL and filter settings are retrieved from the plugin's settings.
	 *
	 * The method iterates over each event in the XML data and performs the following steps:
	 * - Skips deleted events.
	 * - Skips empty events.
	 * - Filters events based on the specified filter settings.
	 * - Retrieves a saved event or creates a new one.
	 * - Sets the event data based on the XML data.
	 * - Saves the event to the local database.
	 *
	 * @return void
	 */
	public function import_db_events() {
		//$xml = file_get_contents( 'https://akcekct.kct-db.cz/export/akceexport1.php' );
		$xml = file_get_contents( 'https://akcekct.kct-db.cz/export/akceexport1x.php' );
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

		$filter_by  = $this->settings->code_type();

		foreach ( $xml['event'] as $xml_event ) {
			// Skip deleted events
			if ( isset( $xml_event->deleted ) && $xml_event->deleted == 'Y' ) {
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

	}

	/**
	 * Imports the event types from the given URL.
	 *
	 * This method imports the event types from the provided URL. It makes a GET request to the URL
	 * and retrieves an XML file. The XML file is parsed and converted to an associative array using
	 * the `simplexml_load_file()` function. If the XML file is not loaded successfully, the method
	 * returns and no further processing is done.
	 *
	 * The XML data array is dumped using the `dump()` function, and then the script execution is terminated
	 * using the `die()` function. This is done for debugging purposes and can be removed in a production
	 * environment.
	 *
	 * @return void
	 */
	public function import_event_types() {
		$url = "https://akcekct.kct-db.cz/export/akceexport4.php";
		$xml = json_decode( json_encode( simplexml_load_file( $url ) ), true );

		if ( ! $xml ) {
			return;
		}

		dump( $xml );
		die();
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
	public function get_events( $date_from = '', $date_to = '' ): array {
		// Získání všech akcí
		$post_events = $this->event_repository->find_all_published_by_date( $date_from, $date_to );
		$db_events   = $this->db_event_repository->find_all_by_date( $date_from, $date_to );
		$to_exclude  = [];

		// filtr z nastavení
		$filter_val = $this->settings->get_option( 'id_code' );
		$filter_by  = $this->settings->code_type();

		// Data CPT akcí převedeme na array a případně sloučíme se spárovanýni akcemi z DB
		$events = array();
		/** @var EventModel $post_event */
		foreach ( $post_events as $post_event ) {
			$post_data = $post_event->to_array();

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

		return $event;
	}
}
