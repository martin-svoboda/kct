<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Vkládací widget Turinky pro stránku akce.
 *
 * Je to iframe, ne skript vkládající obsah do stránky: obsah v rámu se připisuje
 * turinka.cz, kdežto obsah dopsaný JavaScriptem si vyhledávač vyrenderuje
 * a připíše hostitelské stránce. Vedle rámu proto stojí statický odkaz — ten je
 * jediná protihodnota, kterou Turinka za vložení dostává, a musí být v DOM
 * hostitele, ne uvnitř rámu, kde by turinka.cz odkazovala sama na sebe.
 */
class Embed {
	/** Pole v nastavení, kterým se widget vypíná. */
	const OPTION = 'turinka_widget';

	/** Moduly, které widget umí. Vkládá se celá sada. */
	const MODULES = array( 'image', 'info', 'content', 'stats', 'actions' );

	const CACHE_PREFIX = 'kct_turinka_slug_';

	/** Slug se mění jen s názvem akce, takže se drží dlouho. */
	const CACHE_TTL = WEEK_IN_SECONDS;

	/**
	 * I „akci neznám“ je potřeba si pamatovat — jinak by se na každé zobrazení
	 * akce, kterou Turinka nemá, posílal dotaz. Kratší, protože akce může
	 * v Turince přibýt.
	 */
	const CACHE_MISS_TTL = 6 * HOUR_IN_SECONDS;

	public function __construct(
		private Config $config,
		private Client $client,
		private EventState $state,
		private SettingsRepository $settings
	) {
	}

	/**
	 * Má se widget na tomhle webu vkládat?
	 *
	 * **Výchozí je zapnuto a propojení k tomu není potřeba** — čtecí API Turinky
	 * je veřejné, takže widget funguje i webu, který o přístup nikdy nepožádal.
	 *
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
	 * Hotové HTML widgetu, nebo prázdný řetězec.
	 *
	 * Prázdný znamená „nemáme co vložit“ — akci Turinka nezná, nebo neodpovídá.
	 * Widget nikdy nesmí rozbít stránku akce.
	 *
	 * @param array $event Akce z Events::get_event().
	 */
	public function snippet( array $event ): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$slug = $this->slug( $event );

		if ( '' === $slug ) {
			return '';
		}

		$base  = $this->config->public_url();
		$title = (string) ( $event['title'] ?? '' );

		// Značky odchozího prokliku. Kliknutí z rámu nese Referer embed stránky
		// Turinky, ne hostitele, takže bez nich by proklik z widgetu nešlo odlišit
		// od běžné návštěvy — tenhle odkaz je ale v DOM hostitele a značky si nese sám.
		$url = add_query_arg(
			array(
				'utm_source' => 'widget',
				'utm_medium' => 'embed',
			),
			$base . '/akce/' . rawurlencode( $slug )
		);

		return sprintf(
			'<div class="kct-turinka-embed">'
			. '<iframe src="%1$s" data-turi-embed style="width:100%%;border:0;height:320px" loading="lazy" title="%2$s"></iframe>'
			. '<p class="kct-turinka-embed__link"><small><a href="%3$s" target="_blank" rel="noopener">%4$s</a></small></p>'
			. '</div>',
			esc_url( $base . '/vlozit/akce/' . rawurlencode( $slug ) . '?moduly=' . implode( ',', self::MODULES ) ),
			/* translators: %s: název akce. */
			esc_attr( sprintf( __( '%s — Turinka KČT', 'kct' ), $title ) ),
			esc_url( $url ),
			// Kotva nese název konkrétní akce, ne klíčové slovo — tisíc webů se
			// stejnou kotvou je spam, tisíc názvů konkrétních akcí přirozený stav.
			/* translators: %s: název akce. */
			esc_html( sprintf( __( 'Zobrazit v Turince akci %s', 'kct' ), $title ) )
		);
	}

	/**
	 * Slug akce v Turince.
	 *
	 * U vlastních akcí ho plugin zná ze zápisu. U akcí z feedu se hledá podle
	 * čísla z centrální databáze veřejným čtecím API — tam se dopředu vzít nedá
	 * a import je XML nad tisíci akcemi, takže se ptáme až při vykreslení,
	 * s krátkým timeoutem a s cache na obě odpovědi.
	 */
	private function slug( array $event ): string {
		$post_id = (int) ( $event['id'] ?? 0 );

		if ( $post_id && ! empty( $event['post_type'] ) ) {
			$own = $this->state->slug( $post_id );

			if ( '' !== $own ) {
				return $own;
			}
		}

		$kct_id = trim( (string) ( $event['db_id'] ?? '' ) );

		if ( '' === $kct_id || '0' === $kct_id ) {
			return '';
		}

		$cached = get_transient( self::CACHE_PREFIX . $kct_id );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$result = $this->client->event_by_kct_id( $kct_id );
		$slug   = (string) ( $result['data']['slug'] ?? '' );

		// Nedostupnou Turinku si zapamatovat nesmíme — to by z výpadku udělalo
		// šest hodin bez widgetu. Cachuje se jen odpověď, kterou opravdu dala.
		if ( ! empty( $result['ok'] ) || 404 === (int) $result['status'] ) {
			set_transient(
				self::CACHE_PREFIX . $kct_id,
				$slug,
				'' !== $slug ? self::CACHE_TTL : self::CACHE_MISS_TTL
			);
		}

		return $slug;
	}
}
