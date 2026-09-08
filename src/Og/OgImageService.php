<?php

namespace Kct\Og;

use Throwable;

/**
 * Spojuje dohromady klíčování, úložiště a kreslení.
 *
 * Volající předá hotová data karty a předponu názvu souboru; služba spočítá
 * hash, a když soubor s tím hashem existuje, vrátí ho bez kreslení. Jinak
 * kartu vyrenderuje a uloží.
 *
 * Kterou kartu kreslí, řeší jen výběr v public metodách — zbytek jede přes
 * rozhraní Card, takže přidání dalšího formátu se téhle třídy skoro nedotkne.
 *
 * Vrací null, kdykoli se cokoli nepovede — volající pak spadne na dnešní
 * chování. Sdílecí obrázek je ozdoba, ne funkce, a nesmí nic shodit.
 */
class OgImageService {

	public function __construct(
		private OgImageRenderer $renderer,
		private OgImageStore $store,
		private PostCard $post_card,
		private EventCard $event_card,
		private PostPosterCard $post_poster,
		private EventPosterCard $event_poster
	) {
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function post( array $data, string $prefix ): ?array {
		return $this->image( $this->post_card, $data, $prefix );
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function event( array $data, string $prefix ): ?array {
		return $this->image( $this->event_card, $data, $prefix );
	}

	/**
	 * Karta 4:5 příspěvku pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_post( array $data, string $prefix ): ?array {
		return $this->image( $this->post_poster, $data, 'social-' . $prefix );
	}

	/**
	 * Karta 4:5 akce pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_event( array $data, string $prefix ): ?array {
		return $this->image( $this->event_poster, $data, 'social-' . $prefix );
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	private function image( Card $card, array $data, string $prefix ): ?array {
		if ( ! $this->renderer->available() ) {
			return null;
		}

		$ext  = $card->extension();
		$hash = $this->hash( $data, $card );
		$url  = $this->store->url( $prefix, $hash, $ext );

		if ( null === $url ) {
			try {
				$url = $this->store->save( $card->render( $data ), $prefix, $hash, $ext );
			} catch ( Throwable $e ) {
				// Chyba kreslení nesmí shodit stránku. Zaloguje se a volající
				// spadne na dnešní obrázek.
				error_log( sprintf( 'kct: sdílecí obrázek %s selhal: %s', $prefix, $e->getMessage() ) );

				return null;
			}
		}

		if ( null === $url ) {
			return null;
		}

		return array(
			'url'    => $url,
			'width'  => $card->width(),
			'height' => $card->height(),
		);
	}

	/**
	 * Otisk vstupů karty.
	 *
	 * Do hashe jde všechno, co se kreslí, plus rozměry karty a verze
	 * vykreslování. Změna obsahu i změna designu tak vyrobí jiný název
	 * souboru — starý se smaže a Facebook si sáhne pro nový, protože se změní
	 * i URL v og:image.
	 */
	private function hash( array $data, Card $card ): string {
		$key = wp_json_encode( $data ) . '|' . $card->width() . 'x' . $card->height()
			. '|v' . OgImageRenderer::RENDER_VERSION;

		return substr( sha1( $key ), 0, 12 );
	}
}
