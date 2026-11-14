<?php

namespace Kct\Features;

use Kct\PostTypes\EventPostType;
use Kct\Turinka\Config;
use Kct\Turinka\EventRoutesMetabox;
use Kct\Turinka\TokenStore;
use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;

/**
 * Vazby akce na trasy v administraci.
 *
 * Jediné místo, které zná hooky; vykreslení drží EventRoutesPanel a práci
 * s API Turinka\EventRoutes. Panel je na dvou obrazovkách — u akce s
 * příspěvkem v editoru a v přehledu akcí pod řádkem — a obě potřebují týž
 * skript. Zobrazí se jen propojenému webu; bez přístupu k Turince by neměl
 * co ukazovat.
 */
class EventRoutes {

	public function __construct(
		private EventRoutesMetabox $metabox,
		private TokenStore $tokens,
		private Config $config,
		private AssetFactory $assets,
		private PluginUtils $utils
	) {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Skript panelu na obrazovky, kde panel je.
	 *
	 * Hostitelský skript formuláře je cizí soubor na doméně aplikace — nemá
	 * manifest ani verzi, kterou bychom znali, takže se zařazuje ručně. Vlastní
	 * skript jde přes factory, aby si závislosti a verzi vzal z manifestu.
	 */
	public function enqueue( string $hook ): void {
		if ( ! $this->tokens->has_token() || ! $this->has_panel( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			'turinka-form-host',
			$this->config->public_url() . '/integrace/formular.js',
			array(),
			null,
			true
		);

		wp_add_inline_style(
			'common',
			'.kct-route-list{margin:0 0 12px}'
			. '.kct-route-list li{margin:0 0 8px;padding:0 0 8px;border-bottom:1px solid #f0f0f1}'
			. '.kct-route-list li:last-child{border-bottom:0}'
			. '.kct-route-remove{color:#b32d2e;float:right;font-size:16px;line-height:1;text-decoration:none}'
			. '.kct-route-results{margin:0}'
			. '.kct-route-results li{margin:0}'
			. '.kct-route-results button{display:block;width:100%;text-align:left;padding:6px 8px;background:none;border:0;cursor:pointer}'
			. '.kct-route-results button:hover{background:#f0f0f1}'
			// Panel pod řádkem přehledu akcí: ať je vidět, že patří k řádku
			// nad ním, a ať se neroztáhne přes celou šířku obrazovky.
			. '.kct-routes-row>td{background:#f6f7f7;box-shadow:inset 0 2px 0 -1px #c3c4c7}'
			. '.kct-routes-row [data-kct-event-routes="panel"]{max-width:28em;padding:4px 0}'
		);

		$this->assets->admin_wp_script(
			$this->utils->get_plugin_path( 'build/turinka-event.js' ),
			array(
				'dependencies' => array( 'turinka-form-host' ),
				'in_footer'    => true,
				'variables'    => array(
					'kctTurinkaEvent' => array(
						'failed'      =>__( 'Nepovedlo se to. Zkuste to prosím znovu.', 'kct' ),
						'hostMissing' => __( 'Nepodařilo se načíst formulář Turinky. Zkuste stránku načíst znovu.', 'kct' ),
						'noResults'   => __( 'Žádná trasa nenalezena.', 'kct' ),
					),
				),
			)
		);
	}

	/**
	 * Je na téhle obrazovce panel tras?
	 *
	 * Editor akce ho má v postranním sloupci, přehled akcí pod každým řádkem
	 * akce, kterou web v Turince spravuje.
	 */
	private function has_panel( string $hook ): bool {
		if ( EventPostType::KEY . '_page_' . EventsOverview::SLUG === $hook ) {
			return true;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && EventPostType::KEY === $screen->post_type;
	}
}
