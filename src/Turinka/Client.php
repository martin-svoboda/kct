<?php

namespace Kct\Turinka;

/**
 * Tenký klient nad API Turinky.
 *
 * Nezná WordPress hooky ani post types — jen mluví s API a překládá odpověď na
 * jednotné pole. Stejná role jako Facebook\GraphClient.
 *
 * Chyby Turinka vrací podle RFC 7807 (`title`, `detail`, u validace `errors`),
 * takže hlášky jsou už česky a plugin je jen podává dál. Přepisovat je vlastními
 * slovy by znamenalo udržovat druhou sadu textů o pravidlech, která nejsou naše.
 */
class Client {
	const TIMEOUT = 15;

	/**
	 * Čtení, které běží při vykreslování stránky akce, si tak dlouho čekat
	 * nesmí — návštěvník by koukal na bílou stránku kvůli widgetu.
	 */
	const RENDER_TIMEOUT = 2;

	public function __construct( private Config $config ) {
	}

	/**
	 * Žádost o přístup — veřejná, token v ní ještě nemá kdo předložit.
	 *
	 * Vlastníka pošle web podle svého kódu: `department_number` u odboru,
	 * `region_number` u oblasti. Nikdy obojí — web patří jedné organizaci a
	 * Turinka obojí naráz odmítá.
	 *
	 * @param string $owner_field 'department_number' nebo 'region_number'
	 */
	public function request_access( string $domain, string $owner_field, string $owner_number ): array {
		return $this->request( 'POST', '/api/v1/integrations/request', array(
			'body' => array(
				'domain'      => $domain,
				$owner_field  => $owner_number,
			),
		) );
	}

	/**
	 * Identita podle předloženého tokenu — doména, vlastník a rozsah oprávnění.
	 *
	 * Používá se dvakrát: na tlačítko „Ověřit připojení" a při převzetí tokenu,
	 * kde je to jediný způsob, jak poznat, že ho poslala opravdu Turinka.
	 */
	public function me( string $token ): array {
		return $this->request( 'GET', '/api/v1/integrations/me', array( 'token' => $token ) );
	}

	/**
	 * Obsah, který web dodává k akci známé z feedu KČT.
	 *
	 * `PUT`, protože je to nahrazení obsahu, ne přidání dalšího — opakovaný
	 * zápis téhož nemá žádný následek.
	 *
	 * @param string $kct_id Číslo akce v centrální databázi KČT (`db_id`).
	 */
	public function write_content( string $token, string $kct_id, array $payload ): array {
		return $this->request(
			'PUT',
			'/api/v1/integrations/events/' . rawurlencode( $kct_id ) . '/content',
			array( 'token' => $token, 'body' => $payload )
		);
	}

	/**
	 * Vlastní akce webu — akce, kterou centrální databáze KČT nezná.
	 *
	 * Bez id v cestě schválně: akci identifikuje dvojice (klient, `source_id`
	 * v těle), protože „42" jednoho odboru není „42" druhého. Opakované volání
	 * s týmž `source_id` je proto aktualizace, ne druhá akce.
	 */
	public function write_event( string $token, array $payload ): array {
		return $this->request( 'PUT', '/api/v1/integrations/events', array(
			'token' => $token,
			'body'  => $payload,
		) );
	}

	/**
	 * Detail akce podle čísla z centrální databáze KČT — veřejné, bez tokenu.
	 *
	 * Plugin z něj potřebuje jediné: slug, kterým se skládá adresa widgetu.
	 */
	public function event_by_kct_id( string $kct_id ): array {
		return $this->request(
			'GET',
			'/api/v1/events/kct/' . rawurlencode( $kct_id ),
			array( 'timeout' => self::RENDER_TIMEOUT )
		);
	}

	/**
	 * @param array{token?: string, body?: array, timeout?: int} $args
	 *
	 * @return array{ok: bool, status: int, data: array, body: array, message: string, errors: array}
	 */
	private function request( string $method, string $path, array $args = array() ): array {
		$options = array(
			'method'  => $method,
			'timeout' => $args['timeout'] ?? self::TIMEOUT,
			'headers' => array( 'Accept' => 'application/json' ),
		);

		if ( ! empty( $args['token'] ) ) {
			$options['headers']['Authorization'] = 'Bearer ' . $args['token'];
		}

		if ( isset( $args['body'] ) ) {
			$options['headers']['Content-Type'] = 'application/json';
			$options['body']                    = wp_json_encode( $args['body'] );
		}

		$host = $this->config->host_header();
		if ( '' !== $host ) {
			$options['headers']['Host'] = $host;
		}

		return $this->parse( wp_remote_request( $this->config->base_url() . $path, $options ) );
	}

	/**
	 * @param array|\WP_Error $response
	 */
	private function parse( $response ): array {
		$result = array(
			'ok'      => false,
			'status'  => 0,
			'data'    => array(),
			'body'    => array(),
			'message' => '',
			'errors'  => array(),
		);

		if ( is_wp_error( $response ) ) {
			$result['message'] = $response->get_error_message();

			return $result;
		}

		$result['status'] = (int) wp_remote_retrieve_response_code( $response );
		$body             = json_decode( wp_remote_retrieve_body( $response ), true );
		$result['body']   = is_array( $body ) ? $body : array();

		if ( $result['status'] >= 200 && $result['status'] < 300 ) {
			$result['ok']   = true;
			$result['data'] = is_array( $result['body']['data'] ?? null ) ? $result['body']['data'] : array();

			return $result;
		}

		// Prázdné nebo nesrozumitelné tělo (chybová stránka od WAF, špatná
		// adresa) se od chyby vrácené Turinkou pozná jen podle HTTP stavu.
		$detail = $result['body']['detail'] ?? $result['body']['title'] ?? null;

		$result['message'] = is_scalar( $detail )
			? (string) $detail
			: sprintf( __( 'Turinka odpověděla HTTP %d.', 'kct' ), $result['status'] );

		if ( is_array( $result['body']['errors'] ?? null ) ) {
			$result['errors'] = $result['body']['errors'];
		}

		return $result;
	}
}
