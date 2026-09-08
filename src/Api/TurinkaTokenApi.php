<?php

namespace Kct\Api;

use Kct\Turinka\Client;
use Kct\Turinka\Config;
use Kct\Turinka\TokenStore;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Jediné místo, kde Turinka volá plugin — doručení tokenu po schválení žádosti.
 *
 * Cesta je pevná (`/wp-json/kct/v1/turinka-token`), protože si ji Turinka skládá
 * z domény klienta a nezná o webu nic dalšího.
 *
 * Bez autentizace: token je to jediné, čím se volající může prokázat, a ověřuje
 * se až obsahem požadavku. Sdílené tajemství pro podpis neexistuje — před vydáním
 * tokenu není co sdílet.
 */
class TurinkaTokenApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	public function __construct(
		private Config $config,
		private Client $client,
		private TokenStore $tokens
	) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'turinka-token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'receive_token' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Převezme token, nebo doručení zahodí.
	 *
	 * Za převzaté považuje Turinka jen odpověď 2xx s tělem `{"status":"ok"}`,
	 * takže odmítnutí musí vrátit cokoli jiného — jinak by si zapsala doručení,
	 * které se nekonalo, a token by byl nenávratně pryč (vydat nový znamená
	 * zneplatnit tenhle).
	 *
	 * Ověřuje se dvakrát a v tomhle pořadí:
	 *
	 * 1. **Doména v těle je moje.** Zahodí doručení, o které web nežádal, a
	 *    zároveň ušetří odchozí požadavek při každém nesmyslu, který sem přijde.
	 * 2. **Token je pravý.** Pozná se jedině tak, že se s ním Turinky zeptáme,
	 *    komu patří. Odpoví-li 200 a toutéž doménou, poslala ho ona.
	 *
	 * Oprávnění se berou z odpovědi `/me`, ne z těla požadavku — co token smí,
	 * ví Turinka, ne ten, kdo se ozval.
	 */
	public function receive_token( WP_REST_Request $request ): WP_REST_Response {
		$domain = strtolower( sanitize_text_field( (string) $request->get_param( 'domain' ) ) );
		$token  = trim( (string) $request->get_param( 'token' ) );

		if ( '' === $token || $domain !== $this->config->site_domain() ) {
			return $this->reject( __( 'Doručení nepatří tomuto webu.', 'kct' ) );
		}

		$identity = $this->client->me( $token );

		if ( empty( $identity['ok'] ) ) {
			return $this->reject( __( 'Token se nepodařilo ověřit u Turinky.', 'kct' ) );
		}

		if ( strtolower( (string) ( $identity['data']['domain'] ?? '' ) ) !== $domain ) {
			return $this->reject( __( 'Token patří jiné doméně.', 'kct' ) );
		}

		$this->tokens->activate( $token, $identity['data'] );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Odmítnutí popisuje důvod obecně — komu doručení nepatří, tomu není co
	 * vysvětlovat, a přesnější zpráva by jen napovídala, co zkusit příště.
	 */
	private function reject( string $reason ): WP_REST_Response {
		return new WP_REST_Response( array( 'status' => 'rejected', 'reason' => $reason ), 400 );
	}
}
