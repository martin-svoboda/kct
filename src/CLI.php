<?php

namespace Kct;

use Kct\Facebook\Credentials;
use Kct\Facebook\GraphClient;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\ShareState;
use Kct\Features\Departments;
use Kct\Features\Events;
use Kct\Features\FacebookShare;
use WP_CLI;
use WP_CLI_Command;

class CLI extends WP_CLI_Command {

	public function __construct() {
		parent::__construct();
		WP_CLI::add_command( 'kct', self::class );
	}

	public function import_departments() {
		$departments = kct_container()->get( Departments::class );
		$departments->import_departments();
	}

	public function import_events() {
		$events = kct_container()->get( Events::class );
		$events->import_db_events();
	}

	public function update_events() {
		$events = kct_container()->get( Events::class );
		$events->import_db_events( true );
	}

	/**
	 * Ověří připojení k Facebooku a vypíše název připojené stránky.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct fb_check
	 */
	public function fb_check() {
		$credentials = kct_container()->get( Credentials::class );

		if ( ! $credentials->is_configured() ) {
			WP_CLI::error( __( 'Chybí Page ID nebo token. Vyplň je v Nastavení → KČT.', 'kct' ) );
		}

		$result = kct_container()->get( GraphClient::class )->verify( $credentials->token() );

		if ( empty( $result['ok'] ) ) {
			// Kód 0 znamená, že se nepodařilo Facebook vůbec zastihnout (chyba
			// spojení) — od skutečné chybové odpovědi API to je potřeba odlišit.
			if ( 0 === (int) $result['code'] ) {
				WP_CLI::error( sprintf( __( 'Nepodařilo se spojit s Facebookem: %s', 'kct' ), $result['message'] ) );
			}

			WP_CLI::error( sprintf( __( 'Facebook vrátil chybu %1$d: %2$s', 'kct' ), $result['code'], $result['message'] ) );
		}

		// Ověření prokázalo, že token funguje — upozornění na neplatný token
		// v administraci už neplatí.
		delete_option( FacebookShare::TOKEN_ERROR_OPTION );

		$page_name = $result['name'] ?? '';
		$page_id   = $result['id'] ?? '?';

		WP_CLI::success( sprintf( __( 'Připojeno ke stránce „%1$s“ (ID %2$s).', 'kct' ), $page_name, $page_id ) );

		// /me vrací identitu, ke které token patří — u uživatelského tokenu
		// nebo tokenu jiné stránky se liší od Page ID v nastavení.
		if ( isset( $result['id'] ) && $result['id'] !== $credentials->page_id() ) {
			WP_CLI::warning( sprintf(
				__( 'Token patří k jinému účtu, než je nastavené Page ID. Vráceno ID %1$s, v nastavení je %2$s.', 'kct' ),
				$result['id'],
				$credentials->page_id()
			) );
		}
	}

	/**
	 * Odešle příspěvek na Facebook, nebo jen vypíše, co by odeslal.
	 *
	 * Samotné odeslání dělá feature FacebookShare, ne tento příkaz — pravidla
	 * sdílení (podmínky, zámek proti souběhu, obsluha chyb a opakování) mají
	 * jediné místo. Vlastní cestu má jen `--force`, protože ta musí odeslat
	 * i příspěvek, který už odeslaný je; i ta ale zabírá stejný zámek a po
	 * selhání volá stejnou obsluhu.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID příspěvku.
	 *
	 * [--dry-run]
	 * : Jen vypíše text a odkaz, nic neodešle.
	 *
	 * [--force]
	 * : Odešle příspěvek, i když už na Facebooku odeslaný byl. Bez tohoto přepínače takový příspěvek příkaz odmítne, aby na zdi nevznikl duplikát.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct fb_share 123 --dry-run
	 *     wp kct fb_share 123
	 *     wp kct fb_share 123 --force
	 */
	public function fb_share( $args, $assoc_args ) {
		$post_id = intval( $args[0] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			WP_CLI::error( sprintf( __( 'Příspěvek %d neexistuje.', 'kct' ), $post_id ) );
		}

		$state = kct_container()->get( ShareState::class );
		$force = ! empty( $assoc_args['force'] );

		if ( empty( $assoc_args['dry-run'] ) && ! $force && $state->is_shared( $post_id ) ) {
			WP_CLI::error( sprintf(
				/* translators: %s: ID příspěvku na Facebooku. */
				__( 'Příspěvek už na Facebooku odeslaný je (ID %s). Opakované odeslání vynutíš přepínačem --force.', 'kct' ),
				$state->fb_post_id( $post_id )
			) );
		}

		$composer = kct_container()->get( MessageComposer::class );
		$message  = $composer->compose( $post );
		$link     = $composer->link( $post );

		if ( '' === trim( $message ) ) {
			WP_CLI::error( __( 'Příspěvek nemá co odeslat — složený text vyšel prázdný.', 'kct' ) );
		}

		WP_CLI::line( '--- text ---' );
		WP_CLI::line( $message );
		WP_CLI::line( '--- odkaz ---' );
		WP_CLI::line( $link ? $link : __( '(bez odkazu)', 'kct' ) );

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			return;
		}

