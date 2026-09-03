<?php

namespace Kct\Help;

/**
 * Jediný zdroj pravdy o tom, kam v administraci odkazovat na nápovědu.
 *
 * Drží základní adresu a mapu „obrazovka → odkazy“. Záměrně nezná žádné hooky
 * WordPressu — skládá jen adresy a značky, takže se dá číst i použít odkudkoli
 * (záložka Nápovědy, pole nastavení, metabox, Přizpůsobení).
 *
 * Adresy vedou na konkrétní stránku, ne na rozcestník. Smyslem je uspořit
 * hledání; odkaz na úvodní stránku by uživatele posunul jen o krok.
 */
class DocsLinks {

	/**
	 * Výchozí adresa nápovědy.
	 *
	 * Přebít jde konstantou KCT_DOCS_URL ve wp-config.php nebo filtrem
	 * `kct_docs_url`. Web provozovaný mimo síť KČT tak nemusí odkazovat na
	 * doménu, kterou jeho správce nijak neovlivní.
	 */
	private const DEFAULT_URL = 'https://napoveda.sokct.cz';

	/**
	 * Odkazy podle obrazovky administrace.
	 *
	 * Klíč je `screen id` (WP_Screen::$id), hodnota pole dvojic cesta → popisek.
	 *
	 * Cesty musí odpovídat skutečným stránkám příručky. Ověřit se to dá proti
	 * sestavené dokumentaci — postup je v CLAUDE.md, oddíl Uživatelská
	 * dokumentace. Validátor odkazů uvnitř příručky tuhle hranici nevidí.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const SCREENS = array(
		'settings_page_kct_options' => array(
			'/zaciname/prvni-nastaveni/'      => 'První nastavení webu',
			'/funkce/sdileni-na-facebook/'    => 'Sdílení na Facebook',
			'/spravce/bezpecnost/'            => 'Kam bezpečně uložit token',
		),
		'edit-akce'                 => array(
			'/funkce/akce/'                   => 'Akce',
		),
		'akce'                      => array(
			'/funkce/akce/'                   => 'Akce',
			'/funkce/sdileni-na-facebook/'    => 'Sdílení na Facebook',
		),
		'edit-odbory'               => array(
			'/funkce/odbory/'                 => 'Odbory',
		),
		'odbory'                    => array(
			'/funkce/odbory/'                 => 'Odbory',
		),
		'edit-post'                 => array(
			'/zaklady-wordpressu/stranky-a-aktuality/' => 'Stránky a aktuality',
		),
		'post'                      => array(
			'/zaklady-wordpressu/stranky-a-aktuality/' => 'Stránky a aktuality',
			'/funkce/sdileni-na-facebook/'             => 'Sdílení na Facebook',
		),
	);

	/**
	 * Základní adresa nápovědy bez koncového lomítka.
	 */
	public function base_url(): string {
		$url = defined( 'KCT_DOCS_URL' ) ? (string) KCT_DOCS_URL : self::DEFAULT_URL;

		return untrailingslashit( (string) apply_filters( 'kct_docs_url', $url ) );
	}

	/**
	 * Úplná adresa stránky nápovědy.
	 *
	 * @param string $path Cesta včetně úvodního lomítka, např. `/funkce/akce/`.
	 */
	public function url( string $path = '/' ): string {
		return $this->base_url() . '/' . ltrim( $path, '/' );
	}

	/**
	 * Odkazy pro danou obrazovku administrace.
	 *
	 * @return array<string, string> Cesta → popisek. Prázdné pole, když
	 *                               obrazovka svůj odkaz nemá.
	 */
	public function for_screen( string $screen_id ): array {
		return self::SCREENS[ $screen_id ] ?? array();
	}

	/**
	 * Všechny cesty, na které se v administraci odkazuje.
	 *
	 * Používá kontrola, která je porovnává se sestavenou dokumentací.
	 *
	 * @return string[]
	 */
	public function all_paths(): array {
		$paths = array();

		foreach ( self::SCREENS as $links ) {
			foreach ( array_keys( $links ) as $path ) {
				$paths[ $path ] = true;
			}
		}

		foreach ( self::INLINE as $path ) {
			$paths[ $path ] = true;
		}

		return array_keys( $paths );
	}

	/**
	 * Cesty použité u viditelných odkazů v rozhraní.
	 *
	 * Nejsou v mapě obrazovek, protože je vykreslují jednotlivá pole a panely,
	 * ne záložka Nápovědy. Do kontroly odkazů ale patří stejně.
	 */
	private const INLINE = array(
		'settings'   => '/zaciname/prvni-nastaveni/',
		'facebook'   => '/funkce/sdileni-na-facebook/',
		'event_data' => '/funkce/akce/',
		'appearance' => '/funkce/vzhled-webu/',
	);

	/**
	 * Adresa viditelného odkazu podle jeho místa v rozhraní.
	 *
	 * @param string $context Klíč z INLINE.
	 */
	public function inline_url( string $context ): string {
		return $this->url( self::INLINE[ $context ] ?? '/' );
	}

	/**
	 * Hotová značka odkazu pro vložení do popisku pole nebo panelu.
	 *
	 * Otevírá se v novém panelu — uživatel je uprostřed vyplňování formuláře a
	 * odchod z rozepsané stránky by znamenal ztrátu rozdělané práce.
	 */
	public function inline_link( string $context, string $label = '' ): string {
		$label = $label ?: __( 'Nápověda', 'kct' );

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $this->inline_url( $context ) ),
			esc_html( $label )
		);
	}
}
