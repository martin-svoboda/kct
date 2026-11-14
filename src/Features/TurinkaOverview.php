<?php

namespace Kct\Features;

use Kct\Admin\TurinkaItemsTable;
use KctDeps\Wpify\Asset\AssetFactory;
use KctDeps\Wpify\PluginUtils\PluginUtils;
use Kct\Turinka\Config;
use Kct\Turinka\MineItems;
use Kct\Turinka\TokenStore;

/**
 * Přehledy tras a míst z Turinky v administraci.
 *
 * Trasy ani místa web nedrží jako příspěvky — data patří aplikaci a web je
 * jen zobrazuje. Tyhle stránky jsou proto čtení z API, ne správa obsahu:
 * ukážou, co Turinka o organizaci webu vede, v jakém to je stavu a jestli
 * k tomu web dodal článek.
 *
 * **Bez propojení tyhle stránky v menu vůbec nejsou.** Propojení s Turinkou je
 * dobrovolné a web, který ho nemá, nemá důvod vidět položky menu k službě,
 * kterou nepoužívá. Menu šablony jako takové zůstává — nese nástěnku
 * a nastavení (viz AdminMenu).
 */
class TurinkaOverview {

	const ROUTES_SLUG = 'kct-turinka-trasy';
	const PLACES_SLUG = 'kct-turinka-mista';

	const CAPABILITY = 'edit_posts';

	/**
	 * Obrazovky přehledů a typ obsahu, který vypisují.
	 *
	 * Identifikátor skládá WordPress z nadřazeného menu (`kct`) a slugu
	 * stránky — po přesunu menu by se změnil, proto je tu jako konstanta
	 * a ne poskládaný na třech místech.
	 */
	const SCREENS = array(
		'kct_page_' . self::ROUTES_SLUG => 'routes',
		'kct_page_' . self::PLACES_SLUG => 'places',
	);

	/** Nonce odkazu „Načíst znovu“. */
	const REFRESH_NONCE = 'kct-turinka-obnovit';

