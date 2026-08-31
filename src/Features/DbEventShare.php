<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use Kct\Facebook\DbShareState;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\Publisher;
use Kct\Facebook\ShareSchedule;

/**
 * Sdílení akcí z centrální databáze na Facebook.
 *
 * Tyhle akce nemají příspěvek, takže se jich běžné sdílení (Features\FacebookShare)
 * nedotkne — a je jich drtivá většina: z 319 akcí, které sokct.cz vypisuje, má
 * vlastní příspěvek 12.
 *
 * Odesílá je denní úloha. Ne plánování jednotlivých akcí dopředu: import akce
 * přepisuje, takže by změna termínu ve feedu nechala viset naplánované
 * odeslání na starý čas.
 */
class DbEventShare {

	const CRON_HOOK = 'kct_fb_share_due';

	/**
	 * Kolik dní zpět úloha hledá akce, kterým den odeslání už nastal.
	 *
	 * Pojistka pro případ, že web na den ztichne, cache se zpřísní nebo někdo
	 * předsadí CDN — WP-Cron se spouští při požadavcích na web. Měřeno na
	 * produkci je nejhorší zpoždění pod dvě hodiny, ale tři dny jsou levné.
	 *
	 * Z okna zároveň plyne, že se nemá co nahromadit: akce starší než tři dny
	 * se samy neodešlou nikdy, takže se při prvním spuštění nevysype historie.
	 */
	const WINDOW_DAYS = 3;

	public function __construct(
		private Events $events,
		private DbShareState $state,
		private Credentials $credentials,
		private MessageComposer $composer,
		private Publisher $publisher,
		private ShareSchedule $schedule,
		private OgImages $og_images
	) {
		add_action( 'init', array( $this, 'schedule_daily' ) );
		add_action( self::CRON_HOOK, array( $this, 'send_due' ) );
		add_action( 'admin_post_kct_fb_db', array( $this, 'handle_action' ) );
	}

	/**
	 * Naplánuje denní úlohu na hodinu z nastavení.
	 *
	 * Systémový cron se nevyžaduje — plugin se distribuuje i jako balíček pro
	 * weby mimo tuhle síť a nesmí potřebovat zásah do serveru.
	 */
	public function schedule_daily(): void {
		if ( ! $this->credentials->is_configured() ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		$zone  = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $zone );
		$first = $today->setTime( $this->schedule->hour(), 0 );

		if ( $first->getTimestamp() <= time() ) {
			$first = $first->modify( '+1 day' );
		}

		wp_schedule_event( $first->getTimestamp(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Odešle akce, kterým den odeslání nastal.
	 */
	public function send_due(): void {
		if ( ! $this->credentials->is_configured() ) {
			return;
		}

		foreach ( $this->due() as $event ) {
			$this->send( $event );
		}
	}

	/**
	 * Akce, které tenhle web má právě teď odeslat.
	 *
	 * @return array<int, array>
	 */
	public function due(): array {
		if ( ! $this->credentials->share_default_for( 'akce' ) ) {
			return array();
		}

		$zone = wp_timezone();
		$lead = $this->schedule->lead_days();
		$out  = array();

		// Den odeslání je datum akce mínus odstup, a odstup je globální —
		// hledané datum akce je tedy dnešek plus odstup, plus okno pro dny,
		// kdy se úloha nespustila.
		for ( $back = 0; $back < self::WINDOW_DAYS; $back++ ) {
			$date = ( new \DateTimeImmutable( 'today', $zone ) )
				->modify( '+' . ( $lead - $back ) . ' days' )
				->format( 'Y-m-d' );

			foreach ( $this->events->get_events( $date, $date ) as $event ) {
				$db_id = (int) ( $event['db_id'] ?? 0 );

				if ( ! $db_id || ! $this->eligible( $event, $db_id ) ) {
					continue;
				}

				$out[ $db_id ] = $event;
			}
		}

		return array_values( $out );
	}

	/**
	 * Smí a má se tahle akce odeslat?
	 */
	private function eligible( array $event, int $db_id ): bool {
		// Akce s vlastním příspěvkem — o tu se stará běžné sdílení a odešla by
		// dvakrát.
		//
		// Pozná se podle klíče 'post_type': pole akce z databáze pochází
		// z DbEventModel a ten klíč nemá vůbec, kdežto akce s příspěvkem jde
		// přes EventModel (potomek Post), který ho nese vždy. Ověřeno na
		// datech porovnáním klíčů obou tvarů.
		if ( ! empty( $event['post_type'] ) ) {
			return false;
		}

		if ( ! $this->events->lists_event( $event ) ) {
			return false;
		}

		if ( $this->state->is_shared( $db_id ) || $this->state->is_disabled( $db_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Odešle jednu akci. Vrací true při úspěchu.
	 */
	public function send( array $event ): bool {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( ! $db_id || ! $this->credentials->is_configured() ) {
			return false;
		}

		if ( ! $this->state->claim( $db_id ) ) {
			return false;
		}

		try {
			if ( $this->state->is_shared( $db_id ) ) {
				return false;
			}

			$image = $this->og_images->social_for_event( $event );

			$result = $this->publisher->send(
				$this->composer->db_event_message( $event ),
				$this->composer->db_event_message_with_link( $event ),
				$this->composer->db_event_link( $event ),
				$image['url'] ?? null
			);

			if ( ! empty( $result['ok'] ) ) {
				$this->state->mark_shared( $db_id, (string) ( $result['id'] ?? '' ) );

				return true;
			}

			$this->state->mark_error( $db_id, (int) ( $result['code'] ?? 0 ), (string) ( $result['message'] ?? '' ) );

			return false;
		} finally {
			$this->state->release( $db_id );
		}
	}

	/**
	 * Obsluha tlačítek ze stránky akce.
	 *
	 * Přes admin-post.php, stejně jako stávající „Převést na vlastní akci" —
	 * potřebuje přihlášeného uživatele a nonce.
	 */
	public function handle_action(): void {
		$db_id = isset( $_REQUEST['db_id'] ) ? (int) $_REQUEST['db_id'] : 0;
		$do    = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : '';

		if ( ! $db_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'K této akci nemáte oprávnění.', 'kct' ) );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'kct-fb-db-' . $db_id ) ) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ) );
		}

		$event = $this->events->get_event( 0, $db_id );

		if ( $event && $this->events->lists_event( $event ) ) {
			if ( 'off' === $do ) {
				$this->state->set_disabled( $db_id, true );
			} elseif ( 'on' === $do ) {
				$this->state->set_disabled( $db_id, false );
			} elseif ( 'now' === $do ) {
				$this->send( $event );
			}
		}

		wp_safe_redirect( home_url( 'akce-db/' . $db_id ), 302, 'kct' );
		exit;
	}

