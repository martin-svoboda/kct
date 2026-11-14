<?php

namespace Kct\Turinka;

/**
 * Vazby akce na trasy v Turince.
 *
 * Vazbu spravuje **pořadatel akce**: akce musí patřit webu, trasa může být
 * cizí, jen viditelná. Z pohledu redakce to sedí na to, jak o tom přemýšlí —
 * „k mojí akci přidám trasu“ — a plugin díky tomu nemusí rozlišovat, čí trasa
 * to je.
 *
 * Každá vazba se přidává a ruší samostatně (`PUT` / `DELETE` na
 * `route-event-links`). Nahrazovací seznamy (`route_ids`, `event_ids`) tu
 * schválně nepoužíváme: uměly by vynulovat i vazby, které k téže trase udělal
 * jiný pořadatel.
 */
class EventRoutes {

	public function __construct(
		private Client $client,
		private TokenStore $tokens,
		private MineItems $mine,
		private EventState $state,
		private EventRoutesPublic $public
	) {
	}

	/**
	 * Co web o akci ví a co s jejími vazbami smí.
	 *
	 * Akce se pojmenovává **odkazem**, ne id příspěvku: v přehledu akcí jsou
	 * i akce, které příspěvek na webu nemají, a vazby se u nich spravují
	 * stejně dobře. Odkaz je `post_id`, nebo `turinka_id`.
	 *
	 * @param array{post_id?: int, turinka_id?: int} $ref
	 *
	 * @return array{known: bool, editable: bool, id: int, key: array, routes: array<int,array>, message: string}
	 */
	public function state( array $ref ): array {
		$empty = array(
			'known'    => false,
			'editable' => false,
			'id'       => 0,
			'key'      => array(),
			'routes'   => array(),
			'message'  => '',
		);

		if ( ! $this->tokens->has_token() ) {
			return $empty;
		}

		$item = $this->item( $ref );

		if ( ! $item ) {
			return $empty + array();
		}

		$access   = (string) ( $item['access'] ?? '' );
		$editable = in_array( $access, array( 'own', 'managed' ), true );

		return array(
			'known'    => true,
			'editable' => $editable,
			'id'       => (int) ( $item['id'] ?? 0 ),
			'key'      => $this->key( $item ),
			'routes'   => $this->routes( (int) ( $item['id'] ?? 0 ) ),
			'message'  => $editable
				? ''
				: __( 'Vazby na trasy spravuje pořadatel akce — tahle akce webu nepatří.', 'kct' ),
		);
	}

	/**
	 * Přidá vazbu. Opakované volání nevadí, Turinka vrátí 200 místo 201.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function link( array $ref, array $route ): array {
		return $this->write( 'link', $ref, $route );
	}

	/** Zruší vazbu. Vazba, která není, se hlásí jako hotová — výsledek je týž. */
	public function unlink( array $ref, array $route ): array {
		return $this->write( 'unlink', $ref, $route );
	}

	/**
	 * Trasy pro našeptávač.
	 *
	 * Hledá se ve **všech viditelných trasách**, ne jen ve vlastních: k vlastní
	 * akci smí web přivázat i cizí trasu a redakce ji jinak nemá jak najít.
	 *
	 * @return array<int,array{id: int, title: string, meta: string}>
	 */
	public function search( string $query ): array {
		$query = trim( $query );

		if ( mb_strlen( $query ) < 2 || ! $this->tokens->has_token() ) {
			return array();
		}

		$result = $this->client->listing( $this->tokens->token(), 'routes', array(
			'q'        => $query,
			'per_page' => 10,
		) );

		if ( empty( $result['ok'] ) ) {
			return array();
		}

		$found = array();

		foreach ( (array) ( $result['data'] ?? array() ) as $route ) {
			$found[] = array(
				'id'    => (int) ( $route['id'] ?? 0 ),
				'title' => (string) ( $route['title'] ?? '' ),
				'meta'  => $this->route_meta( $route ),
			);
		}

		return $found;
	}

