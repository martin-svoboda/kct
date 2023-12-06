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
	private Events $events;

	public function __construct( Events $events ) {
		$this->events = $events;

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
	}


	/**
	 * Retrieves events.
	 *
	 * @return WP_REST_Response The REST response containing the events.
	 */
	public function get_events(): WP_REST_Response {
		return new WP_REST_Response(
			$this->events->get_events(), 200 );
	}
}
