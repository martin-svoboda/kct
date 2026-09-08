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
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function publish( string $page_id, string $token, string $message, ?string $link = null ): array {
		$body = array(
			'message'      => $message,
			'access_token' => $token,
		);

		if ( $link ) {
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
	 * Publikuje na zeď stránky fotku s popiskem.
	 *
	 * Obrázek se nenahrává — předá se jeho veřejná adresa a Facebook si ho
	 * stáhne sám. Z toho plyne, že se to nedá vyzkoušet z lokálního vývoje:
	 * na sokct.test Facebook nedosáhne.
	 *
	 * Endpoint vrací dvě různá ID: `id` je identifikátor fotky, `post_id`
	 * identifikátor příspěvku na zdi. Ukládá se ten druhý, protože z něj
	 * Facebook\ShareMetabox staví odkaz na příspěvek — s ID fotky by odkaz
	 * nefungoval, a to tiše, protože odeslání by proběhlo v pořádku. Volajícím
	 * se vrací pod klíčem `id`, aby se nemusely měnit.
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function publish_photo( string $page_id, string $token, string $message, string $image_url ): array {
		$response = wp_remote_post(
			self::API_URL . self::API_VERSION . '/' . $page_id . '/photos',
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'message'      => $message,
					'url'          => $image_url,
					'access_token' => $token,
				),
			)
		);

		$result = $this->parse( $response, 'post_id' );

		if ( ! empty( $result['ok'] ) ) {
			$result['id'] = $result['post_id'];
			unset( $result['post_id'] );
		}

		return $result;
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
