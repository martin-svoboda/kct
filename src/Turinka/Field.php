<?php

namespace Kct\Turinka;

/**
 * Čtení číselníkových polí z odpovědí Turinky.
 *
 * Pole jako `status`, `visibility`, `difficulty` nebo `category` nesou kód
 * i popisek: `{"value":"easy","label":"Snadná"}`. Kód je pro logiku (porovnání,
 * filtry), popisek k zobrazení — a bere se z API, protože vlastní překlad by se
 * s aplikací dřív nebo později rozešel.
 *
 * Dřív tatáž pole chodila jako holý řetězec. Obojí se tu čte stejně, takže
 * plugin nespadne ani na starší instanci aplikace a nedělá z pole `Array`.
 */
final class Field {

	/**
	 * Kód hodnoty — to, co se porovnává a posílá zpátky při zápisu.
	 *
	 * @param mixed $value
	 */
	public static function code( $value ): string {
		if ( is_array( $value ) ) {
			return (string) ( $value['value'] ?? '' );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Popisek pro člověka. Když ho odpověď nenese, vrací se kód — neznámá
	 * hodnota je informace, kdežto prázdná buňka vypadá jako chybějící údaj.
	 *
	 * @param mixed $value
	 */
	public static function label( $value ): string {
		if ( is_array( $value ) ) {
			$label = (string) ( $value['label'] ?? '' );

			return '' !== $label ? $label : (string) ( $value['value'] ?? '' );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Popisky seznamu hodnot, třeba `suitability` nebo `activity_types`.
	 *
	 * @param mixed $values
	 *
	 * @return string[]
	 */
	public static function labels( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		// Jedna hodnota přijde jako `{value,label}`, seznam jako pole takových
		// polí — rozezná se podle toho, jestli má klíč `value`.
		if ( isset( $values['value'] ) || isset( $values['label'] ) ) {
			$label = self::label( $values );

			return '' !== $label ? array( $label ) : array();
		}

		$labels = array();

		foreach ( $values as $value ) {
			$label = self::label( $value );

			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		return $labels;
	}
}
