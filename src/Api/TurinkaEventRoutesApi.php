<?php

namespace Kct\Api;

use Kct\PostTypes\EventPostType;
use Kct\Turinka\EventRoutes;
use Kct\Turinka\EventRoutesPanel;
use Kct\Turinka\MineItems;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Vazby akce na trasy z obrazovky akce.
 *
 * Všechno jde přes server, protože token nesmí do prohlížeče. Odpověď vrací
 * rovnou překreslený panel — tím se stav na obrazovce nikdy nerozejde s tím,
 * co o akci říká Turinka.
 */
class TurinkaEventRoutesApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	public function __construct(
		private EventRoutes $routes,
		private EventRoutesPanel $panel,
		private MineItems $mine
	) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// Akce se pojmenovává příspěvkem, nebo id v Turince — v přehledu akcí
		// jsou i akce, které na webu příspěvek nemají.
		$event = array(
			'post_id'    => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'turinka_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		$route = array(
			'route_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		register_rest_route(
			$this->namespace,
			'turinka/event-routes/search',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'search' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $event + array(
						'q' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		foreach ( array( 'link', 'unlink' ) as $action ) {
			register_rest_route(
				$this->namespace,
				'turinka/event-routes/' . $action,
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, $action ),
						'permission_callback' => array( $this, 'can_edit' ),
						'args'                => $event + $route,
					),
				)
			);
		}

		register_rest_route(
			$this->namespace,
			'turinka/event-routes/panel',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'panel' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $event + array(
						'obnovit' => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Smí volající tuhle akci upravovat?
	 *
	 * U akce s příspěvkem rozhoduje právo na ten příspěvek — a kontroluje se
	 * i typ, protože endpoint je o akcích a právo na cizí typ sem nepatří.
	 * U akce bez příspěvku není co kontrolovat na webu, tak platí totéž právo
	 * jako na přehled akcí, odkud se panel otevírá; čí akce to v Turince je,
	 * si pohlídá EventRoutes.
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( $post_id ) {
			$post = get_post( $post_id );

			return $post && EventPostType::KEY === $post->post_type && current_user_can( 'edit_post', $post_id );
		}

		return (int) $request->get_param( 'turinka_id' ) > 0 && current_user_can( 'edit_posts' );
	}

	public function search( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			array( 'routes' => $this->routes->search( (string) $request->get_param( 'q' ) ) ),
			200
		);
	}

	public function link( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->change( 'link', $request );
	}

	public function unlink( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->change( 'unlink', $request );
	}

	/**
	 * Vykreslení panelu.
	 *
	 * Volá se po uložení formuláře Turinky a při prvním otevření panelu
	 * v přehledu akcí — tam se obsah načítá až na vyžádání, aby stránka
	 * s padesáti řádky nevolala Turinku padesátkrát.
	 */
	public function panel( WP_REST_Request $request ): WP_REST_Response {
		// Formulář mohl akci v Turince teprve založit, takže uložený výpis
		// `mine=1` je po něm zastaralý.
		if ( $request->get_param( 'obnovit' ) ) {
			$this->mine->forget( 'events' );
		}

		return new WP_REST_Response( array( 'panel' => $this->panel->html( $this->ref( $request ) ) ), 200 );
	}

	/**
	 * Odkaz na akci z požadavku.
	 *
	 * @return array{post_id: int, turinka_id: int}
	 */
	private function ref( WP_REST_Request $request ): array {
		return array(
			'post_id'    => (int) $request->get_param( 'post_id' ),
			'turinka_id' => (int) $request->get_param( 'turinka_id' ),
		);
	}

	private function change( string $action, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ref   = $this->ref( $request );
		$route = array( 'id' => (int) $request->get_param( 'route_id' ) );

		$result = 'link' === $action
			? $this->routes->link( $ref, $route )
			: $this->routes->unlink( $ref, $route );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'kct_turinka_event_routes', $result['message'], array( 'status' => 502 ) );
		}

		return new WP_REST_Response( array( 'panel' => $this->panel->html( $ref ) ), 200 );
	}
}
