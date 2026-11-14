<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use Kct\PostTypes\EventPostType;
use Kct\Repositories\SettingsRepository;
use Kct\Settings;
use Kct\Turinka\EventState;
use Kct\Turinka\MineItems;
use Kct\Turinka\TokenStore;

/**
 * Menu šablony v administraci a jeho nástěnka.
 *
 * Jedno místo, kde se registruje vrchní položka menu — nastavení i přehledy
 * Turinky se pod ni jen věší. Proto běží dřív (priorita 9) než ony: podmenu
 * nemá kam přibýt, dokud menu neexistuje.
 *
 * **V menu stojí dole, pod Nastavením.** Není to správa obsahu; je to nářadí
 * ke správě webu, a mezi Příspěvky a Médii by jen překáželo.
 */
class AdminMenu {

	/** Slug vrchní položky i nástěnky. */
	const SLUG = 'kct';

	/**
	 * Pozice v menu — **začátek druhé sekce**, hned za oddělovačem (59) a před
	 * Vzhledem (60).
	 *
	 * Správa webu začíná tady, ne až pod Nastavením: do menu šablony se chodí
	 * často, kdežto úplný spodek menu je pro věci, které se otevřou jednou za
	 * čas. Desetinné místo drží odstup od cizích pluginů, které si berou celá
	 * čísla — při shodě by WordPress jednu z položek tiše zahodil.
	 */
	const POSITION = 59.7;

	const CAPABILITY = 'edit_posts';

	public function __construct(
		private SettingsRepository $settings,
		private TokenStore $tokens,
		private EventState $state,
		private MineItems $mine,
		private Credentials $facebook,
		private Events $events
	) {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'KČT', 'kct' ),
			__( 'KČT', 'kct' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-location',
			self::POSITION
		);

