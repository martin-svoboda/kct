<?php

namespace Kct\Facebook;

use Kct\Repositories\DbEventRepository;

/**
 * Stav odeslání databázových akcí.
 *
 * Drží ho sloupec `fb_share` v `wp_db_events`, JSON klíčovaný ID webu.
 * Tabulka je jedna pro celou síť a tatáž akce se objevuje na oblastním
 * i odborovém webu, takže stav musí být per web.
 *
 * ČTE se přes repozitář (ten si umí přepnout na web 1, kde tabulka fyzicky
 * je). ZAPISUJE se vlastním SQL, protože přes model by to bylo
 * „načti, uprav, ulož" — a dva weby zpracovávající tentýž řádek současně by si
 * zápis přepsaly. Ztracený záznam „odesláno" znamená odeslání podruhé, tedy
 * duplicitní příspěvek na Facebooku.
 */
class DbShareState implements ShareStore {

	/** Předpona option se zámkem odesílání. */
	private const LOCK_PREFIX = 'kct_fb_db_sending_';

	/** Jak dlouho zámek platí, než ho převezme další běh. */
	private const LOCK_TTL = 300;

	public function __construct( private DbEventRepository $repository ) {
	}

	public function is_shared( int $id ): bool {
		return '' !== $this->value( $id, 'fb' );
	}

	public function fb_post_id( int $id ): string {
		return $this->value( $id, 'fb' );
	}

	public function shared_at( int $id ): int {
		return (int) $this->value( $id, 'sent' );
	}

	/**
	 * Vypnuto u konkrétní akce přebíjí výchozí hodnotu z nastavení.
	 */
	public function should_share( int $id, bool $default ): bool {
		return empty( $this->state( $id )['off'] ) && $default;
	}

	/** Chyba posledního pokusu, nebo prázdné pole. */
	public function error( int $id ): array {
		$state = $this->state( $id );

		return isset( $state['error'] ) && is_array( $state['error'] ) ? $state['error'] : array();
	}

	public function mark_shared( int $id, string $fb_post_id ): void {
		if ( '' === $fb_post_id ) {
			return;
		}

		$this->write( $id, array(
			'sent'  => time(),
			'fb'    => $fb_post_id,
			'error' => null,
		) );
	}

	public function mark_error( int $id, int $code, string $message ): void {
		$this->write( $id, array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
				'time'    => time(),
			),
		) );
	}

	/** Vypne nebo zapne odesílání u konkrétní akce. */
	public function set_disabled( int $id, bool $disabled ): void {
		$this->write( $id, array( 'off' => $disabled ? true : null ) );
	}

	public function is_disabled( int $id ): bool {
		return ! empty( $this->state( $id )['off'] );
	}

	/**
	 * Zámek se drží v option, ne ve sloupci.
	 *
	 * Sloupec je sdílený mezi weby a zámek je věc jednoho webu; navíc by se
	 * kvůli zámku psalo do tabulky při každém běhu úlohy, i když se nic
	 * neodesílá.
	 */
	public function claim( int $id ): bool {
		$key = self::LOCK_PREFIX . $id;
		$now = time();

		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}

		// Zámek po spadlém běhu se po vypršení TTL uvolní sám — jinak by se
		// akce už nikdy neodeslala.
		$since = (int) get_option( $key );

		if ( $since && ( $now - $since ) > self::LOCK_TTL ) {
			update_option( $key, $now, false );

			return true;
		}

		return false;
	}

	public function release( int $id ): void {
		delete_option( self::LOCK_PREFIX . $id );
	}

	/**
	 * Stav akce pro tenhle web.
	 *
	 * @return array<string, mixed>
	 */
	private function state( int $id ): array {
		$event = $this->repository->get_by_db_id( $id );

		if ( ! $event || ! is_array( $event->fb_share ) ) {
			return array();
		}

		$mine = $event->fb_share[ (string) get_current_blog_id() ] ?? array();

		return is_array( $mine ) ? $mine : array();
	}

	private function value( int $id, string $key ): string {
		$value = $this->state( $id )[ $key ] ?? '';

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Zapíše hodnoty pod klíč tohoto webu, cizí klíče nechá být.
	 *
	 * JSON_MERGE_PATCH slučuje na úrovni řádku, takže se nic nečte do PHP
	 * a dva weby si zápis nepřepíšou. Hodnota null klíč odstraní — tak se maže
	 * `off` i `error`.
	 *
	 * base_prefix, ne prefix: tabulka je jedna pro celou síť a jmenuje se
	 * wp_db_events; $wpdb->prefix je na webu 2 „wp_2_", tedy tabulka, která
	 * neexistuje.
	 *
	 * @param array<string, mixed> $values
	 */
	private function write( int $id, array $values ): void {
		global $wpdb;

		$patch = wp_json_encode( array( (string) get_current_blog_id() => $values ) );

		if ( false === $patch ) {
			return;
		}

		$table = $wpdb->base_prefix . DbEventRepository::$table_name;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET fb_share = JSON_MERGE_PATCH( COALESCE(fb_share, '{}'), %s ) WHERE db_id = %d",
				$patch,
				$id
			)
		);
	}
}
