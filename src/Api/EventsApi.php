<?php

namespace Kct\Api;

use Kct\Features\Events;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class EventsApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	/** @var string */
	protected $nonce_action = 'wp_rest';

	public function __construct(
		private Events $events
	) {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'events',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'event-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_event_types' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}


	/**
	 * Retrieves events.
	 *
	 * @return WP_REST_Response The REST response containing the events.
	 */
	public function get_events( WP_REST_Request $request ): WP_REST_Response {
		$date_from = $request->get_param( 'dateFrom' );
		$date_to   = $request->get_param( 'dateTo' );
		$type      = $request->get_param( 'type' );

		return new WP_REST_Response(
			$this->events->get_events( $date_from, $date_to, $type ), 200 );
	}

	/**
	 * Retrieves event types.
	 *
	 * @return WP_REST_Response The REST response containing the event types.
	 */
	public function get_event_types(): WP_REST_Response {
		return new WP_REST_Response( $this->events->get_event_types() );
	}
}