		$share = kct_container()->get( FacebookShare::class );

		// Čekající událost je po ručním odeslání k ničemu — a nechat ji viset by
		// rozbilo odstupy opakování, viz FacebookShare::unschedule().
		$share->unschedule( $post_id );

		$before = $share->snapshot( $post_id );

		if ( $force ) {
			$this->fb_share_forced( $post_id, $message, $link );
		} else {
			$share->share( $post_id );
		}

		$this->report_share_outcome( $share->outcome( $post_id, $before ) );
	}

	/**
	 * Vynucené odeslání příspěvku, který už na Facebooku je.
	 *
	 * Jediná cesta, která obchází FacebookShare::share() — ta by u odeslaného
	 * příspěvku skončila hned na kontrole is_shared(). Zbytek pravidel ale
	 * platí i tady: zámek proti souběhu i obsluha selhání jsou stejné, jen
	 * kontroly stavu příspěvku a přepínače se záměrně přeskakují.
	 *
	 * Výstup do konzole se schválně odkládá až za `finally` — WP_CLI::error()
	 * proces ukončí a bloky `finally` se v takovém případě nespustí, takže by
	 * zámek zůstal viset až do vypršení TTL.
	 *
	 * @param int         $post_id ID příspěvku.
	 * @param string      $message Text příspěvku.
	 * @param string|null $link    Odkaz pro náhledovou kartu, nebo null.
	 */
	private function fb_share_forced( int $post_id, string $message, ?string $link ): void {
		$credentials = kct_container()->get( Credentials::class );

		if ( ! $credentials->is_configured() ) {
			WP_CLI::error( __( 'Chybí Page ID nebo token. Vyplň je v Nastavení → KČT.', 'kct' ) );
		}

		$state = kct_container()->get( ShareState::class );
		$share = kct_container()->get( FacebookShare::class );

		if ( ! $state->claim( $post_id ) ) {
			WP_CLI::error( __( 'Odeslání tohoto příspěvku právě probíhá jinde — zkus to za chvíli.', 'kct' ) );
		}

		try {
			$result = kct_container()->get( GraphClient::class )->publish(
				$credentials->page_id(),
				$credentials->token(),
				$message,
				$link
			);

			if ( empty( $result['ok'] ) ) {
				$state->mark_error( $post_id, (int) $result['code'], (string) $result['message'] );
				$share->handle_failure( $post_id, (int) $result['code'], (string) $result['message'] );

				return;
			}

			$state->mark_shared( $post_id, (string) $result['id'] );

			// Úspěšné odeslání prokázalo, že token funguje.
			delete_option( FacebookShare::TOKEN_ERROR_OPTION );
		} finally {
			$state->release( $post_id );
		}
	}

	/**
	 * Vypíše, co se s příspěvkem stalo — odesláno, selhalo, nebo přeskočeno.
	 *
	 * Hlášky skládá FacebookShare::outcome(), aby CLI a tlačítko „Zkusit znovu“
	 * v editoru říkaly totéž. Přeskočení i selhání končí nenulovým návratovým
	 * kódem: uživatel chtěl odeslat a neodeslalo se.
	 *
	 * @param array{status: string, message: string} $outcome Vyhodnocený výsledek pokusu.
	 */
	private function report_share_outcome( array $outcome ): void {
		if ( FacebookShare::RESULT_SHARED === $outcome['status'] ) {
			WP_CLI::success( $outcome['message'] );

			return;
		}

		if ( FacebookShare::RESULT_FAILED === $outcome['status'] ) {
			$message = $outcome['message'];
		} else {
			$message = sprintf(
				/* translators: %s: důvod, proč se příspěvek neodeslal. */
				__( 'Neodesláno: %s', 'kct' ),
				$outcome['message']
			);
		}

		WP_CLI::error( $message );
	}
}
