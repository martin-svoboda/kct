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
	 * Výpis položek jednoho typu.
	 *
	 * Jedna kostra pro akce, místa i trasy — odpověď má u všech tří stejná
	 * pole, takže se liší jen cesta a filtry.
	 *
	 * **Posílat se smí jen parametry, které výpis zná** — neznámý je 422
	 * s výčtem povolených, ne tiché ignorování.
	 *
	 * Výpis akcí vrací bez parametrů i proběhlé akce, nejnovější nahoře.
	 * Kalendář pro návštěvníka proto musí říct `period=upcoming`; inventura
	 * v administraci naopak chce všechno.
	 *
	 * @param string $type  `events` | `places` | `routes`.
	 * @param array  $query Filtry výpisu (`mine`, `page`, `per_page`, `period`…).
	 */
	public function listing( string $token, string $type, array $query = array() ): array {
		return $this->request(
			'GET',
			'/api/v1/integrations/' . rawurlencode( $type ) . ( $query ? '?' . http_build_query( $query ) : '' ),
			array( 'token' => $token )
		);
	}

	/**
	 * Detail položky podle id v Turince.
	 *
	 * Výpis nese jen to, co potřebuje karta; vazby na druhou stranu (trasy
	 * u akce, akce u trasy) jsou až v detailu.
	 *
	 * @param string $type `events` | `places` | `routes`.
	 */
	public function detail( string $token, string $type, int $id ): array {
		return $this->request(
			'GET',
			'/api/v1/integrations/' . rawurlencode( $type ) . '/' . $id,
			array( 'token' => $token )
		);
	}

	/**
	 * Vazba trasa ↔ akce.
	 *
	 * Jedna vazba, jedno volání: `PUT` vrací 201 při vzniku a 200, když už
	 * byla, takže opakování nevadí. Nahrazovací seznamy (`route_ids`,
	 * `event_ids`) se tím vyhýbáme — ty by uměly vynulovat i vazby, které
	 * udělal někdo jiný.
	 *
	 * Vazbu spravuje **pořadatel akce**: akce musí patřit webu, trasa může být
	 * cizí, jen viditelná. Jinak 403.
	 *
	 * @param array $link Trasa (`route_id` | `route_source_id`) a akce
	 *                    (`event_id` | `event_kct_id` | `event_source_id`).
	 */
	public function link_route_event( string $token, array $link ): array {
		return $this->request( 'PUT', '/api/v1/integrations/route-event-links', array(
			'token' => $token,
			'body'  => $link,
		) );
	}

	/**
	 * Zrušení vazby. 204 když zanikla, 404 když nebyla.
	 */
	public function unlink_route_event( string $token, array $link ): array {
		return $this->request(
			'DELETE',
			'/api/v1/integrations/route-event-links?' . http_build_query( $link ),
			array( 'token' => $token )
		);
	}

	/**
	 * Odebrání položky, kterou web do Turinky dodal.
	 *
	 * Odpověď říká, co se stalo: `archived` (položku web založil a Turinka ji
	 * schovala), `unlinked` (web se odpojil od cizí položky, ta zůstává) nebo
	 * `kept` (proběhlá akce zůstává lidem v deníku).
	 *
	 * @param string $type `events` | `places` | `routes`.
	 */
	public function delete_item( string $token, string $type, string $source_id ): array {
		return $this->request(
			'DELETE',
			'/api/v1/integrations/' . rawurlencode( $type ) . '/source/' . rawurlencode( $source_id ),
			array( 'token' => $token )
		);
	}

	/**
	 * Číselníky — hodnoty a jejich popisky pro všechny typy naráz.
	 */
	public function codelists( string $token ): array {
		return $this->request( 'GET', '/api/v1/integrations/codelists', array( 'token' => $token ) );
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
	 * Trasa z webu.
	 *
	 * Bez id v cestě, stejně jako u vlastních akcí: trasu identifikuje dvojice
	 * (klient, `source_id` v těle), takže opakované volání je úprava.
	 *
	 * Plugin tím trasu **nezakládá** — API u nové trasy vyžaduje GPX, které
	 * plugin nemá. Zakládá ji formulář Turinky; tohle je jen dodání názvu
	 * a textu k trase, která už existuje.
	 */
	public function write_route( string $token, array $payload ): array {
		return $this->request( 'PUT', '/api/v1/integrations/routes', array(
			'token' => $token,
			'body'  => $payload,
		) );
	}

	/**
	 * Trasa podle vlastního id webu.
	 *
	 * Tím se po uložení formuláře zjišťuje, co v Turince opravdu vzniklo —
	 * odpověď formuláře z prohlížeče se k tomu nepoužívá, protože ji posílá
	 * rám, ne server.
	 */
	public function route_by_source( string $token, string $source_id ): array {
		return $this->request(
			'GET',
			'/api/v1/integrations/routes/source/' . rawurlencode( $source_id ),
			array( 'token' => $token )
		);
	}

	/**
	 * Odebrání trasy z Turinky.
	 *
	 * Odpověď říká, co se stalo: `archived` (trasu založil web, Turinka ji
	 * schovala), `unlinked` (web se jen odpojil od trasy, kterou doplňoval),
	 * nebo `kept`.
	 */
	public function delete_route( string $token, string $source_id ): array {
		return $this->request(
			'DELETE',
			'/api/v1/integrations/routes/source/' . rawurlencode( $source_id ),
			array( 'token' => $token )
		);
	}

	/**
	 * Lístek na formulář Turinky vložený do stránky webu.
	 *
	 * Platí dvě hodiny a je jednorázový, takže se vydává až na kliknutí
	 * redakce, ne dopředu při vykreslení obrazovky.
	 *
	 * @param array $payload `type`, `source_id`, `source_url`, volitelně `prefill`.
	 */
	public function form_ticket( string $token, array $payload ): array {
		return $this->request( 'POST', '/api/v1/integrations/forms', array(
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
