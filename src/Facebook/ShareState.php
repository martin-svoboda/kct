<?php

namespace Kct\Facebook;

/**
 * Jediné místo, které zná meta klíče sdílení na Facebook.
 */
class ShareState implements ShareStore {
	const META_SHARE    = 'kct_fb_share';
	const META_MESSAGE  = 'kct_fb_message';

	/** Přepsání počtu dní před akcí u konkrétní akce; prázdné = nastavení webu. */
	const META_LEAD_DAYS = 'kct_fb_lead_days';
	const META_POST_ID  = 'kct_fb_post_id';
	const META_TIME     = 'kct_fb_shared_at';
	const META_ERROR    = 'kct_fb_error';
	const META_ATTEMPTS = 'kct_fb_attempts';

	/** Nejdelší uložená délka chybové zprávy ve znacích. */
	const MAX_ERROR_LENGTH = 500;

	/** Předpona option, ve které je uložený zámek odesílání. */
	const LOCK_PREFIX = 'kct_fb_sending_';

	/** Jak dlouho (v sekundách) zámek platí, než ho smí zabrat další běh. */
	const LOCK_TTL = 300;

	/**
	 * Časy, kterými si tento běh zabral zámky, podle ID příspěvku.
	 *
	 * Slouží k tomu, aby release() smazal jen zámek, který tomuto běhu
	 * skutečně patří.
	 *
	 * @var array<int, int>
	 */
	private array $claimed_at = array();

	/**
	 * Byl příspěvek už úspěšně odeslán na Facebook?
	 */
	public function is_shared( int $post_id ): bool {
		return '' !== $this->fb_post_id( $post_id );
	}

