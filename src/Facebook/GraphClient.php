<?php

namespace Kct\Facebook;

/**
 * Tenký klient nad Facebook Graph API.
 *
 * Nezná WordPress hooky ani post types — jen mluví s API a překládá odpověď
 * na jednotné pole array{ok: bool, ...}.
 */
class GraphClient {
	/**
	 * Verze API. Meta vyřazuje verze zhruba po dvou letech — při aktualizaci
	 * ověř aktuální verzi v dokumentaci Graph API.
	 */
	const API_VERSION = 'v21.0';

	const API_URL = 'https://graph.facebook.com/';

	const TIMEOUT = 20;

	/** Kód chyby Graph API pro neplatný nebo expirovaný token. */
	const ERROR_INVALID_TOKEN = 190;

	/**
	 * Publikuje příspěvek na zeď stránky.
	 *
	 * Obrázek se připojuje přes `attached_media` jako už nahraná fotka, ne
	 * adresou — viz upload_photo(), proč to jde na dvakrát.
	 *
	 * `link` a `photo_id` se vylučují: odkaz si Facebook rozbalí do náhledové
	 * karty, a ta nemá kam jít, když příspěvek nese vlastní fotku.
	 *
	 * @param string|null $photo_id ID fotky nahrané přes upload_photo().
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function publish( string $page_id, string $token, string $message, ?string $link = null, ?string $photo_id = null ): array {
		$body = array(
			'message'      => $message,
			'access_token' => $token,
		);

		if ( $photo_id ) {
			// Indexovaný zápis, ne JSON pole — pole v jedné hodnotě chce
			// Graph API Explorer, běžné volání indexy.
			$body['attached_media[0]'] = wp_json_encode( array( 'media_fbid' => $photo_id ) );
		} elseif ( $link ) {
			$body['link'] = $link;
		}

		$response = wp_remote_post(
			self::API_URL . self::API_VERSION . '/' . $page_id . '/feed',
			array(
				'timeout' => self::TIMEOUT,
				'body'    => $body,
			)
		);

		return $this->parse( $response, 'id' );
	}

	/**
	 * Nahraje fotku na stránku, ale nepublikuje ji.
	 *
	 * Tohle je první polovina sdílení fotkou. Druhá je publish() s vráceným
	 * ID v `attached_media`. Na dvakrát to je proto, že fotka publikovaná
	 * rovnou přes `/photos` nevytvoří běžný příspěvek na zdi, ale fotopříspěvek
	 * (`status_type: added_photos`) — ten skončí v albu stránky a ve výpisu
	 * příspěvků se čtenářům většinou vůbec neukáže. Sdílení tím ztrácí smysl:
	 * příspěvek technicky vznikne, ale nikdo se k němu nedostane.
	 *
	 * Obrázek se nenahrává po bajtech — předá se jeho veřejná adresa a Facebook
	 * si ho stáhne sám. Z toho plyne, že se to nedá vyzkoušet z lokálního
	 * vývoje — na adresu, která není dostupná z internetu, Facebook nedosáhne.
	 *
	 * Popisek se sem nedává: text nese až příspěvek z publish(). Parametr
	 * `message` je tu navíc podle dokumentace zastaralý.
	 *
	 * Nepublikovanou fotku drží Facebook zhruba 24 hodin a pak ji maže. Pro
	 * sdílení, kde druhý krok následuje hned, to není omezení — a když druhý
	 * krok selže, je to naopak úklid zadarmo.
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function upload_photo( string $page_id, string $token, string $image_url ): array {
		$response = wp_remote_post(
			self::API_URL . self::API_VERSION . '/' . $page_id . '/photos',
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'url'          => $image_url,
					'published'    => 'false',
					'access_token' => $token,
				),
			)
		);

		return $this->parse( $response, 'id' );
	}

	/**
	 * Ověří token a vrátí název připojené stránky.
	 *
	 * Token se posílá v hlavičce Authorization, ne v query stringu — ten by
	 * skončil nezredigovaný v panelu Query Monitoru (háček `http_api_debug`),
	 * v access logu proxy a v URL případného redirectu.
	 *
	 * @return array{ok: bool, id?: string, name?: string, code?: int, message?: string}
	 */
	public function verify( string $token ): array {
		$response = wp_remote_get(
			add_query_arg( array( 'fields' => 'id,name' ), self::API_URL . self::API_VERSION . '/me' ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		return $this->parse( $response, 'name' );
	}

	/**
	 * @param array|\WP_Error $response
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	private function parse( $response, string $expected_key ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return array(
				'ok'      => false,
				// Prázdné tělo nebo nesrozumitelná odpověď (HTML od WAF apod.)
				// se pozná od chyby vrácené Facebookem podle HTTP stavu.
				'code'    => (int) wp_remote_retrieve_response_code( $response ),
				'message' => __( 'Neplatná odpověď Facebook API.', 'kct' ),
			);
		}

		if ( isset( $data['error'] ) ) {
			$message = $data['error']['message'] ?? null;

			return array(
				'ok'      => false,
				'code'    => (int) ( $data['error']['code'] ?? 0 ),
				'message' => is_scalar( $message ) ? (string) $message : __( 'Neznámá chyba.', 'kct' ),
			);
		}

		if ( ! isset( $data[ $expected_key ] ) ) {
			return array(
				'ok'      => false,
				'code'    => (int) wp_remote_retrieve_response_code( $response ),
				'message' => __( 'Odpověď Facebook API neobsahuje očekávaná data.', 'kct' ),
			);
		}

		// Sestaveno explicitně, ne array_merge() s $data — odpověď od Facebooku
		// by jinak mohla přepsat 'ok' nebo vrátit typy mimo deklarovaný tvar.
		$result = array(
			'ok'          => true,
			$expected_key => (string) $data[ $expected_key ],
		);

		if ( isset( $data['id'] ) ) {
			$result['id'] = (string) $data['id'];
		}

		return $result;
	}
}
