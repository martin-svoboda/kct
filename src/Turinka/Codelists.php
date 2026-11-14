<?php

namespace Kct\Turinka;

/**
 * Číselníky Turinky — seznamy možných hodnot.
 *
 * **Na popisky ve výpisu už nejsou potřeba:** číselníková pole nesou kód
 * i popisek rovnou v odpovědi, viz Turinka\Field. Zůstávají pro místa, kde je
 * potřeba celá nabídka — filtr ve výpisu na webu nebo výběr v editoru — a pro
 * ikony druhů aktivit.
 *
 * Ikony mají druhy aktivit dvě a nejsou zaměnitelné: `event_icon_url` je
 * obrázek z databáze KČT pro akce, `route_icon_url` ikona dlaždice (SVG), která
 * se u trasy kreslí na podklad v barvě `color`.
 *
 * Seznamy se mění zřídka, takže se drží den; vlastní překlad v pluginu by se
 * s Turinkou dřív nebo později rozešel a vyrobil hodnoty, které aplikace nezná.
 */
class Codelists {
	const CACHE_KEY = 'kct_turinka_codelists';

	/** Číselníky se mění zřídka — den je bezpečný kompromis. */
	const TTL = DAY_IN_SECONDS;

	public function __construct(
		private Client $client,
		private TokenStore $tokens
	) {
	}

	/**
	 * Popisek hodnoty, nebo samotná hodnota, když ji číselník nezná.
	 *
	 * Vrací se hodnota, ne prázdno: neznámý klíč je informace („Turinka má
	 * něco nového“), kdežto prázdná buňka vypadá jako chybějící údaj.
	 *
	 * Pro hodnotu z výpisu tahle cesta potřeba není — popisek je přímo v ní
	 * (Turinka\Field). Hodí se, když plugin zná jen kód, třeba z vlastního
	 * nastavení.
	 *
	 * @param string $list  Název sady, např. `difficulty`.
	 * @param mixed  $value Hodnota z výpisu; `{value,label}` i holý klíč.
	 */
	public function label( string $list, $value ): string {
		if ( is_array( $value ) ) {
			$label = (string) ( $value['label'] ?? '' );

			if ( '' !== $label ) {
				return $label;
			}

			$value = (string) ( $value['value'] ?? '' );
		}

		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		$lists = $this->all();

		foreach ( (array) ( $lists[ $list ] ?? array() ) as $item ) {
			if ( (string) ( $item['value'] ?? '' ) === $value ) {
				return (string) ( $item['label'] ?? $value );
			}
		}

		return $value;
	}

	/**
	 * Všechny sady naráz.
	 *
	 * Nepodařilo-li se je stáhnout, vrací prázdno — popisky jsou ozdoba, ne
	 * podmínka. Tabulka pak ukáže klíče a funguje dál.
	 */
	public function all(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! $this->tokens->has_token() ) {
			return array();
		}

		$result = $this->client->codelists( $this->tokens->token() );

		if ( empty( $result['ok'] ) || ! is_array( $result['data'] ) ) {
			return array();
		}

		set_transient( self::CACHE_KEY, $result['data'], self::TTL );

		return $result['data'];
	}
}