	/**
	 * ID příspěvku na Facebooku, nebo prázdný řetězec, nebylo-li odesláno.
	 */
	public function fb_post_id( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_POST_ID, true );
	}

	/**
	 * Unixový čas odeslání, nebo 0, nebylo-li odesláno.
	 */
	public function shared_at( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META_TIME, true );
	}

	/**
	 * Poslední zaznamenaná chyba odeslání.
	 *
	 * @return array{code: int, message: string}|array{}
	 */
	public function error( int $post_id ): array {
		$error = get_post_meta( $post_id, self::META_ERROR, true );

		return is_array( $error ) ? $error : array();
	}

	/**
	 * Kolikrát dosud odeslání selhalo.
	 */
	public function attempts( int $post_id ): int {
		return (int) get_post_meta( $post_id, self::META_ATTEMPTS, true );
	}

	/**
	 * Má se příspěvek sdílet? Chybí-li meta úplně (příspěvek vznikl mimo editor,
	 * třeba importem), rozhodne globální nastavení.
	 */
	public function should_share( int $post_id, bool $default ): bool {
		if ( ! metadata_exists( 'post', $post_id, self::META_SHARE ) ) {
			return $default;
		}

		return (bool) get_post_meta( $post_id, self::META_SHARE, true );
	}

	/**
	 * Zaznamená úspěšné odeslání a smaže případnou předchozí chybu i počítadlo
	 * pokusů.
	 *
	 * Prázdné $fb_post_id se odmítne a zaznamená jako chyba, místo aby se
	 * tiše uložilo — jinak by vznikl mrtvý stav: is_shared() by kvůli
	 * prázdnému fb_post_id() vrátilo false, metabox by se vůbec nezobrazil a
	 * po odeslání by nezůstala žádná stopa, ze které by šlo poznat, že něco
	 * nesedí.
	 */
	public function mark_shared( int $post_id, string $fb_post_id ): void {
		if ( '' === $fb_post_id ) {
			// Kód 0 stejně jako u GraphClient — nejde o chybu vrácenou Graph
			// API (ta by měla vlastní kód), ale o neočekávaně prázdnou
			// hodnotu tam, kde by podle GraphClient::parse() mělo být ID
			// vždy vyplněné.
			$this->mark_error( $post_id, 0, __( 'Facebook nevrátil platné ID publikovaného příspěvku.', 'kct' ) );

			return;
		}

		update_post_meta( $post_id, self::META_POST_ID, $fb_post_id );
		update_post_meta( $post_id, self::META_TIME, time() );
		delete_post_meta( $post_id, self::META_ERROR );
		delete_post_meta( $post_id, self::META_ATTEMPTS );
	}

	/**
	 * Zaznamená neúspěšný pokus o odeslání a zvýší počítadlo pokusů.
	 *
	 * Zpráva se ukládá useknutá na MAX_ERROR_LENGTH znaků — jde o syrovou
	 * odpověď od Facebooku, bez omezení by mohla neúměrně nafouknout řádek
	 * v databázi i výpis v metaboxu.
	 *
	 * Čtení a zápis počítadla (attempts() + 1) není atomické — dva souběžné
	 * běhy (např. naplánovaná cron událost a ruční „Zkusit znovu" spuštěné
	 * skoro současně) mohou přečíst stejnou hodnotu a uložit stejné zvýšené
	 * číslo. Počítadlo jen řídí odstupy opakování (Task 6), takže drobná
	 * nepřesnost při souběhu nevadí.
	 */
	public function mark_error( int $post_id, int $code, string $message ): void {
		$error = array(
			'code'    => $code,
			'message' => mb_substr( $message, 0, self::MAX_ERROR_LENGTH ),
		);

		update_post_meta( $post_id, self::META_ERROR, $error );
		update_post_meta( $post_id, self::META_ATTEMPTS, $this->attempts( $post_id ) + 1 );
	}

	/**
	 * Vynuluje počítadlo pokusů, typicky před dalším pokusem o odeslání.
	 *
	 * Chybu záměrně nemaže. Obsluha tlačítka „Zkusit znovu" (Task 6) volá
	 * reset_attempts() a hned potom share(), které ale může na několika
	 * podmínkách tiše skončit bez odeslání — třeba když redaktor mezitím
	 * vypnul přepínač sdílení nebo vrátil příspěvek do konceptu. Kdyby tahle
	 * metoda smazala i chybu, zmizela by, aniž by cokoli odešlo — metabox by
	 * přestal chybu zobrazovat a po neúspěchu by nezůstala žádná stopa.
	 * Mazání chyby jinde stejně netřeba: mark_shared() ji maže při úspěchu
	 * sama a mark_error() ji při dalším selhání přepíše.
	 */
	public function reset_attempts( int $post_id ): void {
		delete_post_meta( $post_id, self::META_ATTEMPTS );
	}

	/**
	 * Zabere zámek odesílání pro daný příspěvek.
	 *
	 * Mezi kontrolou is_shared() a zápisem mark_shared() leží celé HTTP volání
	 * Graph API (timeout 20 s). Dva souběžné běhy — naplánovaná cron událost
	 * a ruční spuštění wp-cronu, dva požadavky na wp-cron.php současně — by
	 * bez zámku obojí prošly kontrolou, obojí zavolaly publish() a na
	 * Facebooku by zůstal duplicitní příspěvek, o kterém WordPress neví
	 * (uloží se jen ID toho, kdo zapsal poslední).
	 *
	 * Zámek drží řádek v tabulce options, ne transient: unikátní index nad
	 * sloupcem option_name zajistí, že prostý INSERT uspěje jen jednomu
	 * souběžnému běhu — atomicky a bez ohledu na to, jestli je zapnutá
	 * persistentní object cache. Řádek je záměrně bez autoloadu, ať se
	 * nenačítá při každém requestu.
	 *
	 * @param int $post_id ID příspěvku.
	 * @param int $ttl     Po kolika sekundách smí zámek zabrat další běh.
	 *
	 * @return bool True, když zámek patří tomuto běhu.
	 */
	public function claim( int $post_id, int $ttl = self::LOCK_TTL ): bool {
		$key = self::LOCK_PREFIX . $post_id;
		$now = time();

		if ( $this->insert_lock( $key, $now ) ) {
			$this->claimed_at[ $post_id ] = $now;

			return true;
		}

		// Zámek po spadlém běhu (fatal error, timeout) se po vypršení TTL
		// uvolní sám — jinak by se příspěvek už nikdy neodeslal.
		if ( $this->take_over_expired_lock( $key, $now, $ttl ) ) {
			$this->claimed_at[ $post_id ] = $now;

			return true;
		}

		return false;
	}

	/**
	 * Drží zámek odesílání nějaký běh?
	 *
	 * Jen pro vysvětlení, proč se příspěvek zrovna neodeslal (viz
	 * FacebookShare::outcome()) — o samotné odeslání se nikdy nesmí opřít,
	 * na to je claim(). Mezi tímhle čtením a případným odesláním by si zámek
	 * stihl zabrat kdokoli jiný.
	 *
	 * Čte se přímo z databáze, ne přes get_option(): řádek se zámkem vzniká
	 * a mizí mimo API options, takže by ho object cache mohla vracet zastaralý.
	 *
	 * @param int $post_id ID příspěvku.
	 * @param int $ttl     Po kolika sekundách se zámek považuje za vypršelý.
	 */
	public function is_locked( int $post_id, int $ttl = self::LOCK_TTL ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- zámek žije mimo API options, viz docblock.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `option_value` FROM `$wpdb->options` WHERE `option_name` = %s",
				self::LOCK_PREFIX . $post_id
			)
		);

		if ( null === $value ) {
			return false;
		}

		return (int) $value > time() - $ttl;
	}

	/**
	 * Uvolní zámek odesílání — ale jen ten vlastní.
	 *
	 * Maže se podle názvu i hodnoty, tedy podle času, kterým si tento běh
	 * zámek zabral. Běh, kterému zámek mezitím vypršel a někdo jiný ho
	 * převzal, tak nesmaže zámek svému nástupci: nástupce může zámek převzít
	 * nejdřív o LOCK_TTL později, takže jeho čas je vždycky jiný. (Rozlišení
	 * je na sekundy — kdyby TTL někdo snížil na nulu, přestala by tahle
	 * ochrana platit.)
	 *
	 * @param int $post_id ID příspěvku.
	 */
	public function release( int $post_id ): void {
		if ( ! isset( $this->claimed_at[ $post_id ] ) ) {
			return;
		}

		global $wpdb;

		$key        = self::LOCK_PREFIX . $post_id;
		$claimed_at = $this->claimed_at[ $post_id ];

		unset( $this->claimed_at[ $post_id ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- podmínka na hodnotu, viz docblock.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `$wpdb->options` WHERE `option_name` = %s AND `option_value` = %s",
				$key,
				(string) $claimed_at
			)
		);

		$this->forget_cached_lock( $key );
	}

	/**
	 * Vloží řádek se zámkem, jen pokud ještě neexistuje.
	 *
	 * Vlastní INSERT místo add_option(): ta v aktuálním WordPressu (ověřeno
	 * na 6.8) zapisuje přes
	 * `INSERT ... ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)`,
	 * takže když dva souběžné běhy projdou její kontrolou existence dřív, než
	 * kterýkoli z nich stihne zapsat, druhý běh hodnotu prvního přepíše
	 * a dostane zpět úspěch — tedy oba by si mysleli, že zámek drží.
	 * Prostý INSERT v takové situaci skončí na duplicitním klíči.
	 *
	 * @param string $key Název option se zámkem.
	 * @param int    $now Čas zabrání zámku.
	 *
	 * @return bool True, když řádek vznikl.
	 */
	private function insert_lock( string $key, int $now ): bool {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- zámek musí být atomický, viz docblock.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'off' )",
				$key,
				(string) $now
			)
		);

		$wpdb->suppress_errors( $suppress );

		if ( ! $result ) {
			return false;
		}

		$this->forget_cached_lock( $key );

		return true;
	}

	/**
	 * Převezme zámek, kterému vypršel TTL.
	 *
	 * Jediný podmíněný UPDATE, ne mazání a nový INSERT: o vítěze se postará
	 * řádkový zámek InnoDB, takže když dva běhy najdou tentýž vypršelý zámek,
	 * uspěje právě jeden — druhému podmínka `option_value < …` po odemčení
	 * řádku už nesedí a UPDATE změní nula řádků.
	 *
	 * Pozor: `option_value` je textový sloupec, takže porovnání `<` je
	 * porovnání řetězců. Funguje jen proto, že unixové časy mají dnes stejný
	 * počet číslic — až budou delší (rok 2286), řetězcové porovnání přestane
	 * odpovídat porovnání čísel a tuhle podmínku bude potřeba přepsat
	 * (např. `CAST( option_value AS UNSIGNED )`).
	 *
	 * @param string $key Název option se zámkem.
	 * @param int    $now Čas zabrání zámku.
	 * @param int    $ttl Po kolika sekundách smí zámek zabrat další běh.
	 *
	 * @return bool True, když zámek převzal tento běh.
	 */
	private function take_over_expired_lock( string $key, int $now, int $ttl ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- převzetí musí být atomické, viz docblock.
		$taken = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `$wpdb->options` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` < %s",
				(string) $now,
				$key,
				(string) ( $now - $ttl )
			)
		);

		if ( 1 !== (int) $taken ) {
			return false;
		}

		$this->forget_cached_lock( $key );

		return true;
	}

	/**
	 * Zapomene zámek v object cache.
	 *
	 * Řádek se zámkem vzniká, mění se i mizí mimo API options, takže se
	 * o cache musíme postarat sami.
	 *
	 * @param string $key Název option se zámkem.
	 */
	private function forget_cached_lock( string $key ): void {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

}