	public function __construct(
		private MineItems $mine,
		private TokenStore $tokens,
		private Config $config,
		private AssetFactory $assets,
		private PluginUtils $utils
	) {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_pages(): void {
		if ( ! $this->tokens->has_token() ) {
			return;
		}

		add_submenu_page(
			AdminMenu::SLUG,
			__( 'Trasy v Turince', 'kct' ),
			__( 'Trasy v Turince', 'kct' ),
			self::CAPABILITY,
			self::ROUTES_SLUG,
			array( $this, 'render_routes' ),
			20
		);

		add_submenu_page(
			AdminMenu::SLUG,
			__( 'Místa v Turince', 'kct' ),
			__( 'Místa v Turince', 'kct' ),
			self::CAPABILITY,
			self::PLACES_SLUG,
			array( $this, 'render_places' ),
			30
		);
	}

	public function render_routes(): void {
		$this->render(
			'routes',
			self::ROUTES_SLUG,
			__( 'Trasy v Turince', 'kct' ),
			__( 'Trasy, které Turinka vede pod vaší organizací. Data i úpravy patří aplikaci — web je odsud jen vidí a může k nim odkázat článek.', 'kct' )
		);
	}

	public function render_places(): void {
		$this->render(
			'places',
			self::PLACES_SLUG,
			__( 'Místa v Turince', 'kct' ),
			__( 'Místa, která Turinka vede pod vaší organizací. Nové místo vzniká ke schválení, takže chvíli po založení bývá ve stavu „Čeká na schválení“.', 'kct' )
		);
	}

	/**
	 * Skripty a styl formuláře na stránky přehledů.
	 *
	 * Visí na `admin_enqueue_scripts`, ne uvnitř vykreslení stránky: styly se
	 * tisknou v hlavičce, takže přidat je až z těla stránky je pozdě a do
	 * výstupu se nedostanou. U skriptů v patičce by to prošlo, ale mít obojí na
	 * jednom místě je srozumitelnější.
	 *
	 * Hostitelský skript je cizí soubor na doméně aplikace — nemá manifest ani
	 * verzi, kterou bychom znali, takže se zařazuje ručně. Vlastní skript jde
	 * přes factory, aby si závislosti a verzi vzal z `build/*.asset.php`.
	 *
	 * @param string $hook Identifikátor obrazovky, který dává WordPress.
	 */
	public function enqueue( string $hook ): void {
		$type = self::SCREENS[ $hook ] ?? '';

		if ( '' === $type || ! $this->tokens->has_token() ) {
			return;
		}

		wp_enqueue_script(
			'turinka-form-host',
			$this->config->public_url() . '/integrace/formular.js',
			array(),
			null,
			true
		);

		// Odebrání je ztráta, i když vratná — tlačítko proto nese barvu, kterou
		// administrace používá pro mazání. Tři řádky stylu nestojí za vlastní
		// soubor, takže se přidávají k základnímu stylu administrace.
		wp_add_inline_style(
			'common',
			'.row-actions .kct-remove button{color:#b32d2e}'
			. '.row-actions .kct-remove button:hover{color:#8a1f1f}'
			. '.row-actions .kct-remove button[disabled]{color:#8c8f94}'
		);

		$asset = $this->assets->admin_wp_script(
			$this->utils->get_plugin_path( 'build/turinka-admin.js' ),
			array(
				'dependencies' => array( 'turinka-form-host' ),
				'in_footer'    => true,
				'variables'    => array(
					'kctTurinkaAdmin' => array(
						'type'          => $type,
						'failed'        => __( 'Formulář se nepodařilo otevřít.', 'kct' ),
						'hostMissing'   => __( 'Nepodařilo se načíst formulář Turinky. Zkuste stránku načíst znovu.', 'kct' ),
						/* translators: %s: název trasy nebo místa. */
						'confirmRemove' => __( 'Odebrat „%s“ z Turinky? Položka se neztratí — Turinka ji schová a vrátí se, když ji založíte znovu.', 'kct' ),
					),
				),
			)
		);

		unset( $asset );
	}

	private function render( string $type, string $slug, string $heading, string $intro ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Na tuhle stránku nemáte přístup.', 'kct' ) );
		}

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		// Odkaz „Načíst znovu“ jen zahodí uloženou odpověď; stažení udělá volání
		// níž, takže se stav nikdy nerozejde s tím, co je na stránce vidět.
		if ( isset( $_GET['obnovit'] ) && check_admin_referer( self::REFRESH_NONCE ) ) {
			$this->mine->forget( $type );
		}

		// Hláška po odebrání položky. Přežívá načtení stránky v transientu,
		// protože tabulka se po odebrání načítá znovu a hláška vypsaná skriptem
		// by s ní zmizela.
		$removed_key = 'kct_turinka_removed_' . get_current_user_id();
		$removed     = get_transient( $removed_key );

		if ( is_string( $removed ) && '' !== $removed ) {
			delete_transient( $removed_key );

			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $removed ) );
		}

		$result = $this->mine->all( $type );

		if ( ! $result['ok'] && '' !== $result['message'] ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %s: důvod, proč se data nenačetla. */
					__( 'Data z Turinky se nepodařilo načíst: %s Níž je poslední známý stav, nebo prázdná tabulka.', 'kct' ),
					$result['message']
				) )
			);
		}

		$table = new TurinkaItemsTable( $type, $result['items'] );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
			<button type="button" class="page-title-action" data-kct-turinka-form="create">
				<?php
				echo esc_html(
					'routes' === $type
						? __( 'Založit trasu v Turince', 'kct' )
						: __( 'Založit místo v Turince', 'kct' )
				);
				?>
			</button>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'obnovit', '1' ), self::REFRESH_NONCE ) ); ?>">
				<?php esc_html_e( 'Načíst znovu', 'kct' ); ?>
			</a>
			<p class="description"><?php echo esc_html( $intro ); ?></p>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $slug ); ?>">
				<?php
				$table->search_box( __( 'Hledat', 'kct' ), 'kct-turinka-' . $type );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
