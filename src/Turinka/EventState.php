<?php

namespace Kct\Turinka;

use Kct\Repositories\SettingsRepository;

/**
 * Jediné místo, které zná meta klíče propojení s Turinkou.
 *
 * Volba redakce (`turinka_state`) je běžné pole akce, proto bez předpony —
 * stejně jako db_id, year nebo place ve stejném metaboxu. Provozní stav má
 * předponu `kct_turinka_` a je skrytý, stejně jako u sdílení na Facebook.
 */
class EventState {
	/** Volba redakce: posílat, a jak. */
	const META_STATE = 'turinka_state';

	/** Otisk naposledy úspěšně odeslaného payloadu. */
	const META_HASH = 'kct_turinka_hash';

	/** Slug a adresa akce v Turince — z odpovědi na zápis, pro widget. */
	const META_SLUG = 'kct_turinka_slug';
	const META_URL  = 'kct_turinka_url';

	const META_ERROR = 'kct_turinka_error';
	const META_TIME  = 'kct_turinka_sent_at';

	/** Nejdelší uložená délka chybové zprávy ve znacích. */
	const MAX_ERROR_LENGTH = 500;

	const STATE_OFF      = 'off';
	const STATE_PUBLIC   = 'public';
	const STATE_UNLISTED = 'unlisted';

	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * Přípustné volby a jejich popis pro administraci.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		return array(
			self::STATE_OFF      => __( 'Neposílat', 'kct' ),
			self::STATE_PUBLIC   => __( 'Veřejně', 'kct' ),
			self::STATE_UNLISTED => __( 'Jen na odkaz (mimo výpisy)', 'kct' ),
		);
	}

	/**
	 * Co si redakce u téhle akce přeje.
	 *
	 * Bez uložené hodnoty platí výchozí stav z nastavení webu — akce založené
	 * dřív, než se web propojil, se tak řídí tím, co správce nastavil, a nemusí
	 * se procházet jedna po druhé.
	 */
	public function desired( int $post_id ): string {
		$value = (string) get_post_meta( $post_id, self::META_STATE, true );

		if ( ! isset( self::choices()[ $value ] ) ) {
			$value = (string) $this->settings->get_option( 'turinka_default_state' );
		}

		return isset( self::choices()[ $value ] ) ? $value : self::STATE_OFF;
	}

	/**
	 * Byla akce do Turinky někdy úspěšně odeslána?
	 */
	public function is_sent( int $post_id ): bool {
		return '' !== (string) get_post_meta( $post_id, self::META_HASH, true );
	}

	/**
	 * Otisk posledního úspěšného odeslání — podle něj se pozná, že se nic
	 * nezměnilo a volat API nemá smysl.
	 */
	public function hash( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_HASH, true );
	}

	public function slug( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_SLUG, true );
	}

	public function url( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_URL, true );
	}

	public function error( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_ERROR, true );
	}

	/**
	 * Poznamená úspěšné odeslání a zahodí předchozí chybu.
	 *
	 * @param array $data Tělo `data` z odpovědi Turinky; slug a url jen u akcí,
	 *                    které je vracejí (vlastní akce), u obsahu chybí.
	 */
	public function mark_sent( int $post_id, string $hash, array $data = array() ): void {
		update_post_meta( $post_id, self::META_HASH, $hash );
		update_post_meta( $post_id, self::META_TIME, time() );
		delete_post_meta( $post_id, self::META_ERROR );

		if ( ! empty( $data['slug'] ) ) {
			update_post_meta( $post_id, self::META_SLUG, (string) $data['slug'] );
		}

		if ( ! empty( $data['url'] ) ) {
			update_post_meta( $post_id, self::META_URL, (string) $data['url'] );
		}
	}

	/**
	 * Uloží důvod, proč se odeslání nepovedlo.
	 *
	 * Otisk se přitom zahazuje: příští uložení akce má zkusit odeslat znovu,
	 * i když se obsah nezměnil. Bez toho by se web po jedné chybě už nikdy
	 * sám nepokusil.
	 */
	public function mark_error( int $post_id, string $message ): void {
		update_post_meta( $post_id, self::META_ERROR, mb_substr( $message, 0, self::MAX_ERROR_LENGTH ) );
		delete_post_meta( $post_id, self::META_HASH );
	}
}
