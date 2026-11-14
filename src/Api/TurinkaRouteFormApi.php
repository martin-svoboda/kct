<?php

namespace Kct\Api;

use Kct\Features\Routes;
use Kct\PostTypes\RoadPostType;
use Kct\Turinka\RouteMetabox;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Obsluha formuláře Turinky na obrazovce trasy.
 *
 * Tři věci, které musí proběhnout na serveru: vydání lístku (nese token, který
 * v prohlížeči být nesmí), dočtení uložené trasy (co v Turince vzniklo, ví API,
 * ne rám ve stránce) a odebrání trasy.
 */
class TurinkaRouteFormApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	public function __construct(
		private Routes $routes,
		private RouteMetabox $metabox
	) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$args = array(
			'post_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		foreach ( array( 'form', 'link', 'remove' ) as $action ) {
			register_rest_route(
				$this->namespace,
				'turinka/route-' . $action,
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action ),
						'permission_callback' => array( $this, 'can_edit' ),
						'args'                => $args,
					),
				)
			);
		}
	}

	/**
	 * Smí volající tuhle trasu upravovat?
	 *
	 * Kontroluje se i typ příspěvku: endpoint je o trasách a `edit_post` na
	 * cizí typ by sem nepatřil, i kdyby ho uživatel měl.
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		return $post && RoadPostType::KEY === $post->post_type && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Vydá lístek na formulář.
	 */
	public function form( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->routes->form_url( (int) $request->get_param( 'post_id' ) );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'kct_turinka_form', $result['error'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Dočte trasu po uložení formuláře a vrátí překreslený metabox.
	 */
	public function link( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );
		$result  = $this->routes->link( $post_id );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'kct_turinka_link', $result['error'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response( array( 'metabox' => $this->metabox->html( $post_id ) ), 200 );
	}

	/**
	 * Odebere trasu z Turinky a vrátí překreslený metabox i větu pro redakci.
	 */
	public function remove( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );
		$result  = $this->routes->remove( $post_id );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'kct_turinka_remove', $result['error'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response(
			array(
				'message' => $result['message'],
				'metabox' => $this->metabox->html( $post_id ),
			),
			200
		);
	}
}
