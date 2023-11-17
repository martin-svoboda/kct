<?php

namespace Kct\Api;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ExampleApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	/** @var string */
	protected $nonce_action = 'wp_rest';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'some-endpoint',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'handle_some_endpoint' ),
					'args'     => array(
						'size' => array(
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/set-app-name',
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'set_app_name' ),
				'args'     => array(
					'nonce' => array(
						'required'          => true,
						'validate_callback' => function ( $nonce ) {
							return wp_verify_nonce( $nonce, $this->nonce_action );
						},
					),
					'name'  => array(
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Add box to cart
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_some_endpoint( $request ) {
		$size = $request->get_param( 'size' );

		return new WP_REST_Response( array( 'size' => $size ), 201 );
	}

	public function set_app_name( $request ) {
		$params = $request->get_params();

		set_transient( 'wpify_app_name', $params['name'] );

		return rest_ensure_response( $params );
	}


	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return bool
	 */
	public function create_item_permissions_check( $request ) {
		return true;
	}


	/**
	 * Prepare the item for the REST response
	 *
	 * @param mixed $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return mixed
	 */
	public function prepare_item_for_response( $item, $request ) {
		return array();
	}
}
