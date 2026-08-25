<?php

namespace Kct\Facebook;

use WP_Post;

/**
 * Vypisuje stav odeslání na Facebook v editoru.
 *
 * Zobrazí se jen u příspěvku, který už byl odeslán nebo u kterého odeslání
 * selhalo — u nového příspěvku by prázdný box jen překážel.
 *
 * Pozor: blokový editor (Gutenberg) po uložení metaboxy sám nepřekresluje.
 * Odeslání navíc běží v cronu mimo aktuální request (viz FacebookShare,
 * Task 5) — tenhle box se proto po prvním odeslání objeví, nebo se po dalším
 * pokusu aktualizuje, až po ručním obnovení stránky editoru.
 */
class ShareMetabox {
	const ID = 'kct_facebook_status';

	/**
	 * @param ShareState $state      Čtení stavu odeslání.
	 * @param string[]   $post_types Typy příspěvků, u kterých se box registruje.
	 */
	public function __construct(
		private ShareState $state,
		private array $post_types
	) {
		add_action( 'add_meta_boxes', array( $this, 'register' ), 10, 2 );
	}

	/**
	 * Zaregistruje metabox, jen je-li u daného příspěvku co zobrazit.
	 *
	 * $post není typovaný jako WP_Post, protože háček `add_meta_boxes` běží
	 * i na obrazovce úpravy komentáře a tam nese WP_Comment jako druhý
	 * argument — pevný typ by tam skončil fatální chybou dřív, než proběhne
	 * kontrola instanceof níž.
	 */
	public function register( string $post_type, $post ): void {
		if ( ! in_array( $post_type, $this->post_types, true ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! $this->state->is_shared( $post->ID ) && ! $this->state->error( $post->ID ) ) {
			return;
		}

		add_meta_box(
			self::ID,
			__( 'Facebook — stav odeslání', 'kct' ),
			array( $this, 'render' ),
			$post_type,
			'side',
			'low'
		);
	}

	/**
	 * Vykreslí obsah metaboxu — potvrzení odeslání s odkazem na Facebook,
	 * nebo chybovou hlášku s tlačítkem na opakování pokusu.
	 */
	public function render( WP_Post $post ): void {
		if ( $this->state->is_shared( $post->ID ) ) {
			$this->render_shared( $post );

			return;
		}

		$this->render_error( $post );
	}

	/**
	 * Potvrzení odeslání a odkaz na zveřejněný příspěvek.
	 *
	 * Řádek s datem se vynechá, když META_TIME chybí (shared_at() vrátí 0) —
	 * jinak by wp_date() vypsalo zavádějící "1. 1. 1970".
	 */
	private function render_shared( WP_Post $post ): void {
		$shared_at = $this->state->shared_at( $post->ID );

		echo '<p>';

		if ( 0 !== $shared_at ) {
			$shared_label = sprintf(
				/* translators: %s: datum a čas odeslání příspěvku na Facebook. */
				__( 'Odesláno %s', 'kct' ),
				wp_date( 'j. n. Y H:i', $shared_at )
			);

			printf( '%s<br>', esc_html( $shared_label ) );
		}

		printf(
			'<a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( 'https://www.facebook.com/' . $this->state->fb_post_id( $post->ID ) ),
			esc_html__( 'Zobrazit příspěvek na Facebooku', 'kct' )
		);
	}

	/**
	 * Chybová hláška a tlačítko na opakování pokusu.
	 *
	 * Tlačítko se vypíše, jen když má aktuální uživatel právo příspěvek
	 * editovat — odkaz nemá slibovat akci, kterou nejde provést.
	 */
	private function render_error( WP_Post $post ): void {
		$error = $this->state->error( $post->ID );

		$error_label = sprintf(
			/* translators: %s: chybová zpráva vrácená Facebook API. */
			__( 'Odeslání selhalo: %s', 'kct' ),
			$error['message'] ?? ''
		);

		printf(
			'<div class="notice notice-error inline"><p>%s</p></div>',
			esc_html( $error_label )
		);

		// Kód 0 znamená, že se spojení nepodařilo dokončit — požadavek ale mohl
		// na straně Facebooku uspět, takže se takové odeslání automaticky
		// neopakuje (viz FacebookShare::handle_failure()) a rozhodnutí zůstává
		// na člověku.
		if ( isset( $error['code'] ) && 0 === (int) $error['code'] ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'Spojení s Facebookem se nedokončilo, proto se odeslání samo neopakuje — příspěvek na zdi přesto mohl vzniknout. Než ho pošlete znovu, podívejte se na stránku na Facebooku.', 'kct' )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		printf(
			'<p><a href="%s" class="button">%s</a></p>',
			wp_nonce_url(
				add_query_arg(
					array(
						'kct-action' => 'fb_retry',
						'post'       => $post->ID,
					),
					admin_url( 'index.php' )
				),
				'kct-fb-retry-' . $post->ID
			),
			esc_html__( 'Zkusit znovu', 'kct' )
		);
	}
}