	/**
	 * Řádek se stavem a odkazy pro stránku akce.
	 *
	 * Vrací hotové HTML, nebo prázdný řetězec, když se nemá co ukázat —
	 * u akce, kterou web nevypisuje, nebo bez nastaveného sdílení.
	 */
	public function control_html( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( ! $db_id || ! $this->credentials->is_configured() || ! $this->events->lists_event( $event ) ) {
			return '';
		}

		$url = static function ( string $do ) use ( $db_id ): string {
			return add_query_arg( array(
				'action'   => 'kct_fb_db',
				'db_id'    => $db_id,
				'do'       => $do,
				'_wpnonce' => wp_create_nonce( 'kct-fb-db-' . $db_id ),
			), admin_url( 'admin-post.php' ) );
		};

		if ( $this->state->is_shared( $db_id ) ) {
			return sprintf(
				'<p class="kct-fb-state">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_html__( 'Odesláno na Facebook.', 'kct' ),
				esc_url( 'https://www.facebook.com/' . $this->state->fb_post_id( $db_id ) ),
				esc_html__( 'Zobrazit příspěvek', 'kct' )
			);
		}

		$note = $this->state->is_disabled( $db_id )
			? __( 'Na Facebook se neodešle.', 'kct' )
			: $this->due_note( $event );

		$toggle = $this->state->is_disabled( $db_id )
			? sprintf( '<a href="%s">%s</a>', esc_url( $url( 'on' ) ), esc_html__( 'Sdílet', 'kct' ) )
			: sprintf( '<a href="%s">%s</a>', esc_url( $url( 'off' ) ), esc_html__( 'Nesdílet', 'kct' ) );

		return sprintf(
			'<p class="kct-fb-state">%s &nbsp; %s &nbsp; <a href="%s">%s</a></p>',
			esc_html( $note ),
			$toggle,
			esc_url( $url( 'now' ) ),
			esc_html__( 'Odeslat hned', 'kct' )
		);
	}

	/**
	 * Kdy se akce odešle, slovy.
	 */
	private function due_note( array $event ): string {
		$date = '';

		foreach ( array( $event['start']['date'] ?? '', $event['date'] ?? '' ) as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$date = trim( $value );
				break;
			}
		}

		$timestamp = '' !== $date ? strtotime( $date ) : false;

		if ( ! $timestamp ) {
			return __( 'Akce nemá datum, na Facebook se neodešle.', 'kct' );
		}

		$send = $timestamp - ( $this->schedule->lead_days() * DAY_IN_SECONDS );

		if ( $send < strtotime( '-' . self::WINDOW_DAYS . ' days' ) ) {
			return __( 'Termín odeslání už uplynul — pošlete ručně.', 'kct' );
		}

		/* translators: %s: datum odeslání na Facebook. */
		return sprintf( __( 'Na Facebook se odešle %s.', 'kct' ), wp_date( 'j. n. Y', $send ) );
	}
}