	/**
	 * Údaje o trase na jeden řádek — délka, převýšení, obtížnost.
	 */
	public function route_meta( array $route ): string {
		$parts = array();
		$km    = (float) ( $route['length_km'] ?? 0 );

		if ( $km > 0 ) {
			/* translators: %s: délka trasy v kilometrech. */
			$parts[] = sprintf( __( '%s km', 'kct' ), number_format_i18n( $km, 1 ) );
		}

		$gain = (int) ( $route['elevation_gain_m'] ?? 0 );

		if ( $gain > 0 ) {
			/* translators: %s: převýšení v metrech. */
			$parts[] = sprintf( __( '↑ %s m', 'kct' ), number_format_i18n( $gain ) );
		}

		$difficulty = Field::label( $route['difficulty'] ?? null );

		if ( '' !== $difficulty ) {
			$parts[] = $difficulty;
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Položka akce v Turince, nebo null.
	 *
	 * Hledá se v uložené odpovědi `mine=1` — tam je akce vlastní i ta, kterou
	 * Turinka zná z databáze KČT pod číslem organizace webu.
	 *
	 * @param array{post_id?: int, turinka_id?: int} $ref
	 */
	private function item( array $ref ): ?array {
		$mine       = $this->mine->all( 'events' );
		$post_id    = (int) ( $ref['post_id'] ?? 0 );
		$turinka_id = (int) ( $ref['turinka_id'] ?? 0 );

		if ( $turinka_id ) {
			foreach ( (array) ( $mine['items'] ?? array() ) as $item ) {
				if ( $turinka_id === (int) ( $item['id'] ?? 0 ) ) {
					return $item;
				}
			}

			return null;
		}

		if ( ! $post_id ) {
			return null;
		}

		$db_id = (string) get_post_meta( $post_id, 'db_id', true );

		$by_source = (array) ( $mine['by_source_id'] ?? array() );
		$by_kct    = (array) ( $mine['by_kct_id'] ?? array() );

		if ( isset( $by_source[ (string) $post_id ] ) ) {
			return $by_source[ (string) $post_id ];
		}

		if ( '' !== $db_id && isset( $by_kct[ $db_id ] ) ) {
			return $by_kct[ $db_id ];
		}

		return null;
	}

	/**
	 * Jak akci pojmenovat v požadavku na vazbu.
	 *
	 * Pořadí je dané tím, co je přesnější: vlastní id webu, pak číslo
	 * z databáze KČT, nakonec id v Turince.
	 */
	private function key( array $item ): array {
		$source_id = (string) ( $item['source']['source_id'] ?? '' );

		if ( '' !== $source_id ) {
			return array( 'event_source_id' => $source_id );
		}

		$kct_id = (string) ( $item['kct_id'] ?? '' );

		if ( '' !== $kct_id ) {
			return array( 'event_kct_id' => $kct_id );
		}

		$id = (int) ( $item['id'] ?? 0 );

		return $id ? array( 'event_id' => $id ) : array();
	}

	/**
	 * Trasy navázané na akci. Vazby nese až detail, výpis je nemá.
	 *
	 * @return array<int,array>
	 */
	private function routes( int $id ): array {
		if ( ! $id ) {
			return array();
		}

		$result = $this->client->detail( $this->tokens->token(), 'events', $id );

		return empty( $result['ok'] ) ? array() : (array) ( $result['data']['routes'] ?? array() );
	}

	/**
	 * Společné tělo pro přidání i zrušení vazby.
	 *
	 * @param array $ref   Odkaz na akci: `post_id`, nebo `turinka_id`.
	 * @param array $route Trasa jako `id`, nebo `source_id`.
	 */
	private function write( string $action, array $ref, array $route ): array {
		$state = $this->state( $ref );

		if ( ! $state['known'] ) {
			return array( 'ok' => false, 'message' => __( 'Turinka tuhle akci nezná.', 'kct' ) );
		}

		if ( ! $state['editable'] ) {
			return array( 'ok' => false, 'message' => $state['message'] );
		}

		$link = $state['key'] + $this->route_key( $route );

		if ( count( $link ) < 2 ) {
			return array( 'ok' => false, 'message' => __( 'Chybí trasa nebo akce.', 'kct' ) );
		}

		$result = 'link' === $action
			? $this->client->link_route_event( $this->tokens->token(), $link )
			: $this->client->unlink_route_event( $this->tokens->token(), $link );

		$status = (int) $result['status'];

		// 200 i 201 u přidání, 204 u zrušení; 404 u zrušení znamená, že vazba
		// není — tedy přesně stav, o který redakce žádá.
		if ( in_array( $status, array( 200, 201, 204 ), true ) || ( 'unlink' === $action && 404 === $status ) ) {
			// Výpis tras na stránce akce drží čtvrt hodiny uložený výsledek;
			// po změně vazby by ukazoval stav před ní.
			$this->public->forget( (int) $state['id'] );

			return array( 'ok' => true, 'message' => '' );
		}

		return array( 'ok' => false, 'message' => $this->failure( $result ) );
	}

	/**
	 * @param array $route Trasa jako `id`, nebo `source_id`.
	 */
	private function route_key( array $route ): array {
		$id = (int) ( $route['id'] ?? 0 );

		if ( $id ) {
			return array( 'route_id' => $id );
		}

		$source_id = trim( (string) ( $route['source_id'] ?? '' ) );

		return '' !== $source_id ? array( 'route_source_id' => $source_id ) : array();
	}

	/**
	 * Věta pro redakci z nepovedené odpovědi.
	 *
	 * Text od Turinky má přednost: u vazeb píše, čí akce to je a proč to nejde,
	 * a to je srozumitelnější než cokoli, co bych složil tady.
	 */
	private function failure( array $result ): string {
		$detail = trim( (string) ( $result['body']['detail'] ?? $result['message'] ?? '' ) );

		if ( '' !== $detail ) {
			return $detail;
		}

		if ( 0 === (int) $result['status'] ) {
			return __( 'Turinka neodpověděla.', 'kct' );
		}

		/* translators: %d: stavový kód odpovědi. */
		return sprintf( __( 'Turinka odpověděla %d.', 'kct' ), (int) $result['status'] );
	}
}
