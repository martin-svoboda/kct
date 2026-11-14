<?php

namespace Kct\Turinka;

/**
 * Výškový profil trasy do karty výpisu.
 *
 * Výpis posílá `elevation_profile_mini` — nejvýš třicet nadmořských výšek,
 * právě na tohle. Kreslí se jako malé SVG přímo do stránky: žádný skript,
 * žádný požadavek navíc, a v kartě to funguje i bez JavaScriptu.
 *
 * Je to **ozdoba, ne údaj** — čísla (délka, převýšení) stojí v kartě slovy nad
 * ním, takže profil je pro čtečky skrytý a nenese popisek.
 */
final class Profile {

	/** Rozměry kreslicí plochy; karta si je roztáhne CSS. */
	const WIDTH  = 160;
	const HEIGHT = 36;

	/** Pod tři body nemá křivka co ukázat. */
	const MIN_POINTS = 3;

	/**
	 * SVG profilu, nebo prázdný řetězec, když není z čeho kreslit.
	 *
	 * @param mixed $points Pole nadmořských výšek z výpisu.
	 */
	public static function svg( $points ): string {
		if ( ! is_array( $points ) ) {
			return '';
		}

		$values = array_values( array_map( 'floatval', array_filter( $points, 'is_numeric' ) ) );

		if ( count( $values ) < self::MIN_POINTS ) {
			return '';
		}

		$min   = min( $values );
		$max   = max( $values );
		$range = $max - $min;
		$last  = count( $values ) - 1;

		$coords = array();

		foreach ( $values as $index => $value ) {
			$x = round( $index / $last * self::WIDTH, 1 );

			// Rovina (všechny body stejné) by dělila nulou — kreslí se v půlce
			// výšky, což rovinu vystihuje líp než čára po spodní hraně.
			$y = $range > 0
				? round( self::HEIGHT - ( ( $value - $min ) / $range * self::HEIGHT ), 1 )
				: round( self::HEIGHT / 2, 1 );

			$coords[] = $x . ',' . $y;
		}

		$line = implode( ' ', $coords );
		$area = '0,' . self::HEIGHT . ' ' . $line . ' ' . self::WIDTH . ',' . self::HEIGHT;

		return sprintf(
			'<svg class="turinka-profile" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" focusable="false" role="presentation">'
			. '<polygon class="turinka-profile__area" points="%3$s"></polygon>'
			. '<polyline class="turinka-profile__line" points="%4$s" fill="none"></polyline>'
			. '</svg>',
			self::WIDTH,
			self::HEIGHT,
			esc_attr( $area ),
			esc_attr( $line )
		);
	}
}
