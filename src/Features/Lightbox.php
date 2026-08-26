<?php

namespace Kct\Features;

use WP_Block;
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
		add_filter( 'render_block_data', array( $this, 'prepare_block' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
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
	 * Vrátí tlačítko lupy do rohu náhledu v oříznuté galerii.
	 *
	 * Jádro tlačítko umisťuje inline stylem a u obrázků se `scale` nastaveným
	 * na `contain` počítá s tím, že se obrázek v náhledu nevyplňuje celý —
	 * odsadí ho proto dovnitř, do místa, kde by končila fotografie. V oříznuté
	 * galerii ale náhled vyplněný je (`is-cropped` mu dává `object-fit: cover`),
	 * takže by tlačítko viselo uprostřed buňky.
	 *
	 * `!important` je tu nutnost, ne nedbalost: přebíjí se inline styl.
	 *
	 * @see Lightbox::keep_cropped_image_whole()
	 */
	public function enqueue_styles(): void {
		wp_register_style( 'kct-lightbox', false, array(), null );
		wp_enqueue_style( 'kct-lightbox' );
		wp_add_inline_style(
			'kct-lightbox',
			'.wp-block-gallery.is-cropped .wp-lightbox-container > button{top:16px !important;right:16px !important}'
		);
	}

	/**
	 * Upraví blok před vykreslením tak, aby na něm lightbox fungoval.
	 *
	 * Řeší se to při vykreslování, ne přepsáním databáze: uložený obsah zůstane
	 * beze změny, takže se z toho dá kdykoli couvnout a nehrozí, že by se
	 * hromadnou úpravou rozbil obsah, který nikdo nekontroloval.
	 *
	 * @param array         $parsed_block Blok před vykreslením.
	 * @param array         $source_block Blok před úpravami ostatních filtrů.
	 * @param WP_Block|null $parent_block Nadřazený blok, u obrázku v galerii ta galerie.
	 *
	 * @return array
	 */
	public function prepare_block( $parsed_block, $source_block = array(), $parent_block = null ) {
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

		$parsed_block = $this->keep_cropped_image_whole( $parsed_block, $parent_block );

		if ( ! in_array( $parsed_block['attrs']['linkDestination'] ?? '', self::MEDIA_LINK_DESTINATIONS, true ) ) {
			return $parsed_block;
		}

		// Starší galerie mají odkaz na soubor uložený v obsahu — dřív to byla
		// podmínka, aby na ně dosáhl plugin Lightbox with PhotoSwipe. Jádro ale
		// lightbox nasazuje jen tam, kde obrázek žádný odkaz nemá (viz
		// `render_block_core_image()`), takže by se takové obrázky po vypnutí
		// PhotoSwipe otevíraly jako holý soubor v prázdném okně.
		//
		// Odkaz na stránku přílohy ani vlastní odkaz se nezahazují — na rozdíl
		// od odkazu na soubor jsou to skutečné cíle, kam měl návštěvník jít.
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
	 * Zajistí, že se obrázek z oříznuté galerie otevře v lightboxu celý.
	 *
	 * Volba „Oříznout obrázky na míru“ sjednotí náhledy v mřížce tím, že jim
	 * dá `object-fit: cover` — obrázek na šířku se v buňce na výšku ořízne.
	 * To je u náhledu žádoucí, po rozkliknutí ale ne.
	 *
	 * Jádro počítá velikost okna lightboxu z poměru stran *náhledu*, ne
	 * fotografie (`setOverlayStyles()` ve `block-library/image/view.js`), a
	 * protože na zvětšeninu pak natvrdo nasazuje `object-fit: cover`, ořízne
	 * ji úplně stejně jako náhled. Výjimku má jen pro obrázky s `scale`
	 * nastaveným na `contain` — u těch si rozměry přepočítá podle skutečné
	 * fotografie a okno vyjde ve správném poměru.
	 *
	 * Atribut `scale` se u bloku obrázku na serveru nikde jinde nepoužívá než
	 * právě pro tenhle výpočet (viz `block_core_image_render_lightbox()`),
	 * takže nastavením nic dalšího nerozbijeme — náhled v mřížce zůstává
	 * oříznutý, mění se jen chování zvětšeniny.
	 *
	 * @param array         $parsed_block Blok obrázku.
	 * @param WP_Block|null $parent_block Nadřazený blok.
	 *
	 * @return array
	 */
	private function keep_cropped_image_whole( array $parsed_block, $parent_block ): array {
		// Vlastní hodnotu od redaktora nepřebíjíme — ten výplň řeší záměrně,
		// typicky spolu s pevným poměrem stran obrázku.
		if ( isset( $parsed_block['attrs']['scale'] ) ) {
			return $parsed_block;
		}

		if ( ! $parent_block instanceof WP_Block || 'core/gallery' !== $parent_block->name ) {
			return $parsed_block;
		}

		// `imageCrop` je ve výchozím stavu zapnuté, takže galerie bez uloženého
		// atributu je oříznutá.
		if ( ! ( $parent_block->attributes['imageCrop'] ?? true ) ) {
			return $parsed_block;
		}

		$parsed_block['attrs']['scale'] = 'contain';

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
