<?php

namespace Kct\Features;

use WP_Theme_JSON_Data;

/**
 * Zapne vestavěný lightbox WordPressu pro všechny obrázky a galerie.
 *
 * WordPress umí lightbox sám (blok core/image, Interactivity API) a od WP 7.0
 * v něm jde i proklikávat celou galerii tlačítky Předchozí/Další. Ve výchozím
 * stavu ho ale musí redaktor zapnout u každého bloku zvlášť — volbou
 * „Zvětšit po kliknutí“ v panelu nástrojů bloku. Na to se zapomíná a starší
 * obsah ji uloženou nemá vůbec, takže se obrázky otevírají jen jako holý
 * soubor, nebo vůbec.
 *
 * Nastavení `enabled` v theme.json obrací výchozí hodnotu: lightbox je zapnutý
 * všude, dokud ho někdo u konkrétního bloku ručně nevypne. Zabírá i zpětně na
 * existující příspěvky, protože se nesahá na uložený obsah, jen na výchozí
 * nastavení při vykreslování.
 *
 * Šablona KČT je klasická (bez theme.json), proto se nastavení přidává filtrem.
 */
class Lightbox {
	/**
	 * Hodnoty `linkDestination`, které znamenají „odkaz na soubor média“.
	 *
	 * Editor galerie interně používá 'file', blok obrázku 'media' — v uloženém
	 * obsahu se podle stáří objevují obě.
	 */
	private const MEDIA_LINK_DESTINATIONS = array( 'media', 'file' );

	public function __construct() {
		add_filter( 'wp_theme_json_data_theme', array( $this, 'enable_lightbox' ) );
		add_filter( 'render_block_data', array( $this, 'unwrap_media_links' ) );
	}

	/**
	 * Přidá do dat šablony zapnutý lightbox pro blok core/image.
	 *
	 * `allowEditing` se nenastavuje — jádro ho má ve výchozím stavu zapnuté a
	 * redaktor tak volbu v panelu nástrojů bloku pořád vidí a může ji u
	 * jednotlivého obrázku či galerie přepnout.
	 *
	 * @param WP_Theme_JSON_Data $theme_json Data theme.json aktivní šablony.
	 *
	 * @return WP_Theme_JSON_Data
	 */
	public function enable_lightbox( WP_Theme_JSON_Data $theme_json ): WP_Theme_JSON_Data {
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'blocks' => array(
						'core/image' => array(
							'lightbox' => array( 'enabled' => true ),
						),
					),
				),
			)
		);
	}

	/**
	 * Zruší u obrázků odkaz na vlastní soubor média, aby se místo něj použil
	 * lightbox.
	 *
	 * Starší galerie mají odkaz na soubor uložený v obsahu — dřív to byla
	 * podmínka, aby na ně dosáhl plugin Lightbox with PhotoSwipe. Jádro ale
	 * lightbox nasazuje jen tam, kde obrázek žádný odkaz nemá (viz
	 * `render_block_core_image()`), takže by se takové obrázky po vypnutí
	 * PhotoSwipe otevíraly jako holý soubor v prázdném okně.
	 *
	 * Řeší se to při vykreslování, ne přepsáním databáze: uložený obsah zůstane
	 * beze změny, takže se z toho dá kdykoli couvnout a nehrozí, že by se
	 * hromadnou úpravou rozbil obsah, který nikdo nekontroloval.
	 *
	 * Odkaz na stránku přílohy ani vlastní odkaz se nezahazují — na rozdíl od
	 * odkazu na soubor jsou to skutečné cíle, kam měl návštěvník jít.
	 *
	 * @param array $parsed_block Blok před vykreslením.
	 *
	 * @return array
	 */
	public function unwrap_media_links( $parsed_block ) {
		if ( ! is_array( $parsed_block ) || ! isset( $parsed_block['blockName'] ) ) {
			return $parsed_block;
		}

		// Galerie plněná dynamickým zdrojem nemá obrázky v uloženém obsahu,
		// odkazy jim jádro skládá až při vykreslení podle `linkTo`.
		if ( 'core/gallery' === $parsed_block['blockName'] ) {
			if ( in_array( $parsed_block['attrs']['linkTo'] ?? '', self::MEDIA_LINK_DESTINATIONS, true ) ) {
				$parsed_block['attrs']['linkTo'] = 'lightbox';
			}

			return $parsed_block;
		}

		if ( 'core/image' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		if ( ! in_array( $parsed_block['attrs']['linkDestination'] ?? '', self::MEDIA_LINK_DESTINATIONS, true ) ) {
			return $parsed_block;
		}

		$parsed_block['attrs']['linkDestination'] = 'none';

		// `href` a spol. se ukládají do HTML, ne do atributů bloku; tady jsou
		// jen pro případ, že je někdo doplnil filtrem.
		unset(
			$parsed_block['attrs']['href'],
			$parsed_block['attrs']['rel'],
			$parsed_block['attrs']['linkClass'],
			$parsed_block['attrs']['linkTarget']
		);

		if ( isset( $parsed_block['innerHTML'] ) ) {
			$parsed_block['innerHTML'] = $this->remove_image_anchor( $parsed_block['innerHTML'] );
		}

		if ( ! empty( $parsed_block['innerContent'] ) && is_array( $parsed_block['innerContent'] ) ) {
			foreach ( $parsed_block['innerContent'] as $index => $chunk ) {
				if ( is_string( $chunk ) ) {
					$parsed_block['innerContent'][ $index ] = $this->remove_image_anchor( $chunk );
				}
			}
		}

		return $parsed_block;
	}

	/**
	 * Odstraní `<a>`, který obaluje samotný `<img>`, a nechá obrázek na místě.
	 *
	 * Vzor je záměrně úzký — musí jít o odkaz, který kolem sebe nemá nic než
	 * jeden obrázek. Popisek pod obrázkem ani odkazy jinde v bloku se nedotkne.
	 *
	 * @param string $html HTML bloku obrázku.
	 *
	 * @return string
	 */
	private function remove_image_anchor( string $html ): string {
		if ( ! str_contains( $html, '<a' ) ) {
			return $html;
		}

		return preg_replace( '#<a\b[^>]*>(\s*<img\b[^>]*>\s*)</a>#i', '$1', $html ) ?? $html;
	}
}
