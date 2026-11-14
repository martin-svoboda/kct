<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Vkládaný widget Turinky pro stránku trasy.
 *
 * Je to iframe, ne skript vkládající obsah do stránky: obsah v rámu se
 * připisuje turinka.cz, kdežto obsah dopsaný JavaScriptem si vyhledávač
 * vyrenderuje a připíše hostitelské stránce. Vedle rámu proto stojí statický
 * odkaz — ten je jediná protihodnota, kterou Turinka za vložení dostává, a musí
 * být v DOM hostitele.
 *
 * Na stránce trasy stojí rámy tři a widget se proto vkládá po částech: mapa nad
 * textem, údaje o trase v postranním oddílu (tam, kde má akce svoje údaje)
 * a profil s tlačítky pod textem. Mezi nimi stojí to, co vlastní web — článek.
 * Odkaz do Turinky je u spodního rámu, aby na stránce byl právě jednou.
 *
 * Proti widgetu akcí je jednodušší: slug trasy plugin zná z propojení, takže se
 * nikam nedohledává a žádná cache není potřeba.
 */
class RouteEmbed {
	/** Pole v nastavení, kterým se widget vypíná. */
	const OPTION = 'turinka_route_widget';

	/** Rám nad textem: jen mapa se stopou. */
	const MODULES_MAP = array( 'map' );

	/**
	 * Rám v postranním oddílu: údaje o trase — délka, převýšení, odhad času,
	 * obtížnost a oblast. Modul `stats` tu schválně není: sám o sobě nevypíše
	 * nic, údaje jsou součástí `info`.
	 */
	const MODULES_FACTS = array( 'info' );

	/** Rám pod textem: výškový profil a tlačítka aplikace. */
	const MODULES_DETAILS = array( 'elevation', 'actions' );

	public function __construct(
		private Config $config,
		private LinkState $state,
		private SettingsRepository $settings
	) {
	}

	/**
	 * Rozlišuje se „neuloženo“ od „uloženo vypnuté“: get_option() vrací false
	 * pro obojí, takže by web, který volbu nikdy neviděl, widget nezobrazoval.
	 */
	public function is_enabled(): bool {
		$options = $this->settings->get_options();

		return ! array_key_exists( self::OPTION, $options ) || (bool) $options[ self::OPTION ];
	}

	/**
	 * Adresa skriptu, který na hostitelské stránce dopočítává výšku rámu.
	 */
	public function host_script_url(): string {
		return $this->config->public_url() . '/vlozit/host.js';
	}

	/**
	 * Mapa se stopou — rám nad textem článku.
	 */
	public function map( int $post_id, string $title ): string {
		return $this->frame( $post_id, $title, self::MODULES_MAP, 'map', false );
	}

	/**
	 * Údaje o trase — rám v postranním oddílu, na místě, kde má akce svoje
	 * údaje a odbor kontakty.
	 */
	public function facts( int $post_id, string $title ): string {
		return $this->frame( $post_id, $title, self::MODULES_FACTS, 'facts', false );
	}

	/**
	 * Výškový profil a tlačítka Turinky (oblíbit, sdílet, stáhnout GPX) — rám
	 * pod textem článku.
	 */
	public function details( int $post_id, string $title ): string {
		return $this->frame( $post_id, $title, self::MODULES_DETAILS, 'details', true );
	}

	/**
	 * Hotové HTML jednoho rámu, nebo prázdný řetězec, když trasa propojená není.
	 *
	 * @param string[] $modules  Moduly widgetu v pořadí, v jakém je Turinka vypíše.
	 * @param string   $variant  Modifikátor třídy — kvůli stylům se oba rámy musí
	 *                           dát rozlišit.
	 * @param bool     $with_link Vypsat pod rámem odkaz do Turinky.
	 */
	private function frame( int $post_id, string $title, array $modules, string $variant, bool $with_link ): string {
		$slug = $this->state->slug( $post_id );

		if ( ! $this->is_enabled() || '' === $slug ) {
			return '';
		}

		$base = $this->config->public_url();

		$out = sprintf(
			'<div class="kct-turinka-embed kct-turinka-embed--%1$s">'
			. '<iframe src="%2$s" data-turi-embed style="width:100%%;border:0;height:320px" loading="lazy" title="%3$s"></iframe>'
			. '</div>',
			esc_attr( $variant ),
			esc_url( $base . '/vlozit/trasa/' . rawurlencode( $slug ) . '?moduly=' . implode( ',', $modules ) ),
			/* translators: %s: název trasy. */
			esc_attr( sprintf( __( '%s — Turinka KČT', 'kct' ), $title ) )
		);

		if ( ! $with_link ) {
			return $out;
		}

		// Značky odchozího prokliku. Kliknutí z rámu nese Referer embed stránky
		// Turinky, ne hostitele, takže bez nich by proklik z widgetu nešlo
		// odlišit od běžné návštěvy.
		$url = add_query_arg(
			array(
				'utm_source' => 'widget',
				'utm_medium' => 'embed',
			),
			$base . '/trasa/' . rawurlencode( $slug )
		);

		return $out . sprintf(
			'<p class="kct-turinka-embed__link"><small><a href="%1$s" target="_blank" rel="noopener">%2$s</a></small></p>',
			esc_url( $url ),
			// Kotva nese název konkrétní trasy, ne klíčové slovo — tisíc webů se
			// stejnou kotvou je spam, tisíc názvů konkrétních tras přirozený stav.
			/* translators: %s: název trasy. */
			esc_html( sprintf( __( 'Zobrazit v Turince trasu %s', 'kct' ), $title ) )
		);
	}
}
