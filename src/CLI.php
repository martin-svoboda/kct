<?php

namespace Kct;

use Kct\Facebook\Credentials;
use Kct\Facebook\GraphClient;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\ShareState;
use Kct\Features\Departments;
use Kct\Features\Events;
use Kct\Features\FacebookShare;
use Kct\Images\MetadataCleaner;
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

	/**
	 * Odstraní z obrázků v knihovně nepotřebná metadata.
	 *
	 * Zahazuje EXIF, XMP, IPTC a barevné profily, které pro zobrazení na webu
	 * nemají význam. Obrazová data se nedekódují ani znovu nekomprimují, takže
	 * úklid nesnižuje kvalitu — přepisuje se jen struktura souboru a výsledek
	 * se před zápisem ověří načtením a porovnáním rozměrů.
	 *
	 * Soubory s barevným profilem, jehož odstranění by posunulo barvy
	 * (Display P3, DCI-P3, Apple Wide Color, tiskové CMYK profily), se
	 * přeskakují a vypíšou na konci. Jejich převod do sRGB je jiná operace.
	 *
	 * Bez přepínače --write se nic nezapisuje, jen se spočítá, co by úklid
	 * udělal.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Skutečně přepsat soubory. Bez něj běží příkaz nanečisto.
	 *
	 * [--path=<cesta>]
	 * : Podadresář v uploads, jinak celá knihovna. Např. --path=2024/03
	 *
	 * [--limit=<pocet>]
	 * : Zpracovat nejvýše tolik souborů.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct clean_image_meta
	 *     wp kct clean_image_meta --path=2024/03
	 *     wp kct clean_image_meta --write
	 *
	 * @when after_wp_load
	 */
	public function clean_image_meta( $args, $assoc_args ) {
		$write = ! empty( $assoc_args['write'] );
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;

		$uploads = wp_get_upload_dir();
		$root    = untrailingslashit( $uploads['basedir'] );

		if ( ! empty( $assoc_args['path'] ) ) {
			$root .= '/' . trim( (string) $assoc_args['path'], '/' );
		}

		if ( ! is_dir( $root ) ) {
			WP_CLI::error( sprintf( __( 'Adresář %s neexistuje.', 'kct' ), $root ) );
		}

		$cleaner = kct_container()->get( MetadataCleaner::class );
		$files   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );

		$counts  = array( 'cleaned' => 0, 'unchanged' => 0, 'skipped' => 0, 'error' => 0 );
		$before  = 0;
		$after   = 0;
		$done    = 0;
		$top     = array();
		$reasons = array();

		WP_CLI::log( $write
			? sprintf( __( 'Zapisuji do %s …', 'kct' ), $root )
			: sprintf( __( 'Nanečisto nad %s (zápis zapneš přepínačem --write) …', 'kct' ), $root ) );

		foreach ( $files as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$ext = strtolower( $file->getExtension() );

			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				continue;
			}

			$path   = $file->getPathname();
			$result = $cleaner->clean( $path, $write );

			$counts[ $result['status'] ]++;

			if ( 'cleaned' === $result['status'] ) {
				$before += $result['before'];
				$after  += $result['after'];
				$top[]   = array( $result['before'] - $result['after'], str_replace( $root . '/', '', $path ) );
			}

			if ( 'skipped' === $result['status'] && $result['note'] ) {
				$reasons[ $result['note'] ] = ( $reasons[ $result['note'] ] ?? 0 ) + 1;
			}

			$done++;

			if ( $limit && $done >= $limit ) {
				break;
			}
		}

		usort( $top, static fn( $a, $b ) => $b[0] <=> $a[0] );

		WP_CLI::log( '' );

		foreach ( array_slice( $top, 0, 10 ) as $row ) {
			WP_CLI::log( sprintf( '  %8s   %s', size_format( $row[0] ), $row[1] ) );
		}

		if ( $reasons ) {
			WP_CLI::log( '' );
			WP_CLI::log( __( 'Přeskočeno (potřebují převod barev, řeší se zvlášť):', 'kct' ) );

			arsort( $reasons );

			foreach ( $reasons as $reason => $count ) {
				WP_CLI::log( sprintf( '  %5d×  %s', $count, $reason ) );
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( sprintf(
			/* translators: 1: počet upravených, 2: beze změny, 3: přeskočených, 4: chyb. */
			__( 'upraveno %1$d, beze změny %2$d, přeskočeno %3$d, chyb %4$d', 'kct' ),
			$counts['cleaned'],
			$counts['unchanged'],
			$counts['skipped'],
			$counts['error']
		) );

		$message = sprintf(
			/* translators: 1: původní objem, 2: nový objem, 3: úspora, 4: procenta. */
			__( 'Objem upravených souborů: %1$s → %2$s, úspora %3$s (%4$s).', 'kct' ),
			size_format( $before, 1 ),
			size_format( $after, 1 ),
			size_format( $before - $after, 1 ),
			$before ? round( ( $before - $after ) / $before * 100 ) . ' %' : '0 %'
		);

		if ( $write ) {
			WP_CLI::success( $message );
		} else {
			WP_CLI::log( $message );
			WP_CLI::log( __( 'Nic se nezapsalo. Spusť znovu s --write.', 'kct' ) );
		}
	}

	/**
	 * Smaže plné verze obrázků, které si jádro nechává vedle zmenšených.
	 *
	 * Když je nahraný snímek delší než strop rozměrů, WordPress vyrobí kopii
	 * `…-scaled.…`, tu servíruje, a původní soubor nechá ležet na disku. Nikdo
	 * ho nečte — jen zabírá místo a nafukuje zálohy. Nová nahrávání to řeší
	 * sama (Features\ImageUploads), tenhle příkaz uklidí, co se nasbíralo dřív.
	 *
	 * Prochází všechny weby sítě. Bez přepínače --write jen spočítá, co by se
	 * smazalo.
	 *
	 * POZOR: mazání je nevratné. Plné verze zpátky nedostaneš jinak než ze zálohy.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Skutečně smazat. Bez něj běží příkaz nanečisto.
	 *
	 * [--site=<id>]
	 * : Jen jeden web sítě podle ID, jinak všechny.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct drop_kept_originals
	 *     wp kct drop_kept_originals --write
	 *
	 * @when after_wp_load
	 */
	public function drop_kept_originals( $args, $assoc_args ) {
		global $wpdb;

		$write   = ! empty( $assoc_args['write'] );
		$only    = isset( $assoc_args['site'] ) ? (int) $assoc_args['site'] : 0;
		$uploads = kct_container()->get( \Kct\Features\ImageUploads::class );
		$sites   = $only ? array( $only ) : get_sites( array( 'number' => 0, 'fields' => 'ids' ) );

		$total_n = 0;
		$total_b = 0;
		$missing = 0;

		WP_CLI::log( $write
			? __( 'Mažu ponechané originály …', 'kct' )
			: __( 'Nanečisto (mazání zapneš přepínačem --write) …', 'kct' ) );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			$ids = $wpdb->get_col(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE '%original_image%'"
			);

			$n = 0;
			$b = 0;

			foreach ( $ids as $id ) {
				$id       = (int) $id;
				$metadata = wp_get_attachment_metadata( $id );

				if ( ! is_array( $metadata ) || empty( $metadata['original_image'] ) ) {
					continue;
				}

				$path = wp_get_original_image_path( $id );

				if ( ! $path || ! file_exists( $path ) ) {
					// Soubor už není, ale klíč na něj pořád ukazuje. Odkaz na
					// originál v knihovně médií by na tohle vracel chybu.
					$missing++;

					if ( $write ) {
						unset( $metadata['original_image'] );
						wp_update_attachment_metadata( $id, $metadata );
					}

					continue;
				}

				$b += (int) filesize( $path );
				$n++;

				if ( $write ) {
					wp_update_attachment_metadata( $id, $uploads->drop_original( $metadata, $id ) );
				}
			}

			if ( $n ) {
				WP_CLI::log( sprintf( '  %-30s %5d × %10s', parse_url( get_home_url( $site_id ), PHP_URL_HOST ), $n, size_format( $b, 1 ) ) );
			}

			$total_n += $n;
			$total_b += $b;

			restore_current_blog();
		}

		if ( $missing ) {
			WP_CLI::log( sprintf(
				/* translators: %d: počet příloh. */
				__( 'U %d příloh odkaz na originál ukazoval na neexistující soubor.', 'kct' ),
				$missing
			) );
		}

		$message = sprintf(
			/* translators: 1: počet souborů, 2: uvolněné místo. */
			__( 'Originálů: %1$d, místo: %2$s.', 'kct' ),
			$total_n,
			size_format( $total_b, 1 )
		);

		if ( $write ) {
			WP_CLI::success( $message );
		} else {
			WP_CLI::log( $message );
			WP_CLI::log( __( 'Nic se nesmazalo. Spusť znovu s --write.', 'kct' ) );
		}
	}
}
