<?php

namespace Kct\Features;

use Kct\Help\DocsLinks;
use WP_Screen;

/**
 * Záložka „Nápověda“ na obrazovkách, které přidává šablona.
 *
 * Vestavěná záložka vpravo nahoře je místo, kam nápověda ve WordPressu patří,
 * a unese i delší text. Sama o sobě ale nestačí — je schovaná za tlačítkem,
 * které řada lidí nikdy nerozklikne. Proto se stejné odkazy objevují i přímo
 * v rozhraní; ty si přidávají místa, kterých se týkají (Settings,
 * EventPostType, FacebookShare, customizer.php).
 *
 * Tahle třída tedy dělá jedinou věc: podle obrazovky vybere z DocsLinks
 * odkazy a složí z nich záložku.
 */
class AdminHelp {

	public function __construct( private DocsLinks $links ) {
		// `admin_head`, ne `current_screen`: jádro registruje své záložky až
		// v obsluze load-{$hook}, tedy po `current_screen`. Odtud by naše
		// záložka vyšla první a odsunula „Přehled“, který na obrazovkách
		// WordPressu patří na začátek. `admin_head` běží v admin-header.php
		// ještě před render_screen_meta(), takže se přidání stihne a seřadí
		// se za jádro.
		add_action( 'admin_head', array( $this, 'add_help_tab' ) );
	}

	/**
	 * Přidá záložku Nápovědy, má-li obrazovka svůj odkaz.
	 */
	public function add_help_tab(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		$links = $this->links->for_screen( $screen->id );

		if ( empty( $links ) ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'kct_help',
				'title'   => __( 'Šablona KČT', 'kct' ),
				'content' => $this->tab_content( $links ),
			)
		);

		$this->add_sidebar( $screen );
	}

	/**
	 * Obsah záložky — odstavec a seznam odkazů na konkrétní stránky.
	 *
	 * @param array<string, string> $links Cesta → popisek.
	 */
	private function tab_content( array $links ): string {
		$items = '';

		foreach ( $links as $path => $label ) {
			$items .= sprintf(
				'<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>',
				esc_url( $this->links->url( $path ) ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<p>%s</p><ul>%s</ul>',
			esc_html__( 'Návody k této obrazovce v uživatelské příručce šablony:', 'kct' ),
			$items
		);
	}

	/**
	 * Postranní sloupec nápovědy s odkazem na celou příručku.
	 *
	 * Nastavuje se jen tehdy, když ho ještě nikdo nenaplnil — jádro i pluginy
	 * ho sdílejí a `set_help_sidebar()` přepisuje, takže bez téhle podmínky by
	 * se přepsal například odkaz na dokumentaci WordPressu u výpisu příspěvků.
	 */
	private function add_sidebar( WP_Screen $screen ): void {
		if ( '' !== trim( (string) $screen->get_help_sidebar() ) ) {
			return;
		}

		$screen->set_help_sidebar(
			sprintf(
				'<p><strong>%s</strong></p><p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_html__( 'Šablona KČT', 'kct' ),
				esc_url( $this->links->url( '/' ) ),
				esc_html__( 'Celá uživatelská příručka', 'kct' )
			)
		);
	}
}
