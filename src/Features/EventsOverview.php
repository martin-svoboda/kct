<?php

namespace Kct\Features;

use Kct\Admin\EventsOverviewTable;
use Kct\PostTypes\EventPostType;
use Kct\Turinka\EventState;
use Kct\Turinka\MineItems;

/**
 * Přehled akcí v administraci — jedna tabulka přes všechny zdroje.
 *
 * Sedí pod položkou Akce, ne pod obecným menu šablony: je to pohled na tentýž
 * obsah, jen přes víc zdrojů než výpis příspěvků. Redakce sem chodí s otázkou
 * „kde všude ta akce je a co s ní ještě můžu udělat“.
 *
 * Data skládá Events::get_events(), stav Turinky čte EventState. Stránka sama
 * nikam nechodí a nic neukládá — je to pohled, ne nástroj.
 */
class EventsOverview {

	const SLUG = 'kct-prehled-akci';

	/** Dokud je akcí tolik, výpis se skládá celý do paměti. */
	const CAPABILITY = 'edit_posts';

	/** Nonce odkazu „Načíst z Turinky znovu“. */
	const REFRESH_NONCE = 'kct-prehled-akci-obnovit';

	public function __construct(
		private Events $events,
		private EventState $state,
		private MineItems $mine
	) {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . EventPostType::KEY,
			__( 'Přehled akcí', 'kct' ),
			__( 'Přehled akcí', 'kct' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Na tuhle stránku nemáte přístup.', 'kct' ) );
		}

		// WP_List_Table jádro načítá až na obrazovkách, které ji používají —
		// na vlastní stránce menu to nemusí platit, takže se o soubor řekneme.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		// Propojení s Turinkou je dobrovolné. Web, který ho nemá, se jí na nic
		// neptá a nic o ní nevidí — ani sloupec, ani hlášku, ani tlačítko.
		$connected = $this->mine->has_token();
		$turinka   = array();

		if ( $connected ) {
			// Odkaz „Načíst z Turinky znovu“ jen zahodí uloženou odpověď; samotné
			// stažení udělá volání níž, takže se stav nikdy nerozejde s tím, co je
			// na stránce vidět.
			if ( isset( $_GET['obnovit'] ) && check_admin_referer( self::REFRESH_NONCE ) ) {
				$this->mine->forget( 'events' );
			}

			$turinka = $this->mine->all( 'events' );
		}

		if ( $connected && ! $turinka['ok'] && '' !== $turinka['message'] ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: %s: důvod, proč se akce z Turinky nenačetly. */
					__( 'Akce z Turinky se nepodařilo načíst: %s Sloupec Turinka ukazuje poslední známý stav, nebo zůstane prázdný.', 'kct' ),
					$turinka['message']
				) )
			);
		}

		// Prázdné datové okno = všechny akce, které web zná, bez ohledu na to,
		// jestli už proběhly. Přehled je inventura, ne výpis pro návštěvníka.
		$table = new EventsOverviewTable( $this->state, $this->events->get_events( '', '' ), $turinka, $connected );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Přehled akcí', 'kct' ); ?></h1>
			<?php if ( $connected ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'obnovit', '1' ), self::REFRESH_NONCE ) ); ?>">
					<?php esc_html_e( 'Načíst z Turinky znovu', 'kct' ); ?>
				</a>
			<?php endif; ?>
			<p class="description">
				<?php
				echo esc_html( $connected
					? __( 'Všechny akce, které web zná — vlastní příspěvky, akce z databáze KČT i akce, které o vaší organizaci ví Turinka. Jedna akce je jeden řádek, i když je ve všech třech zdrojích.', 'kct' )
					: __( 'Všechny akce, které web zná — vlastní příspěvky i akce z databáze KČT. Jedna akce je jeden řádek, i když je v obou zdrojích.', 'kct' )
				);
				?>
			</p>

			<form method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( EventPostType::KEY ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php
				$table->search_box( __( 'Hledat akci', 'kct' ), 'kct-prehled-akci' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
