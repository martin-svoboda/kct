<?php

namespace Kct\Turinka;

use KctDeps\Wpify\Template\WordPressTemplate;

/**
 * Trasy akce na stránce akce.
 *
 * Vypisují se stejnou kartou jako blok „Trasy z Turinky“ — návštěvník má
 * trasu u akce poznat jako tutéž věc, jakou vidí ve výpisu tras.
 *
 * Data se skládají ze tří dotazů, protože jinak to API nedovolí: výpis tras
 * neumí filtr podle akce, vazby nese až detail akce a detail akce o trase
 * říká jen název a adresu. Teprve detail trasy má to, co karta ukazuje.
 * Dotazy se proto dělají jednou za čtvrt hodiny na akci a výsledek drží
 * transient; poslední známý výpis slouží den jako záloha, aby výpadek Turinky
 * neudělal ze stránky akce prázdné místo.
 *
 * Čtecí API integrací je za tokenem, takže tenhle výpis má jen propojený web.
 * Vkládaný widget akce funguje i bez propojení — ten chodí na veřejné API.
 */
class EventRoutesPublic {
	const CACHE_PREFIX  = 'kct_turinka_event_routes_';
	const BACKUP_SUFFIX = '_backup';

	/** Jak dlouho se výpis považuje za čerstvý. */
	const TTL = 15 * MINUTE_IN_SECONDS;

	/** Jak dlouho slouží poslední výpis, když Turinka neodpovídá. */
	const BACKUP_TTL = DAY_IN_SECONDS;

	/** Předpona uložené dvojice „číslo akce v databázi KČT → id v Turince“. */
	const ID_PREFIX = 'kct_turinka_event_id_';

	/** Id se mění jen se vznikem akce, takže se drží dlouho. */
	const ID_TTL = WEEK_IN_SECONDS;

	/** Akce, kterou Turinka nezná, může přibýt — proto kratší. */
	const ID_MISS_TTL = 6 * HOUR_IN_SECONDS;

	/** Kolik tras u akce se vypíše. Víc než tolik jich akce nemívá. */
	const MAX_ROUTES = 12;

	public function __construct(
		private Client $client,
		private TokenStore $tokens,
		private Config $config,
		private LinkState $link,
		private WordPressTemplate $template
	) {
	}

	/**
	 * Hotové HTML výpisu, nebo prázdný řetězec.
	 *
	 * Prázdný znamená „není co vypsat“ — akce žádnou trasu nemá, Turinka ji
	 * nezná, nebo neodpovídá. Stránka akce na tomhle výpisu nesmí stát.
	 *
	 * @param array $event Akce z Events::get_event().
	 */
	public function html( array $event ): string {
		$routes = $this->routes( $event );

		if ( ! $routes ) {
			return '';
		}

		$list = (string) $this->template->render( 'blocks/turinka-items', null, array(
			'type'    => 'routes',
			'items'   => $routes,
			'ok'      => true,
			'heading' => __( 'Trasy akce', 'kct' ),
		) );

		// Vlastní obal, aby trasy stály na stránce akce jako oddíl vedle
		// propozic a widgetu — se stejnou linkou a stejným odstupem. Výpis
		// samotný je tentýž blok jako ve stránce, a ten si rytmus stránky
		// nese z bloků, ne z akce.
		return sprintf( '<section class="event-routes">%s</section>', $list );
	}

	/**
	 * Trasy akce ve tvaru, jaký čeká karta výpisu.
	 *
	 * @return array<int,array>
	 */
	public function routes( array $event ): array {
		if ( ! $this->tokens->has_token() ) {
			return array();
		}

		$id = $this->event_id( $event );

		if ( ! $id ) {
			return array();
		}

		$key    = self::CACHE_PREFIX . $id;
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$routes = $this->fetch( $id );

		if ( null === $routes ) {
			$backup = get_transient( $key . self::BACKUP_SUFFIX );

			return is_array( $backup ) ? $backup : array();
		}

		set_transient( $key, $routes, self::TTL );
		set_transient( $key . self::BACKUP_SUFFIX, $routes, self::BACKUP_TTL );

		return $routes;
	}

	/**
	 * Zahodí uložený výpis akce — po změně vazeb v administraci.
	 */
	public function forget( int $turinka_id ): void {
		delete_transient( self::CACHE_PREFIX . $turinka_id );
	}

	/**
	 * Trasy z Turinky, nebo null, když se nepovedlo se jí zeptat.
	 *
	 * Rozdíl mezi prázdným polem a null je podstatný: prázdné pole znamená
	 * „akce trasy nemá“ a uloží se, kdežto null je výpadek a sáhne se po
	 * záloze.
	 *
	 * @return array<int,array>|null
	 */
	private function fetch( int $id ): ?array {
		$token  = $this->tokens->token();
		$detail = $this->client->detail( $token, 'events', $id );

		if ( empty( $detail['ok'] ) ) {
			return null;
		}

		$routes = array();

		foreach ( (array) ( $detail['data']['routes'] ?? array() ) as $link ) {
			if ( count( $routes ) >= self::MAX_ROUTES ) {
				break;
			}

			$route_id = (int) ( $link['id'] ?? 0 );

			if ( ! $route_id ) {
				continue;
			}

			$route = $this->client->detail( $token, 'routes', $route_id );

			// Jedna nedostupná trasa neshodí celý výpis: ostatní se vypíšou.
			if ( ! empty( $route['ok'] ) && ! empty( $route['data'] ) ) {
				$routes[] = $this->rebase( (array) $route['data'] );
			}
		}

		return $routes;
	}

	/**
	 * Id akce v Turince.
	 *
	 * U vlastní akce ho plugin zná ze zápisu. U akce z databáze KČT se hledá
	 * podle jejího čísla veřejným čtecím API — stejnou cestou jako slug pro
	 * vkládaný widget, jen se z odpovědi bere id.
	 */
	private function event_id( array $event ): int {
		$post_id = (int) ( $event['id'] ?? 0 );

		if ( $post_id && ! empty( $event['post_type'] ) ) {
			$own = $this->link->turinka_id( $post_id );

			if ( $own ) {
				return $own;
			}
		}

		$kct_id = trim( (string) ( $event['db_id'] ?? '' ) );

		if ( '' === $kct_id || '0' === $kct_id ) {
			return 0;
		}

		$cached = get_transient( self::ID_PREFIX . $kct_id );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$result = $this->client->event_by_kct_id( $kct_id );
		$id     = (int) ( $result['data']['id'] ?? 0 );

		// Nedostupnou Turinku si zapamatovat nesmíme — z výpadku by to udělalo
		// šest hodin bez tras. Ukládá se jen odpověď, kterou opravdu dala.
		if ( ! empty( $result['ok'] ) || 404 === (int) $result['status'] ) {
			set_transient(
				self::ID_PREFIX . $kct_id,
				$id,
				$id ? self::ID_TTL : self::ID_MISS_TTL
			);
		}

		return $id;
	}

	/**
	 * Adresy z odpovědi převede na veřejnou adresu aplikace, ať se na https
	 * web nedostanou obrázky po http. Totéž dělá výpis pro bloky.
	 */
	private function rebase( array $route ): array {
		array_walk_recursive( $route, function ( &$value ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'http' ) ) {
				$value = $this->config->rebase( $value );
			}
		} );

		return $route;
	}
}
