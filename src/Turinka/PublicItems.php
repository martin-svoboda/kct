<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Trasy a místa pro výpis na webu.
 *
 * Nic se neukládá do vlastních tabulek — web je jen zobrazuje a data patří
 * aplikaci. Odpověď proto drží transient: čerstvá platí čtvrt hodiny, poslední
 * známá slouží den jako záloha. **Výpadek Turinky tak neudělá ze stránky
 * prázdné místo**, jen ukáže o něco starší data.
 *
 * Na produkci je navíc stránková cache, takže se API ptá až při jejím
 * obnovení, ne při každém zobrazení stránky.
 *
 * Posílají se jen parametry, které výpis zná — neznámý je 422, ne tiché
 * ignorování.
 */
class PublicItems {
	const CACHE_PREFIX  = 'kct_turinka_list_';
	const BACKUP_SUFFIX = '_backup';

	/** Jak dlouho se odpověď považuje za čerstvou. */
	const TTL = 15 * MINUTE_IN_SECONDS;

	/** Jak dlouho slouží poslední odpověď, když Turinka neodpovídá. */
	const BACKUP_TTL = DAY_IN_SECONDS;

	/** Strop výpisu na stránce. Víc než padesát karet nikdo nečte. */
	const MAX_COUNT = 50;

	public function __construct(
		private Client $client,
		private TokenStore $tokens,
		private SettingsRepository $settings,
		private Config $config
	) {
	}

	public function has_access(): bool {
		return $this->tokens->has_token();
	}

	/**
	 * Položky pro blok.
	 *
	 * @param string $type  `routes` | `places`.
	 * @param array  $args  Nastavení bloku: `count`, `scope`, `order`.
	 *
	 * @return array{items: array<int,array>, ok: bool, message: string}
	 */
	public function listing( string $type, array $args = array() ): array {
		if ( ! in_array( $type, array( 'routes', 'places' ), true ) ) {
			return array( 'items' => array(), 'ok' => false, 'message' => __( 'Neznámý typ obsahu.', 'kct' ) );
		}

		if ( ! $this->tokens->has_token() ) {
			return array( 'items' => array(), 'ok' => false, 'message' => __( 'Web není propojený s Turinkou.', 'kct' ) );
		}

		$query = $this->query( $args );
		$key   = self::CACHE_PREFIX . $type . '_' . md5( (string) wp_json_encode( $query ) );

		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->client->listing( $this->tokens->token(), $type, $query );

		if ( empty( $result['ok'] ) ) {
			$backup = get_transient( $key . self::BACKUP_SUFFIX );

			if ( is_array( $backup ) ) {
				return $backup;
			}

			return array(
				'items'   => array(),
				'ok'      => false,
				'message' => $result['message'] ?: __( 'Turinka neodpověděla.', 'kct' ),
			);
		}

		$data = array(
			'items'   => $this->rebase( (array) ( $result['data'] ?? array() ) ),
			'ok'      => true,
			'message' => '',
		);

		set_transient( $key, $data, self::TTL );
		set_transient( $key . self::BACKUP_SUFFIX, $data, self::BACKUP_TTL );

		return $data;
	}

	/**
	 * Adresy z odpovědi převede na veřejnou adresu aplikace.
	 *
	 * Aplikace skládá absolutní adresy podle toho, přes co na ni web přišel —
	 * lokálně se chodí přímo na kontejner po http, takže by se na stránku
	 * dostaly obrázky s `http://` a prohlížeč by je na https webu zablokoval
	 * jako nezabezpečený obsah. Přepis se týká jen adres na doméně Turinky;
	 * cizí (obrázky z databáze KČT, Wikimedia) zůstávají, jak přišly.
	 */
	private function rebase( array $items ): array {
		array_walk_recursive( $items, function ( &$value ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'http' ) ) {
				$value = $this->config->rebase( $value );
			}
		} );

		return $items;
	}

	/**
	 * Parametry výpisu z nastavení bloku.
	 *
	 * `scope` říká, čí obsah se vypisuje: `mine` jen obsah organizace webu,
	 * `region` celá oblast (u odborového webu jeho oblast), `all` všechno
	 * veřejné. Odbor ani oblast se neposílají ručně — bere se číslo
	 * z nastavení webu, aby se výpis nerozešel s tím, čí web to je.
	 */
	private function query( array $args ): array {
		$count = (int) ( $args['count'] ?? 6 );
		$query = array(
			'per_page' => max( 1, min( self::MAX_COUNT, $count ) ),
			'page'     => 1,
		);

		$code = trim( (string) $this->settings->get_option( 'id_code' ) );
		$type = $this->settings->code_type();

		switch ( (string) ( $args['scope'] ?? 'mine' ) ) {
			case 'region':
				// Oblast zná odbor i oblast: u odboru je to první trojice jeho
				// čísla, u oblasti rovnou její číslo.
				$region = 'department' === $type ? substr( $code, 0, 3 ) : $code;

				if ( '' !== $region ) {
					$query['region'] = $region;
				}
				break;

			case 'all':
				break;

			default:
				$query['mine'] = 1;
		}

		if ( 'routes' === ( $args['item_type'] ?? '' ) && ! empty( $args['difficulty'] ) ) {
			$query['difficulty'] = (string) $args['difficulty'];
		}

		return $query;
	}
}
