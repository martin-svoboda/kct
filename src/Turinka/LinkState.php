<?php

namespace Kct\Turinka;

/**
 * Jediné místo, které zná meta klíče propojení příspěvku s položkou v Turince.
 *
 * Provozní stav má předponu `kct_turinka_` a je skrytý — mění ho plugin, ne
 * člověk. Pro trasu se třída používá přímo: u trasy žádná volba redakce není,
 * trasa je v Turince tehdy a jen tehdy, když ji tam redakce formulářem
 * založila. Akce nad tím mají volbu navíc, proto `EventState`.
 */
class LinkState {
	/** Otisk naposledy úspěšně odeslaného payloadu. */
	const META_HASH = 'kct_turinka_hash';

	/** Identita položky v Turince — z odpovědi, pro odkazy a widget. */
	const META_ID   = 'kct_turinka_id';
	const META_SLUG = 'kct_turinka_slug';
	const META_URL  = 'kct_turinka_url';

	const META_ERROR = 'kct_turinka_error';
	const META_TIME  = 'kct_turinka_sent_at';

	/** Nejdelší uložená délka chybové zprávy ve znacích. */
	const MAX_ERROR_LENGTH = 500;

	/**
	 * Je příspěvek propojený s položkou v Turince?
	 *
	 * Pozná se podle id, ne podle otisku: otisk říká „něco jsme odeslali“,
	 * kdežto propojení je to, co dovoluje zápis vůbec zkusit.
	 */
	public function is_linked( int $post_id ): bool {
		return $this->turinka_id( $post_id ) > 0;
	}

	public function turinka_id( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META_ID, true );
	}

	/**
	 * Byl příspěvek do Turinky někdy úspěšně odeslán?
	 */
	public function is_sent( int $post_id ): bool {
		return '' !== $this->hash( $post_id );
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

	public function sent_at( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META_TIME, true );
	}

	/**
	 * Poznamená úspěšné odeslání a zahodí předchozí chybu.
	 *
	 * @param array $data Tělo `data` z odpovědi Turinky; id, slug a url jen
	 *                    tam, kde je odpověď vrací.
	 */
	public function mark_sent( int $post_id, string $hash, array $data = array() ): void {
		update_post_meta( $post_id, self::META_HASH, $hash );
		update_post_meta( $post_id, self::META_TIME, time() );
		delete_post_meta( $post_id, self::META_ERROR );

		$this->remember( $post_id, $data );
	}

	/**
	 * Uloží identitu položky bez toho, aby tvrdila, že se něco odeslalo.
	 *
	 * Potřebuje to formulář: trasu založila Turinka, plugin ji jen dočetl a
	 * text jí teprve pošle.
	 */
	public function remember( int $post_id, array $data ): void {
		$keys = array(
			self::META_ID   => 'id',
			self::META_SLUG => 'slug',
			self::META_URL  => 'url',
		);

		foreach ( $keys as $meta => $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				update_post_meta( $post_id, $meta, (string) $data[ $key ] );
			}
		}
	}

	/**
	 * Uloží důvod, proč se odeslání nepovedlo.
	 *
	 * Otisk se přitom zahazuje: příští uložení má zkusit odeslat znovu, i když
	 * se obsah nezměnil. Bez toho by se web po jedné chybě už nikdy sám
	 * nepokusil.
	 */
	public function mark_error( int $post_id, string $message ): void {
		update_post_meta( $post_id, self::META_ERROR, mb_substr( $message, 0, self::MAX_ERROR_LENGTH ) );
		delete_post_meta( $post_id, self::META_HASH );
	}

	public function clear_error( int $post_id ): void {
		delete_post_meta( $post_id, self::META_ERROR );
	}

	/**
	 * Zapomene celé propojení.
	 *
	 * Volá se po odebrání položky z Turinky a po odpovědi 404 na zápis —
	 * položka, kterou Turinka nezná, nemá na webu co držet za stav.
	 */
	public function forget( int $post_id ): void {
		$metas = array(
			self::META_HASH,
			self::META_ID,
			self::META_SLUG,
			self::META_URL,
			self::META_ERROR,
			self::META_TIME,
		);

		foreach ( $metas as $meta ) {
			delete_post_meta( $post_id, $meta );
		}
	}
}
