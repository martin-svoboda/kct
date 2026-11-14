<?php

namespace Kct\Turinka;

/**
 * Čtení obrázků z odpovědí Turinky.
 *
 * Obrázek přichází jako objekt: `url`, případně `variants` se zmenšeninami
 * a `attribution` u snímků z cizích zdrojů. Adresy jsou absolutní, takže se
 * nic nelepí — vybírá se jen velikost.
 *
 * Výběr velikosti není kosmetika: mapa trasy má v plné velikosti přes megabajt
 * a do karty široké čtyři sta bodů se posílat nemá.
 */
final class Image {

	/** Velikosti od nejmenší, jak je výpis nabízí. */
	const SIZES = array( 'thumbnail', 'small', 'medium', 'large' );

	/**
	 * Adresa obrázku v požadované velikosti, nebo prázdný řetězec.
	 *
	 * Když žádanou velikost odpověď nenese, bere se nejbližší větší a nakonec
	 * originál — raději větší soubor než prázdné místo v kartě.
	 *
	 * @param mixed  $image Obrázek z odpovědi; zvládne i holou adresu.
	 * @param string $size  Jedna z SIZES.
	 */
	public static function url( $image, string $size = 'medium' ): string {
		if ( is_string( $image ) ) {
			return $image;
		}

		if ( ! is_array( $image ) ) {
			return '';
		}

		$variants = (array) ( $image['variants'] ?? array() );
		$from     = array_search( $size, self::SIZES, true );

		if ( false !== $from ) {
			foreach ( array_slice( self::SIZES, (int) $from ) as $candidate ) {
				if ( ! empty( $variants[ $candidate ] ) ) {
					return (string) $variants[ $candidate ];
				}
			}
		}

		return (string) ( $image['url'] ?? '' );
	}

	/**
	 * Uvedení zdroje, které si snímek nese.
	 *
	 * Vypisuje se, kdykoli ho obrázek má — u fotek z Wikimedia je to podmínka
	 * licence, ne ozdoba.
	 *
	 * @param mixed $image
	 */
	public static function attribution( $image ): string {
		return is_array( $image ) ? trim( (string) ( $image['attribution'] ?? '' ) ) : '';
	}
}