		add_submenu_page(
			self::SLUG,
			__( 'Nástěnka KČT', 'kct' ),
			__( 'Nástěnka', 'kct' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			10
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Na tuhle stránku nemáte přístup.', 'kct' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nástěnka KČT', 'kct' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Odkud web bere obsah, co kam posílá a co z toho zrovna vázne.', 'kct' ); ?></p>

			<div class="kct-dashboard" style="display:flex;flex-wrap:wrap;gap:16px;margin-top:16px">
				<?php
				$this->card( __( 'Akce', 'kct' ), $this->events_rows() );
				$this->card( __( 'Turinka', 'kct' ), $this->turinka_rows() );
				$this->card( __( 'Facebook', 'kct' ), $this->facebook_rows() );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Jedna kartička nástěnky.
	 *
	 * @param array<int,array{0:string,1:string}> $rows Dvojice popisek + hodnota (HTML).
	 */
	private function card( string $title, array $rows ): void {
		?>
		<div class="card" style="max-width:420px;flex:1 1 320px">
			<h2 class="title" style="margin-top:0"><?php echo esc_html( $title ); ?></h2>
			<table class="widefat striped" style="border:0">
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td style="width:45%"><?php echo esc_html( $row[0] ); ?></td>
						<td><?php echo $row[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- skládá se níž s escapováním. ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** Akce: kolik jich web zná, odkud a kdy přibudou nové. */
	private function events_rows(): array {
		$all   = $this->events->get_events( '', '' );
		$own   = 0;
		$only  = 0;

		foreach ( $all as $event ) {
			if ( (int) ( $event['post_id'] ?? 0 ) > 0 ) {
				$own++;
			} elseif ( ! empty( $event['db_id'] ) ) {
				$only++;
			}
		}

		$export = trim( (string) $this->settings->get_option( 'db_export_url' ) );
		$next   = wp_next_scheduled( 'kct_update_events' );

		return array(
			array( __( 'Akcí celkem', 'kct' ), esc_html( number_format_i18n( count( $all ) ) ) ),
			array( __( 'S vlastním příspěvkem', 'kct' ), esc_html( number_format_i18n( $own ) ) ),
			array( __( 'Jen z databáze KČT', 'kct' ), esc_html( number_format_i18n( $only ) ) ),
			array(
				__( 'Import z databáze', 'kct' ),
				'' === $export
					? esc_html__( 'nenastavený — akce se neimportují', 'kct' )
					: ( $next
						? esc_html( sprintf(
							/* translators: %s: datum a čas příštího importu. */
							__( 'další běh %s', 'kct' ),
							wp_date( 'j. n. Y H:i', $next )
						) )
						: esc_html__( 'naplánovaný není', 'kct' ) ),
			),
			array(
				__( 'Přehled', 'kct' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?post_type=' . EventPostType::KEY . '&page=' . EventsOverview::SLUG ) ),
					esc_html__( 'Otevřít přehled akcí', 'kct' )
				),
			),
		);
	}

	/**
	 * Turinka: stav propojení a co o webu ví.
	 *
	 * Počty se berou **jen z uložené odpovědi**, a když žádná není, nástěnka to
	 * řekne. Ptát se kvůli třem číslům na tři výpisy pokaždé, když si někdo
	 * otevře nástěnku, by znamenalo čekání i zbytečná volání — a limit API je
	 * sto dotazů za minutu na web. Data natáhne stránka přehledu.
	 */
	private function turinka_rows(): array {
		$state = $this->tokens->state();

		if ( TokenStore::STATE_ACTIVE !== $state ) {
			return array(
				array(
					__( 'Stav', 'kct' ),
					TokenStore::STATE_PENDING === $state
						? esc_html__( 'žádost čeká na schválení', 'kct' )
						: esc_html__( 'web propojený není', 'kct' ),
				),
				array(
					__( 'Propojení', 'kct' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( Settings::get_settings_url() ),
						esc_html__( 'Zřídit v nastavení', 'kct' )
					),
				),
			);
		}

		$owner = $this->tokens->owner();
		$rows  = array(
			array(
				__( 'Stav', 'kct' ),
				esc_html( sprintf(
					/* translators: %s: název odboru nebo oblasti. */
					__( 'propojeno jako %s', 'kct' ),
					(string) ( $owner['name'] ?? __( 'neznámý vlastník', 'kct' ) )
				) ),
			),
		);

		foreach ( array( 'events' => __( 'Akcí v Turince', 'kct' ), 'routes' => __( 'Tras', 'kct' ), 'places' => __( 'Míst', 'kct' ) ) as $type => $label ) {
			$data = $this->mine->cached( $type );

			$rows[] = array(
				$label,
				null === $data
					? esc_html__( 'zatím nenačteno', 'kct' )
					: esc_html( number_format_i18n( count( $data['items'] ) ) ),
			);
		}

		$errors = $this->failed_events();

		$rows[] = array(
			__( 'Nepovedená odeslání', 'kct' ),
			$errors
				? sprintf(
					'<strong>%s</strong>',
					esc_html( sprintf(
						/* translators: %s: počet akcí s chybou. */
						_n( '%s akce', '%s akcí', $errors, 'kct' ),
						number_format_i18n( $errors )
					) )
				)
				: esc_html__( 'žádná', 'kct' ),
		);

		$rows[] = array(
			__( 'Přehledy', 'kct' ),
			sprintf(
				'<a href="%s">%s</a> · <a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . TurinkaOverview::ROUTES_SLUG ) ),
				esc_html__( 'Trasy', 'kct' ),
				esc_url( admin_url( 'admin.php?page=' . TurinkaOverview::PLACES_SLUG ) ),
				esc_html__( 'Místa', 'kct' )
			),
		);

		return $rows;
	}

	/** Kolik akcí má poslední odeslání do Turinky neúspěšné. */
	private function failed_events(): int {
		$ids = get_posts( array(
			'post_type'        => EventPostType::KEY,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		) );

		$failed = 0;

		foreach ( $ids as $id ) {
			if ( '' !== $this->state->error( (int) $id ) ) {
				$failed++;
			}
		}

		return $failed;
	}

	private function facebook_rows(): array {
		if ( ! $this->facebook->is_configured() ) {
			return array(
				array( __( 'Stav', 'kct' ), esc_html__( 'sdílení nastavené není', 'kct' ) ),
				array(
					__( 'Nastavení', 'kct' ),
					sprintf( '<a href="%s">%s</a>', esc_url( Settings::get_settings_url() ), esc_html__( 'Nastavit sdílení', 'kct' ) ),
				),
			);
		}

		return array(
			array( __( 'Stav', 'kct' ), esc_html__( 'sdílení je nastavené', 'kct' ) ),
			array( __( 'Stránka', 'kct' ), esc_html( $this->facebook->page_id() ) ),
			array(
				__( 'Nastavení', 'kct' ),
				sprintf( '<a href="%s">%s</a>', esc_url( Settings::get_settings_url() ), esc_html__( 'Otevřít nastavení', 'kct' ) ),
			),
		);
	}
}
