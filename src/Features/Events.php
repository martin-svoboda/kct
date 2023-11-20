<?php

namespace Kct\Features;

use Kct\Models\DbEventModel;
use Kct\Repositories\DbEventRepository;
use Kct\Repositories\EventRepository;
use KctDeps\Wpify\Model\Exceptions\KeyNotFoundException;
use KctDeps\Wpify\Model\Exceptions\PrimaryKeyException;
use KctDeps\Wpify\Model\Exceptions\RepositoryNotInitialized;
use KctDeps\Wpify\Model\Exceptions\SqlException;

class Events {

	public function __construct(
		private DbEventRepository $db_event_repository,
		private EventRepository $event_repository
	) {
//		add_action( 'init', array( $this, 'import_db_events' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

	}

	public function add_rewrite_rules() {
		add_rewrite_rule( 'akce-db/([a-z0-9-]+)[/]?$', 'index.php?db_id=$matches[1]', 'top' );
	}

	public function add_query_vars( $query_vars ) {
		$query_vars[] = 'db_id';

		return $query_vars;
	}

	/**
	 * Import events from KČT DB
	 *
	 * @return void
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function import_db_events() {
		$xml = file_get_contents( 'https://akcekct.kct-db.cz/export/akceexport1.php' );
		//$xml = file_get_contents( 'https://akcekct.kct-db.cz/export/akceexport1x.php' );
		$xml = mb_convert_encoding( $xml, 'UTF-8' );
		$xml = json_decode( json_encode( simplexml_load_string( $xml ) ), true );

		if ( ! $xml ) {
			return;
		}

		foreach ( $xml['event'] as $xml_event ) {
			// Skip deleted events
			if ( isset( $xml_event->deleted ) && $xml_event->deleted == 'Y' ) {
				continue;
			}

			// Skip empty events
			if ( ( empty( $xml_event['name'] ) ) && ( empty( $xml_event['start'] ) ) ) {
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
				foreach ( $xml_event['photo'] as $photo ) {
					if ( $photo['mainfoto'] !== 'Y' || ! isset( $photo['url'] ) ) {
						continue;
					}
					$image = array(
						'url'    => $photo['url'],
						'author' => $photo['author'] ?? '',
						'title'  => $photo['description'] ?? '',
					);
				}
			}

			$db_event->image = $image;
			// Save
			$this->db_event_repository->save( $db_event );
		}

	}

	/**
	 * Import event types from KČT DB
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
	 * Get all events
	 *
	 * @return array
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function get_events(): array {
		$db_events = $this->db_event_repository->find_all();

		$events = array();
		/** @var DbEventModel $db_event */
		foreach ( $db_events as $key => $db_event ) {
			$events[ $key ] = $db_event->to_array();
		}

		usort( $events, fn( $a, $b ) => strtotime( $a['date'] ) - strtotime( $b['date'] ) );

		return $events;
	}

	/**
	 * Get all events
	 *
	 * @return array
	 * @throws KeyNotFoundException
	 * @throws PrimaryKeyException
	 * @throws RepositoryNotInitialized
	 * @throws SqlException
	 * @throws \ReflectionException
	 */
	public function get_event( $id, $bd_id ) {
		var_dump( [ $id, $bd_id ] );

		$post_data     = [];
		$event_db_data = [];
		if ( $id ) {
			$post      = $this->event_repository->get( $id );
			$post_data = $post->to_array();
			$bd_id     = ! $bd_id && $post->db_id ? $post->db_id : 0;
			dump( $post->title );
			dump( $post_data );
		}
		if ( $bd_id ) {
			$event_db      = $this->db_event_repository->get_by_db_id( (int) $bd_id );
			$event_db_data = $event_db->to_array();
		}
		//dump($event);
		echo '<h2>AA</h2>';

		return array_merge( $event_db_data, $post_data );
	}
}
