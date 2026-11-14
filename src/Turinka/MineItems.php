<?php

namespace Kct\Turinka;

/**
 * Obsah organizace tohoto webu tak, jak ho vidí Turinka — akce, trasy i místa.
 *
 * `mine=1` znamená „obsah mého odboru nebo oblasti“, ne „co jsem poslal“ —
 * u akcí vrací i ty z databáze KČT, které nesou číslo organizace webu, i když
 * k nim web nikdy nic nedodal. Právě proto má přehled smysl: ukáže, co Turinka
 * o webu ví, nejen to, co jsme jí poslali.
 *
 * Co s položkou smí web dělat, říká pole `access`: `own` vlastní, `managed`
 * spravovaná (akce odboru, kterou drží web oblasti, dokud obsah nedodá odbor),
 * `linked` spárovaná cizí, `null` cizí.
 *
 * Výpis akcí vrací bez parametrů i proběhlé akce, nejnovější nahoře — což je
 * pro inventuru správně. Veřejný kalendář na webu je jiný případ a musí si
 * říct o `period=upcoming`.
 *
 * Posílat se smí jen parametry, které výpis zná: neznámý parametr je 422,
 * ne tiché ignorování.
 *
 * Nic se neukládá natrvalo — odpověď drží transient. Když Turinka neodpoví,
 * vrátí se poslední uložená odpověď, a není-li, prázdno s příznakem chyby;
 * přehled akcí musí fungovat i bez Turinky.
 */
class MineItems {
	/** Typy, které výpis umí. Kostra odpovědi je u všech tří stejná. */
	const TYPES = array( 'events', 'routes', 'places' );

	/** Předpona transientů; za ní je typ, aby se tři výpisy nepřebíjely. */
	const CACHE_PREFIX  = 'kct_turinka_mine_';
	const BACKUP_SUFFIX = '_backup';

	/** Jak dlouho se odpověď považuje za čerstvou. */
	const TTL = 10 * MINUTE_IN_SECONDS;

	/** Jak dlouho slouží poslední odpověď jako záloha při výpadku. */
	const BACKUP_TTL = DAY_IN_SECONDS;

	/** Strop stránkování, ať se z výpadku nestane nekonečný cyklus. */
	const MAX_PAGES = 10;

	const PER_PAGE = 100;

	public function __construct(
		private Client $client,
		private TokenStore $tokens
	) {
	}

	/**
	 * Položky jednoho typu ve dvou indexech — podle čísla akce v databázi KČT
	 * a podle `source_id` příspěvku. Tím se páruje na to, co web zná.
	 *
	 * @param string $type `events` | `routes` | `places`.
	 *
	 * @return array{items: array<int,array>, by_kct_id: array<string,array>,
	 *               by_source_id: array<string,array>, ok: bool, message: string}
	 */
	public function all( string $type = 'events', bool $refresh = false ): array {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return $this->empty( __( 'Neznámý typ obsahu.', 'kct' ) );
		}

		if ( ! $this->tokens->has_token() ) {
			return $this->empty( __( 'Web není propojený s Turinkou.', 'kct' ) );
		}

		if ( ! $refresh ) {
			$cached = get_transient( self::CACHE_PREFIX . $type );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$fetched = $this->fetch( $type );

		if ( ! $fetched['ok'] ) {
			$backup = get_transient( self::CACHE_PREFIX . $type . self::BACKUP_SUFFIX );

			if ( is_array( $backup ) ) {
				$backup['ok']      = false;
				$backup['message'] = $fetched['message'];

				return $backup;
			}

			return $fetched;
		}

		set_transient( self::CACHE_PREFIX . $type, $fetched, self::TTL );
		set_transient( self::CACHE_PREFIX . $type . self::BACKUP_SUFFIX, $fetched, self::BACKUP_TTL );

		return $fetched;
	}

	/** Má web vůbec přístup, nebo se ptát nemá smysl? */
	public function has_token(): bool {
		return $this->tokens->has_token();
	}

	/**
	 * Uložená odpověď, nebo null, když žádná není.
	 *
	 * Pro místa, kde se čísla jen ukazují a nestojí za to kvůli nim chodit do
	 * API — nástěnka se otevírá často a čekat kvůli ní na tři výpisy by bylo
	 * drahé. Čerstvá data si redakce vyžádá na stránce přehledu.
	 */
	public function cached( string $type ): ?array {
		$cached = get_transient( self::CACHE_PREFIX . $type );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Čerstvá odpověď platí deset minut, ale nástěnka se otevírá kdykoli —
		// po té lhůtě by u ní pořád stálo „zatím nenačteno“, i když se čísla
		// dávno stáhla. Záloha je poslední známý stav a na číslo v přehledu
		// to stačí; čerstvá data natáhne stránka přehledu.
		$backup = get_transient( self::CACHE_PREFIX . $type . self::BACKUP_SUFFIX );

		return is_array( $backup ) ? $backup : null;
	}

	/** Zahodí uloženou odpověď, aby se příští dotaz zeptal znovu. */
	public function forget( string $type = 'events' ): void {
		delete_transient( self::CACHE_PREFIX . $type );
	}

	/**
	 * Projde stránky výpisu a složí z nich indexy.
	 */
	private function fetch( string $type ): array {
		$items = array();
		$query = array(
			'mine'     => 1,
			'per_page' => self::PER_PAGE,
		);

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$result = $this->client->listing( $this->tokens->token(), $type, $query + array( 'page' => $page ) );

			if ( empty( $result['ok'] ) ) {
				return $this->empty( $this->failure( $result ) );
			}

			$data = (array) ( $result['data'] ?? array() );

			if ( ! $data ) {
				break;
			}

			$items = array_merge( $items, $data );

			$total_pages = (int) ( $result['body']['meta']['total_pages'] ?? 1 );

			if ( $page >= $total_pages ) {
				break;
			}
		}

		return $this->index( $items );
	}

	/**
	 * @param array<int,array> $items
	 */
	private function index( array $items ): array {
		$by_kct_id    = array();
		$by_source_id = array();

		foreach ( $items as $item ) {
			$kct_id = (string) ( $item['kct_id'] ?? '' );

			if ( '' !== $kct_id ) {
				$by_kct_id[ $kct_id ] = $item;
			}

			$source_id = (string) ( $item['source']['source_id'] ?? '' );

			if ( '' !== $source_id ) {
				$by_source_id[ $source_id ] = $item;
			}
		}

		return array(
			'items'        => $items,
			'by_kct_id'    => $by_kct_id,
			'by_source_id' => $by_source_id,
			'ok'           => true,
			'message'      => '',
		);
	}

	private function empty( string $message ): array {
		return array(
			'items'        => array(),
			'by_kct_id'    => array(),
			'by_source_id' => array(),
			'ok'           => false,
			'message'      => $message,
		);
	}

	private function failure( array $result ): string {
		if ( 0 === (int) $result['status'] ) {
			return $result['message'] ?: __( 'Turinka neodpověděla.', 'kct' );
		}

		// Překročený limit volání. Opakovat dotaz hned by limit jen držel
		// vyčerpaný, takže se přehled spokojí s posledními známými daty.
		if ( 429 === (int) $result['status'] ) {
			return __( 'Turinka hlásí příliš mnoho dotazů za minutu. Zkuste to za chvíli.', 'kct' );
		}

		return sprintf(
			/* translators: 1: stavový kód odpovědi, 2: popis chyby. */
			__( 'Turinka odpověděla %1$d: %2$s', 'kct' ),
			(int) $result['status'],
			$result['message'] ?: __( 'bez bližšího popisu', 'kct' )
		);
	}
}
