<?php

namespace Kct\Api;

use Kct\Turinka\Client;
use Kct\Turinka\Config;
use Kct\Turinka\EventRoutes;
use Kct\Turinka\MineItems;
use Kct\Turinka\SourceIds;
use Kct\Turinka\TokenStore;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Obsluha formuláře Turinky nad přehledy tras a míst.
 *
 * Dvě věci musí proběhnout na serveru, a proto tu jsou: **vydání lístku**
 * (nese token, který v prohlížeči být nesmí) a **zahození uložené odpovědi**
 * po uložení formuláře, aby přehled ukázal, co v Turince doopravdy vzniklo.
 *
 * Formulář samotný je stránka aplikace v rámu — plugin ho nestaví, nestahuje
 * číselníky ani nenahrává soubory.
 */
class TurinkaFormApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	/** Typy, pro které se formulář vydává, a jejich jméno v API formulářů. */
	const TYPES = array(
		'routes' => 'route',
		'places' => 'place',
	);

	public function __construct(
		private Client $client,
		private Config $config,
		private TokenStore $tokens,
		private MineItems $mine,
		private SourceIds $ids,
		private EventRoutes $event_routes
	) {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$args = array(
			'type' => array(
				'required'          => true,
				'type'              => 'string',
				'enum'              => array_keys( self::TYPES ),
				'sanitize_callback' => 'sanitize_key',
			),
		);

		register_rest_route(
			$this->namespace,
			'turinka/form',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'form' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $args + array(
						'source_id'     => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'event_post_id' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'turinka/remove',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'remove' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $args + array(
						'source_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'turinka/saved',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saved' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => $args,
				),
			)
		);
	}

	/**
	 * Kdo smí zakládat a upravovat obsah v Turince.
	 *
	 * Stejné právo jako na přehledy, ze kterých se formulář otevírá — kdo smí
	 * psát obsah webu, smí ho dodat i do aplikace jménem organizace.
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_posts' ) && $this->tokens->has_token();
	}

	/**
	 * Vydá lístek na formulář.
	 *
	 * Bez `source_id` je to založení nové položky a identifikátor se vyrobí;
	 * s ním úprava té, kterou web už založil. Režim (`create` / `edit`) určuje
	 * Turinka podle toho, jestli `source_id` zná — plugin jí ho nenapovídá.
	 */
	public function form( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$type      = (string) $request->get_param( 'type' );
		$source_id = trim( (string) $request->get_param( 'source_id' ) );

		if ( '' === $source_id ) {
			$source_id = $this->ids->next( $type );
		}

		$payload = array(
			'type'      => self::TYPES[ $type ],
			'source_id' => $source_id,
		);

		// Trasa zakládaná od akce má vazbu předvyplněnou ve formuláři —
		// redaktor ji tam vidí a plugin po uložení nic nedopisuje.
		$prefill = $this->event_prefill( array(
			'post_id'    => (int) $request->get_param( 'event_post_id' ),
			'turinka_id' => (int) $request->get_param( 'event_turinka_id' ),
		) );

		if ( $prefill ) {
			$payload['prefill'] = $prefill;
		}

		$result = $this->client->form_ticket( $this->tokens->token(), $payload );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'kct_turinka_form',
				$this->failure( $result ),
				array( 'status' => 502 )
			);
		}

		return new WP_REST_Response(
			array(
				// Adresa se přepisuje na veřejnou doménu aplikace: lokálně chodí
				// plugin na kontejner, ale rám ve stránce musí mít adresu, která
				// funguje i v prohlížeči.
				'form_url'  => $this->config->rebase( (string) ( $result['data']['form_url'] ?? '' ) ),
				'mode'      => (string) ( $result['data']['mode'] ?? '' ),
				'source_id' => $source_id,
			),
			200
		);
	}

	/**
	 * Předvyplnění vazby na akci, od které se trasa zakládá.
	 *
	 * Klíč akce dává EventRoutes podle toho, co o ní Turinka ví; tady se jen
	 * převede na tvar, kterému rozumí formulář (jednotné číslo → seznam).
	 *
	 * @param array{post_id: int, turinka_id: int} $ref Odkaz na akci.
	 */
	private function event_prefill( array $ref ): array {
		$post_id = (int) $ref['post_id'];

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return array();
		}

		if ( ! $post_id && ! $ref['turinka_id'] ) {
			return array();
		}

		$state = $this->event_routes->state( $ref );

		if ( empty( $state['editable'] ) || empty( $state['key'] ) ) {
			return array();
		}

		$prefill = array();

		foreach ( $state['key'] as $key => $value ) {
			$prefill[ $key . 's' ] = array( $value );
		}

		return $prefill;
	}

	/**
	 * Odebere z Turinky položku, kterou web dodal.
	 *
	 * Co se s ní v aplikaci stane, rozhoduje Turinka — plugin to jen přetlumočí
	 * redakci. Položka, kterou web založil, se **archivuje, ne maže**: další
	 * zápis se stejným `source_id` ji vrátí i s daty.
	 */
	public function remove( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$type      = (string) $request->get_param( 'type' );
		$source_id = (string) $request->get_param( 'source_id' );

		$result = $this->client->delete_item( $this->tokens->token(), $type, $source_id );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'kct_turinka_remove',
				404 === (int) $result['status']
					? __( 'Turinka tuhle položku nezná — nejspíš už odebraná je.', 'kct' )
					: $this->failure( $result ),
				array( 'status' => 502 )
			);
		}

		$this->mine->forget( $type );

		// Zpráva přežije načtení stránky v transientu per uživatel: tabulka se
		// po odebrání načítá znovu a hláška vypsaná skriptem by s ní zmizela.
		$message = $this->removal_message( (string) ( $result['data']['result'] ?? '' ) );

		set_transient( 'kct_turinka_removed_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );

		return new WP_REST_Response( array( 'message' => $message ), 200 );
	}

	/**
	 * Věta o tom, co se s položkou v Turince stalo.
	 */
	private function removal_message( string $outcome ): string {
		switch ( $outcome ) {
			case 'archived':
				return __( 'Položka je v Turince schovaná. Lidé ji nevidí, ale nesmazala se — když ji založíte znovu, vrátí se i s daty.', 'kct' );
			case 'unlinked':
				return __( 'Web se od položky odpojil. V Turince zůstává, protože ji založil někdo jiný.', 'kct' );
			case 'kept':
				return __( 'Položka v Turince zůstává; web na ni od teď nesahá.', 'kct' );
		}

		return __( 'Položka byla z Turinky odebrána.', 'kct' );
	}

	/**
	 * Po uložení formuláře zahodí uloženou odpověď výpisu.
	 *
	 * Bez toho by přehled ukazoval až deset minut stav před úpravou a redakce
	 * by si myslela, že se uložení nepovedlo.
	 */
	public function saved( WP_REST_Request $request ): WP_REST_Response {
		$this->mine->forget( (string) $request->get_param( 'type' ) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Věta pro redakci z odpovědi, která se nepovedla.
	 */
	private function failure( array $result ): string {
		if ( 0 === (int) $result['status'] ) {
			return $result['message'] ?: __( 'Turinka neodpověděla.', 'kct' );
		}

		if ( 403 === (int) $result['status'] ) {
			return __( 'Turinka formulář nevydala: položka patří někomu jinému, nebo ji správce zablokoval.', 'kct' );
		}

		if ( 429 === (int) $result['status'] ) {
			return __( 'Turinka hlásí příliš mnoho dotazů za minutu. Zkuste to za chvíli.', 'kct' );
		}

		if ( ! empty( $result['errors'] ) ) {
			return implode( ' ', array_map( 'strval', (array) reset( $result['errors'] ) ) );
		}

		return sprintf(
			/* translators: 1: stavový kód odpovědi, 2: popis chyby. */
			__( 'Turinka odpověděla %1$d: %2$s', 'kct' ),
			(int) $result['status'],
			$result['message'] ?: __( 'bez bližšího popisu', 'kct' )
		);
	}
}
