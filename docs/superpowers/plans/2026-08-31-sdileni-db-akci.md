# Sdílení databázových akcí na Facebook — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-31-sdileni-db-akci-design.md`](../specs/2026-08-31-sdileni-db-akci-design.md)

**Cíl:** Odesílat na Facebook i akce z centrální databáze, které nemají vlastní
příspěvek — se stavem uloženým v tabulce, s právem podle odboru/oblasti
a s ovládáním přímo na stránce akce.

**Architektura:** Stav odeslání jde do nového sloupce `fb_share` v
`wp_db_events`, klíčovaného ID webu, zapisovaného jedním atomickým
`JSON_MERGE_PATCH`. Odesílání se vytáhne z `FacebookShare` do třídy
`Publisher`, kterou pak sdílí obě cesty. Novou feature `DbEventShare` spouští
denní WP-Cron událost.

**Tech stack:** PHP 8.0+, WordPress multisite, MariaDB 10.11, PHP-DI, wpify/model,
WP-Cron, Facebook Graph API v21.0.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám.

**Nikdy neodesílej nic na Facebook.** Kód publikuje na veřejnou stránku.
Nevolej `publish()`, `publish_photo()`, `wp kct fb_share` ani novou denní
úlohu naostro.

**Nikdy nezapisuj do databáze kvůli ověření.** Výjimka: Task 1 mění schéma
tabulky, což je vlastní obsah úkolu, a Task 10 čte. Žádné `update_option`,
`wp_insert_post`, editace příspěvků.

**V projektu není PHPUnit** a nezavádí se. Ověřuje se `php -l`,
`ddev wp eval-file` a čtením.

**Příkazy** z kořene projektu `/Users/martin/Sites/sokct`. Cesty v plánu jsou
relativní ke kořeni pluginu `/Users/martin/Sites/sokct/wp-content/plugins/kct`.

Víceřádkový PHP kód nedávej do `ddev wp eval` — zapiš do souboru v kořeni
**projektu** a pusť `ddev wp --url=https://sokct.test eval-file <soubor>`.

**Po každé změně tříd smaž kontejner:**

```bash
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

### Ověřená fakta o prostředí — na tohle se neptej znovu

**Tabulka `wp_db_events` je jedna pro celou síť.** Existuje jen ona, žádné
`wp_2_db_events`. Všech osm webů čte tytéž řádky.

**`DbEventRepository` na ni dosáhne z každého webu** tak, že si před dotazem
přepne na web 1 (`get_by_db_id()`, `find_all_by_date()`). Obecné `find_all()`
tu obezličku nemá a z podwebu selže — nepoužívej ho.

**Vlastní SQL piš přes `$wpdb->base_prefix`**, ne `prefix`. Na webu 2 je
`prefix` = `wp_2_`, což je tabulka, která neexistuje.

**MariaDB 10.11 umí `JSON_MERGE_PATCH`** — ověřeno na produkci, včetně mazání
klíče hodnotou `null`.

**Verze schématu je `md5(create_table_sql())`**, takže přidání sloupce spustí
`dbDelta()` samo. Nic se nebumpuje ručně.

**Facebook není nastavený lokálně** (je jen na produkci), takže
`is_configured()` vrací false a nic se neodešle. Pro ověřování v tomhle plánu
to nevadí.

---

## Struktura souborů

| Soubor | Odpovědnost |
|---|---|
| `src/Models/DbEventModel.php` | Nový sloupec `fb_share`. |
| `src/Facebook/ShareStore.php` | **nový** — rozhraní stavu odeslání. |
| `src/Facebook/DbShareState.php` | **nový** — stav DB akcí ve sloupci, atomický zápis. |
| `src/Facebook/Publisher.php` | **nový** — vlastní odeslání: fotka, při odmítnutí odkaz. |
| `src/Facebook/ShareState.php` | Dopsat `implements ShareStore`. |
| `src/Facebook/MessageComposer.php` | Text z pole akce, ne jen z příspěvku. |
| `src/Features/FacebookShare.php` | Použít `Publisher` místo vlastní větve. |
| `src/Features/Events.php` | Veřejná metoda „vypisuje tenhle web tuhle akci?". |
| `src/Features/DbEventShare.php` | **nový** — denní úloha, odeslání, ovládání. |
| `src/Managers/FeaturesManager.php` | Registrace nové feature. |
| `themes/kct/template-parts/content-akce.php` | Ovládání na stránce akce. |
| `src/CLI.php` | Příkaz `fb_due`. |

---

## Task 1: Sloupec fb_share

**Files:**
- Modify: `src/Models/DbEventModel.php`

- [ ] **Step 1: Přidej sloupec do modelu**

V `src/Models/DbEventModel.php` přidej za vlastnost `$proposal`:

```php
	/**
	 * Stav odeslání na Facebook, klíčovaný ID webu.
	 *
	 * Tabulka je jedna pro celou síť a tatáž akce se objevuje na oblastním
	 * i odborovém webu — každý má vlastní facebookovou stránku, takže stav
	 * musí být per web:
	 *
	 *     { "1": { "sent": 1756630800, "fb": "3113…_1223…" },
	 *       "2": { "off": true } }
	 *
	 * ČTE se přes model, ale ZAPISUJE se mimo něj jedním atomickým příkazem
	 * (viz Facebook\DbShareState). Kdyby se zapisovalo přes save(), přepsal by
	 * si jeden web zápis druhého — oba tentýž řádek zpracovávají současně.
	 */
	#[Column( type: Column::JSON, nullable: true )]
	public array $fb_share = array();
```

- [ ] **Step 2: Ověř syntaxi a že migrace proběhla**

**Migraci nespustí načtení WordPressu, ale až použití repozitáře.**
`CustomTableRepository::migrate()` se volá z `auto_migrate()` při prvním
dotazu, ne při bootstrapu — takže `wp option get blogname` sloupec nepřidá.
Vynutí se libovolným čtením přes repozitář.

Zapiš do `/Users/martin/Sites/sokct/migrate.php`:

```php
<?php
// Čtení, které nic nevrátí — jde jen o to sáhnout na repozitář a spustit migraci.
kct_container()->get( \Kct\Repositories\DbEventRepository::class )
	->find_all_by_date( '2099-01-01', '2099-01-02' );
echo "repozitář použit\n";
```

Spusť:

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Models/DbEventModel.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file migrate.php && rm migrate.php
echo "SHOW COLUMNS FROM wp_db_events LIKE 'fb_share';" | ddev wp --url=https://sokct.test db query
```

Očekávaný výstup: `repozitář použit`, pak řádek se sloupcem `fb_share` typu
`longtext`. Kdyby sloupec nebyl, zkontroluj, že se kontejner smazal.

- [ ] **Step 3: Ověř, že data zůstala**

```bash
echo "SELECT COUNT(*) FROM wp_db_events;" | ddev wp --url=https://sokct.test db query --skip-column-names
```

Očekávaný výstup: `318`. `dbDelta` sloupec přidává, řádky nemaže — kdyby
číslo kleslo, něco je špatně a dál nepokračuj.

---

## Task 2: Rozhraní ShareStore

**Files:**
- Create: `src/Facebook/ShareStore.php`
- Modify: `src/Facebook/ShareState.php`

- [ ] **Step 1: Vytvoř rozhraní**

```php
<?php

namespace Kct\Facebook;

/**
 * Stav odeslání jednoho objektu na Facebook.
 *
 * Dvě implementace: ShareState drží stav v post meta (aktuality a CPT akce),
 * DbShareState ve sloupci tabulky (akce z centrální databáze). Odesílání díky
 * tomu nemusí vědět, s čím pracuje.
 *
 * Klíč je celé číslo — u příspěvku jeho ID, u databázové akce její db_id.
 */
interface ShareStore {

	public function is_shared( int $id ): bool;

	/**
	 * Má se objekt odeslat? Vlastní volba u objektu přebíjí výchozí hodnotu.
	 */
	public function should_share( int $id, bool $default ): bool;

	/**
	 * Zabere zámek proti dvojímu odeslání. False = odesílá právě někdo jiný.
	 */
	public function claim( int $id ): bool;

	public function release( int $id ): void;

	public function mark_shared( int $id, string $fb_post_id ): void;

	public function mark_error( int $id, int $code, string $message ): void;
}
```

- [ ] **Step 2: Dopiš implements do ShareState**

V `src/Facebook/ShareState.php` změň deklaraci třídy:

```php
class ShareState implements ShareStore {
```

`ShareState` už všechny metody rozhraní má; `claim()` má navíc volitelný
druhý parametr `$ttl`, což je s rozhraním slučitelné.

- [ ] **Step 3: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Facebook/ShareStore.php && php -l src/Facebook/ShareState.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: dvakrát `No syntax errors detected`, pak název webu.

---

## Task 3: DbShareState

**Files:**
- Create: `src/Facebook/DbShareState.php`

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Facebook;

use Kct\Repositories\DbEventRepository;

/**
 * Stav odeslání databázových akcí.
 *
 * Drží ho sloupec `fb_share` v `wp_db_events`, JSON klíčovaný ID webu.
 * Tabulka je jedna pro celou síť a tatáž akce se objevuje na oblastním
 * i odborovém webu, takže stav musí být per web.
 *
 * ČTE se přes repozitář (ten si umí přepnout na web 1, kde tabulka fyzicky
 * je). ZAPISUJE se vlastním SQL, protože přes model by to bylo
 * „načti, uprav, ulož" — a dva weby zpracovávající tentýž řádek současně by si
 * zápis přepsaly. Ztracený záznam „odesláno" znamená odeslání podruhé, tedy
 * duplicitní příspěvek na Facebooku.
 */
class DbShareState implements ShareStore {

	/** Předpona option se zámkem odesílání. */
	private const LOCK_PREFIX = 'kct_fb_db_sending_';

	/** Jak dlouho zámek platí, než ho převezme další běh. */
	private const LOCK_TTL = 300;

	public function __construct( private DbEventRepository $repository ) {
	}

	public function is_shared( int $id ): bool {
		return '' !== $this->value( $id, 'fb' );
	}

	public function fb_post_id( int $id ): string {
		return $this->value( $id, 'fb' );
	}

	public function shared_at( int $id ): int {
		return (int) $this->value( $id, 'sent' );
	}

	/**
	 * Vypnuto u konkrétní akce přebíjí výchozí hodnotu z nastavení.
	 */
	public function should_share( int $id, bool $default ): bool {
		return empty( $this->state( $id )['off'] ) && $default;
	}

	/** Chyba posledního pokusu, nebo prázdné pole. */
	public function error( int $id ): array {
		$state = $this->state( $id );

		return isset( $state['error'] ) && is_array( $state['error'] ) ? $state['error'] : array();
	}

	public function mark_shared( int $id, string $fb_post_id ): void {
		if ( '' === $fb_post_id ) {
			return;
		}

		$this->write( $id, array(
			'sent'  => time(),
			'fb'    => $fb_post_id,
			'error' => null,
		) );
	}

	public function mark_error( int $id, int $code, string $message ): void {
		$this->write( $id, array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
				'time'    => time(),
			),
		) );
	}

	/** Vypne nebo zapne odesílání u konkrétní akce. */
	public function set_disabled( int $id, bool $disabled ): void {
		$this->write( $id, array( 'off' => $disabled ? true : null ) );
	}

	public function is_disabled( int $id ): bool {
		return ! empty( $this->state( $id )['off'] );
	}

	/**
	 * Zámek se drží v option, ne ve sloupci.
	 *
	 * Sloupec je sdílený mezi weby a zámek je věc jednoho webu; navíc by se
	 * kvůli zámku psalo do tabulky při každém běhu úlohy, i když se nic
	 * neodesílá.
	 */
	public function claim( int $id ): bool {
		$key = self::LOCK_PREFIX . $id;
		$now = time();

		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}

		// Zámek po spadlém běhu se po vypršení TTL uvolní sám — jinak by se
		// akce už nikdy neodeslala.
		$since = (int) get_option( $key );

		if ( $since && ( $now - $since ) > self::LOCK_TTL ) {
			update_option( $key, $now, false );

			return true;
		}

		return false;
	}

	public function release( int $id ): void {
		delete_option( self::LOCK_PREFIX . $id );
	}

	/**
	 * Stav akce pro tenhle web.
	 *
	 * @return array<string, mixed>
	 */
	private function state( int $id ): array {
		$event = $this->repository->get_by_db_id( $id );

		if ( ! $event || ! is_array( $event->fb_share ) ) {
			return array();
		}

		$mine = $event->fb_share[ (string) get_current_blog_id() ] ?? array();

		return is_array( $mine ) ? $mine : array();
	}

	private function value( int $id, string $key ): string {
		$value = $this->state( $id )[ $key ] ?? '';

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Zapíše hodnoty pod klíč tohoto webu, cizí klíče nechá být.
	 *
	 * JSON_MERGE_PATCH slučuje na úrovni řádku, takže se nic nečte do PHP
	 * a dva weby si zápis nepřepíšou. Hodnota null klíč odstraní — tak se maže
	 * `off` i `error`.
	 *
	 * base_prefix, ne prefix: tabulka je jedna pro celou síť a jmenuje se
	 * wp_db_events; $wpdb->prefix je na webu 2 „wp_2_", tedy tabulka, která
	 * neexistuje.
	 *
	 * @param array<string, mixed> $values
	 */
	private function write( int $id, array $values ): void {
		global $wpdb;

		$patch = wp_json_encode( array( (string) get_current_blog_id() => $values ) );

		if ( false === $patch ) {
			return;
		}

		$table = $wpdb->base_prefix . DbEventRepository::$table_name;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET fb_share = JSON_MERGE_PATCH( COALESCE(fb_share, '{}'), %s ) WHERE db_id = %d",
				$patch,
				$id
			)
		);
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/DbShareState.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/DbShareState.php`

- [ ] **Step 3: Ověř atomický zápis, hlavně že nepřepíše cizí web**

Zapiš do `/Users/martin/Sites/sokct/dbstate.php`:

```php
<?php
$state = kct_container()->get( \Kct\Facebook\DbShareState::class );
$repo  = kct_container()->get( \Kct\Repositories\DbEventRepository::class );

$db_id = 23954;

// Cizí web nasimulujeme přímým zápisem do sloupce — je to jediné místo
// v celém plánu, kde se do databáze zapisuje, a je to obsah testu.
global $wpdb;
$table = $wpdb->base_prefix . \Kct\Repositories\DbEventRepository::$table_name;
$wpdb->query( $wpdb->prepare(
	"UPDATE `{$table}` SET fb_share = %s WHERE db_id = %d",
	wp_json_encode( array( '99' => array( 'sent' => 111, 'fb' => 'cizi_web' ) ) ),
	$db_id
) );

$state->mark_shared( $db_id, 'muj_web_123' );

$after = $repo->get_by_db_id( $db_id )->fb_share;
printf( "po zápisu: %s\n", wp_json_encode( $after, JSON_UNESCAPED_UNICODE ) );
printf( "  klíč cizího webu (99) zůstal: %s\n", isset( $after['99'] ) ? 'ANO' : 'NE — CHYBA' );
printf( "  můj klíč (%d) je zapsaný:     %s\n", get_current_blog_id(), isset( $after[ (string) get_current_blog_id() ] ) ? 'ANO' : 'NE — CHYBA' );
printf( "  is_shared(): %s\n", $state->is_shared( $db_id ) ? 'ano' : 'NE — CHYBA' );

$state->set_disabled( $db_id, true );
printf( "  po set_disabled(true)  is_disabled: %s\n", $state->is_disabled( $db_id ) ? 'ano' : 'NE — CHYBA' );
$state->set_disabled( $db_id, false );
printf( "  po set_disabled(false) is_disabled: %s\n", $state->is_disabled( $db_id ) ? 'ANO — CHYBA' : 'ne' );

// Úklid: vrátíme sloupec do původního stavu.
$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET fb_share = NULL WHERE db_id = %d", $db_id ) );
printf( "úklid: fb_share zpět na NULL\n" );
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test eval-file dbstate.php && rm dbstate.php
```

Očekávaný výstup: klíč cizího webu zůstal, můj se zapsal, `is_shared` ano,
přepínání `off` funguje v obou směrech, a na konci se sloupec vrátil na NULL.
Kterékoli `CHYBA` znamená zastavit a nahlásit.

---

## Task 4: Publisher

**Files:**
- Create: `src/Facebook/Publisher.php`

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Facebook;

/**
 * Odeslání jednoho příspěvku na Facebook.
 *
 * Sdílí se fotkou; když ji Facebook odmítne, spadne se na odkaz, aby se
 * sdílení kvůli obrázku neuskutečnilo. Logika je tady, ne u volajícího,
 * protože ji potřebují dvě cesty (příspěvky a databázové akce) a je to zrovna
 * ta část, kde by se kopie rozešly nepozorovaně — chybová větev se v provozu
 * potká zřídka.
 */
class Publisher {

	public function __construct(
		private Credentials $credentials,
		private GraphClient $client
	) {
	}

	/**
	 * @param string      $message       Text pro odeslání odkazem.
	 * @param string      $photo_message Text pro odeslání fotkou (nese odkaz v sobě).
	 * @param string|null $link          Odkaz pro odeslání odkazem; null u obsahu bez detailu.
	 * @param string|null $image_url     Adresa sdílecího obrázku; null = rovnou odkazem.
	 *
	 * @return array{ok: bool, id?: string, code?: int, message?: string}
	 */
	public function send( string $message, string $photo_message, ?string $link, ?string $image_url ): array {
		if ( null !== $image_url && '' !== $image_url ) {
			$result = $this->client->publish_photo(
				$this->credentials->page_id(),
				$this->credentials->token(),
				$photo_message,
				$image_url
			);

			if ( $this->keep( $result ) ) {
				return $result;
			}
		}

		return $this->client->publish(
			$this->credentials->page_id(),
			$this->credentials->token(),
			$message,
			$link
		);
	}

	/**
	 * Má se výsledek odeslání fotky brát jako konečný?
	 *
	 * Úspěch ano. U neúspěchu záleží na tom, jestli Facebook odpověděl:
	 *
	 * - **Odpověděl a odmítl** (kód > 0) — fotka se mu nelíbí a opakovat ji
	 *   nemá smysl; pošle se odkaz, ať sdílení proběhne aspoň takhle.
	 * - **Neodpověděl** (kód 0, chyba spojení nebo časový limit) — neví se,
	 *   jestli příspěvek na zdi vznikl. Odeslat po tom ještě odkaz by mohlo
	 *   znamenat dva příspěvky za sebou, proto se to nechá spadnout do
	 *   běžného opakování, které je chráněné kontrolou is_shared().
	 *
	 * Neplatný token je výjimka v druhou stranu: odkaz by dopadl stejně, tak
	 * se jím neplýtvá a rovnou se předá obsluze chyb.
	 *
	 * @param array{ok: bool, code?: int, message?: string} $result
	 */
	private function keep( array $result ): bool {
		if ( ! empty( $result['ok'] ) ) {
			return true;
		}

		$code = (int) ( $result['code'] ?? 0 );

		if ( 0 === $code || GraphClient::ERROR_INVALID_TOKEN === $code ) {
			return true;
		}

		error_log( sprintf(
			'kct: Facebook odmítl fotku (%d: %s), zkouším odkazem.',
			$code,
			(string) ( $result['message'] ?? '' )
		) );

		return false;
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/Publisher.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/Publisher.php`

---

## Task 5: FacebookShare použije Publisher

**Files:**
- Modify: `src/Features/FacebookShare.php`

- [ ] **Step 1: Přidej Publisher do konstruktoru**

Přidej jako poslední parametr:

```php
		private Publisher $publisher
```

a nahoře do importů:

```php
use Kct\Facebook\Publisher;
```

- [ ] **Step 2: Nahraď větev odesílání**

V metodě `share()` najdi blok, který začíná `$image  = $this->social_image( $post );`
a končí uzavřením přiřazení do `$result` (celý ten `if ( null !== $image ) { … }`
i následující `if ( null === $result ) { … }`), a nahraď ho:

```php
			$result = $this->publisher->send(
				$this->composer->compose( $post ),
				$this->composer->compose_with_link( $post ),
				$this->composer->link( $post ),
				$this->social_image( $post )
			);
```

- [ ] **Step 3: Odstraň osiřelou metodu**

Metoda `keep_photo_result()` se přesunula do `Publisher`. Smaž ji z
`FacebookShare` i s docblockem.

- [ ] **Step 4: Ověř syntaxi a že po ní nezbyly odkazy**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Features/FacebookShare.php
grep -n 'keep_photo_result' src/Features/FacebookShare.php || echo "  žádné zbylé odkazy"
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: `No syntax errors detected`, `žádné zbylé odkazy`, název webu.

---

## Task 6: Právo na akci

**Files:**
- Modify: `src/Features/Events.php`

- [ ] **Step 1: Vytáhni filtr do veřejné metody**

Přidej do třídy `Events`:

```php
	/**
	 * Vypisuje tenhle web tuhle akci?
	 *
	 * Je to totéž pravidlo, kterým get_events() filtruje výpis: web s
	 * třímístným kódem je oblast a vidí celou oblast, se šestimístným je odbor
	 * a vidí jen svoje. Sdílení na Facebook se řídí tímtéž — web smí odeslat
	 * akci právě tehdy, když ji sám vypisuje.
	 *
	 * Bez vyplněného kódu web nevypisuje nic a nesdílí nic.
	 *
	 * @param array $event Pole akce, nebo model s vlastnostmi region/department.
	 */
	public function lists_event( $event ): bool {
		$filter_val = $this->settings->get_option( 'id_code' );
		$filter_by  = $this->settings->code_type();

		if ( ! $filter_val || ! $filter_by ) {
			return false;
		}

		$region     = is_array( $event ) ? ( $event['region'] ?? '' ) : ( $event->region ?? '' );
		$department = is_array( $event ) ? ( $event['department'] ?? '' ) : ( $event->department ?? '' );

		if ( 'region' === $filter_by ) {
			return (string) $filter_val === (string) $region;
		}

		return (string) $filter_val === (string) $department;
	}
```

- [ ] **Step 2: Ověř syntaxi a chování**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Features/Events.php
```

Zapiš do `/Users/martin/Sites/sokct/lists.php`:

```php
<?php
$events = kct_container()->get( \Kct\Features\Events::class );
$all    = $events->get_events();
$mine   = array_filter( $all, static fn( $e ) => $events->lists_event( $e ) );

printf( "web %s (id_code %s)\n",
	parse_url( get_home_url(), PHP_URL_HOST ),
	get_option( 'kct_options', array() )['id_code'] ?? '—' );
printf( "  get_events() vrací:      %d\n", count( $all ) );
printf( "  lists_event() potvrdí:   %d\n", count( $mine ) );
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test eval-file lists.php && rm lists.php
```

Očekávaný výstup na sokct (kód 102, oblast): kolem 307 z 319.

**U odborových webů bude číslo výrazně nižší než počet vypisovaných akcí** a je
to správně: většinu jejich výpisu tvoří vlastní CPT akce, které nemají
vyplněné `region`/`department`, takže jimi `lists_event()` neprojde. Sdílení se
o ně stará běžnou cestou a `eligible()` je stejně přeskočí podle `post_type`.

Kdyby `lists_event()` potvrdilo nula i na sokct, filtr je obrácený a dál
nepokračuj. Nula na webu bez vyplněného `id_code` je naopak žádoucí — takový
web nesdílí nic.

---

## Task 7: Text pro databázovou akci

**Files:**
- Modify: `src/Facebook/MessageComposer.php`

- [ ] **Step 1: Vytáhni skládání do sdílené metody**

Nahraď metodu `event_message()`:

```php
	private function event_message( WP_Post $post ): string {
		$start = get_post_meta( $post->ID, 'start', true );

		// Knihovna wpify/custom-fields umí skupinu polí uložit i jako objekt
		// (stdClass) — Events::update_start_date() ošetřuje stejný případ ze
		// stejného důvodu.
		if ( is_object( $start ) ) {
			$start = (array) $start;
		} elseif ( ! is_array( $start ) ) {
			$start = array();
		}

		// U akcí importovaných z centrální databáze je 'start.date' prázdný
		// řetězec a skutečné datum leží v samostatné metě 'date' — klíč tedy
		// existuje, jen je prázdný, takže `??` by na ni nespadlo.
		return $this->event_lines(
			$post->post_title,
			(string) ( ! empty( $start['date'] ) ? $start['date'] : get_post_meta( $post->ID, 'date', true ) ),
			(string) ( ! empty( $start['time'] ) ? $start['time'] : '' ),
			(string) ( ! empty( $start['place'] ) ? $start['place'] : get_post_meta( $post->ID, 'place', true ) ),
			$this->excerpt( $post, self::MAX_EXCERPT )
		);
	}

	/**
	 * Text pozvánky na databázovou akci.
	 *
	 * Tytéž hodnoty jako u CPT akce, jen z pole místo z post meta. Formát
	 * skládá společná event_lines(), aby se obě cesty nerozešly.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 */
	public function db_event_message( array $event ): string {
		$start = is_array( $event['start'] ?? null ) ? $event['start'] : array();

		// Prázdné hodnoty přicházejí z importu jako prázdné POLE, ne prázdný
		// řetězec — `??` by se u nich nespustilo a přetypování pole na řetězec
		// by do textu propašovalo doslovné „Array". Proto se všude testuje
		// empty(), ne isset()/??.
		$excerpt = trim( wp_strip_all_tags( (string) ( $event['content'] ?? '' ) ) );

		if ( mb_strlen( $excerpt ) > self::MAX_EXCERPT ) {
			$excerpt = mb_substr( $excerpt, 0, self::MAX_EXCERPT ) . '…';
		}

		return $this->event_lines(
			(string) ( $event['title'] ?? '' ),
			(string) ( ! empty( $start['date'] ) ? $start['date'] : ( $event['date'] ?? '' ) ),
			(string) ( ! empty( $start['time'] ) ? $start['time'] : '' ),
			(string) ( ! empty( $start['place'] ) ? $start['place'] : ( $event['place'] ?? '' ) ),
			$excerpt
		);
	}

	/**
	 * Společný formát pozvánky: titulek, kdy, kde, perex.
	 */
	private function event_lines( string $title, string $date, string $time, string $place, string $excerpt ): string {
		$lines = array( $title );

		if ( '' !== $date ) {
			$lines[] = sprintf(
				/* translators: %s: naformátované datum (a případně čas) začátku akce. */
				__( 'Kdy: %s', 'kct' ),
				$this->format_event_date( $date, $time )
			);
		}

		if ( '' !== $place ) {
			$lines[] = sprintf(
				/* translators: %s: místo konání akce. */
				__( 'Kde: %s', 'kct' ),
				$place
			);
		}

		if ( '' !== $excerpt ) {
			$lines[] = '';
			$lines[] = $excerpt;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Text databázové akce i s odkazem na konci — pro sdílení fotkou.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 */
	public function db_event_message_with_link( array $event ): string {
		$link = $this->db_event_link( $event );

		return null === $link
			? $this->db_event_message( $event )
			: $this->db_event_message( $event ) . "\n\n" . $link;
	}

	/**
	 * Odkaz na detail databázové akce, nebo null bez db_id.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 */
	public function db_event_link( array $event ): ?string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		return $db_id ? home_url( 'akce-db/' . $db_id ) : null;
	}
```

- [ ] **Step 2: Ověř syntaxi a složený text**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/MessageComposer.php
```

Zapiš do `/Users/martin/Sites/sokct/msg.php`:

```php
<?php
$c      = kct_container()->get( \Kct\Facebook\MessageComposer::class );
$events = kct_container()->get( \Kct\Features\Events::class )->get_events();

foreach ( array_slice( array_values( $events ), 0, 2 ) as $e ) {
	echo "─── ", $e['title'], " ───\n";
	echo $c->db_event_message_with_link( $e ), "\n\n";
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test eval-file msg.php && rm msg.php
```

Očekávaný výstup: u obou akcí titulek, řádek `Kdy:`, případně `Kde:`, perex
a na posledním řádku adresa `https://sokct.test/akce-db/…`.

---

## Task 8: DbEventShare — denní úloha a odesílání

**Files:**
- Create: `src/Features/DbEventShare.php`
- Modify: `src/Managers/FeaturesManager.php`

- [ ] **Step 1: Vytvoř feature**

```php
<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use Kct\Facebook\DbShareState;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\Publisher;
use Kct\Facebook\ShareSchedule;

/**
 * Sdílení akcí z centrální databáze na Facebook.
 *
 * Tyhle akce nemají příspěvek, takže se jich běžné sdílení (Features\FacebookShare)
 * nedotkne — a je jich drtivá většina: z 319 akcí, které sokct.cz vypisuje, má
 * vlastní příspěvek 12.
 *
 * Odesílá je denní úloha. Ne plánování jednotlivých akcí dopředu: import akce
 * přepisuje, takže by změna termínu ve feedu nechala viset naplánované
 * odeslání na starý čas.
 */
class DbEventShare {

	const CRON_HOOK = 'kct_fb_share_due';

	/**
	 * Kolik dní zpět úloha hledá akce, kterým den odeslání už nastal.
	 *
	 * Pojistka pro případ, že web na den ztichne, cache se zpřísní nebo někdo
	 * předsadí CDN — WP-Cron se spouští při požadavcích na web. Měřeno na
	 * produkci je nejhorší zpoždění pod dvě hodiny, ale tři dny jsou levné.
	 *
	 * Z okna zároveň plyne, že se nemá co nahromadit: akce starší než tři dny
	 * se samy neodešlou nikdy, takže se při prvním spuštění nevysype historie.
	 */
	const WINDOW_DAYS = 3;

	public function __construct(
		private Events $events,
		private DbShareState $state,
		private Credentials $credentials,
		private MessageComposer $composer,
		private Publisher $publisher,
		private ShareSchedule $schedule,
		private OgImages $og_images
	) {
		add_action( 'init', array( $this, 'schedule_daily' ) );
		add_action( self::CRON_HOOK, array( $this, 'send_due' ) );
	}

	/**
	 * Naplánuje denní úlohu na hodinu z nastavení.
	 *
	 * Systémový cron se nevyžaduje — plugin se distribuuje i jako balíček pro
	 * weby mimo tuhle síť a nesmí potřebovat zásah do serveru.
	 */
	public function schedule_daily(): void {
		if ( ! $this->credentials->is_configured() ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		$zone  = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $zone );
		$first = $today->setTime( $this->schedule->hour(), 0 );

		if ( $first->getTimestamp() <= time() ) {
			$first = $first->modify( '+1 day' );
		}

		wp_schedule_event( $first->getTimestamp(), 'daily', self::CRON_HOOK );
	}

	/**
	 * Odešle akce, kterým den odeslání nastal.
	 */
	public function send_due(): void {
		if ( ! $this->credentials->is_configured() ) {
			return;
		}

		foreach ( $this->due() as $event ) {
			$this->send( $event );
		}
	}

	/**
	 * Akce, které tenhle web má právě teď odeslat.
	 *
	 * @return array<int, array>
	 */
	public function due(): array {
		if ( ! $this->credentials->share_default_for( 'akce' ) ) {
			return array();
		}

		$zone = wp_timezone();
		$lead = $this->schedule->lead_days();
		$out  = array();

		// Den odeslání je datum akce mínus odstup, a odstup je globální —
		// hledané datum akce je tedy dnešek plus odstup, plus okno pro dny,
		// kdy se úloha nespustila.
		for ( $back = 0; $back < self::WINDOW_DAYS; $back++ ) {
			$date = ( new \DateTimeImmutable( 'today', $zone ) )
				->modify( '+' . ( $lead - $back ) . ' days' )
				->format( 'Y-m-d' );

			foreach ( $this->events->get_events( $date, $date ) as $event ) {
				$db_id = (int) ( $event['db_id'] ?? 0 );

				if ( ! $db_id || ! $this->eligible( $event, $db_id ) ) {
					continue;
				}

				$out[ $db_id ] = $event;
			}
		}

		return array_values( $out );
	}

	/**
	 * Smí a má se tahle akce odeslat?
	 */
	private function eligible( array $event, int $db_id ): bool {
		// Akce s vlastním příspěvkem — o tu se stará běžné sdílení a odešla by
		// dvakrát.
		//
		// Pozná se podle klíče 'post_type': pole akce z databáze pochází
		// z DbEventModel a ten klíč nemá vůbec, kdežto akce s příspěvkem jde
		// přes EventModel (potomek Post), který ho nese vždy. Ověřeno na
		// datech porovnáním klíčů obou tvarů.
		if ( ! empty( $event['post_type'] ) ) {
			return false;
		}

		if ( ! $this->events->lists_event( $event ) ) {
			return false;
		}

		if ( $this->state->is_shared( $db_id ) || $this->state->is_disabled( $db_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Odešle jednu akci. Vrací true při úspěchu.
	 */
	public function send( array $event ): bool {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( ! $db_id || ! $this->credentials->is_configured() ) {
			return false;
		}

		if ( ! $this->state->claim( $db_id ) ) {
			return false;
		}

		try {
			if ( $this->state->is_shared( $db_id ) ) {
				return false;
			}

			$image = $this->og_images->social_for_event( $event );

			$result = $this->publisher->send(
				$this->composer->db_event_message( $event ),
				$this->composer->db_event_message_with_link( $event ),
				$this->composer->db_event_link( $event ),
				$image['url'] ?? null
			);

			if ( ! empty( $result['ok'] ) ) {
				$this->state->mark_shared( $db_id, (string) ( $result['id'] ?? '' ) );

				return true;
			}

			$this->state->mark_error( $db_id, (int) ( $result['code'] ?? 0 ), (string) ( $result['message'] ?? '' ) );

			return false;
		} finally {
			$this->state->release( $db_id );
		}
	}
}
```

- [ ] **Step 2: Zaregistruj feature**

V `src/Managers/FeaturesManager.php` přidej import
`use Kct\Features\DbEventShare;` a do konstruktoru jako poslední parametr
`DbEventShare $db_event_share`.

- [ ] **Step 3: Ověř syntaxi a sestavení**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Features/DbEventShare.php && php -l src/Managers/FeaturesManager.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: dvakrát `No syntax errors detected`, pak název webu.

---

## Task 9: Ovládání na stránce akce

**Files:**
- Modify: `src/Features/DbEventShare.php`
- Modify: `themes/kct/template-parts/content-akce.php`

- [ ] **Step 1: Přidej obsluhu akcí a text stavu**

Do `src/Features/DbEventShare.php` přidej do konstruktoru za stávající háky:

```php
		add_action( 'admin_post_kct_fb_db', array( $this, 'handle_action' ) );
```

a na konec třídy:

```php
	/**
	 * Obsluha tlačítek ze stránky akce.
	 *
	 * Přes admin-post.php, stejně jako stávající „Převést na vlastní akci" —
	 * potřebuje přihlášeného uživatele a nonce.
	 */
	public function handle_action(): void {
		$db_id = isset( $_REQUEST['db_id'] ) ? (int) $_REQUEST['db_id'] : 0;
		$do    = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : '';

		if ( ! $db_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'K této akci nemáte oprávnění.', 'kct' ) );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'kct-fb-db-' . $db_id ) ) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ) );
		}

		$event = $this->events->get_event( 0, $db_id );

		if ( $event && $this->events->lists_event( $event ) ) {
			if ( 'off' === $do ) {
				$this->state->set_disabled( $db_id, true );
			} elseif ( 'on' === $do ) {
				$this->state->set_disabled( $db_id, false );
			} elseif ( 'now' === $do ) {
				$this->send( $event );
			}
		}

		wp_safe_redirect( home_url( 'akce-db/' . $db_id ), 302, 'kct' );
		exit;
	}

	/**
	 * Řádek se stavem a odkazy pro stránku akce.
	 *
	 * Vrací hotové HTML, nebo prázdný řetězec, když se nemá co ukázat —
	 * u akce, kterou web nevypisuje, nebo bez nastaveného sdílení.
	 */
	public function control_html( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( ! $db_id || ! $this->credentials->is_configured() || ! $this->events->lists_event( $event ) ) {
			return '';
		}

		$url = static function ( string $do ) use ( $db_id ): string {
			return add_query_arg( array(
				'action'   => 'kct_fb_db',
				'db_id'    => $db_id,
				'do'       => $do,
				'_wpnonce' => wp_create_nonce( 'kct-fb-db-' . $db_id ),
			), admin_url( 'admin-post.php' ) );
		};

		if ( $this->state->is_shared( $db_id ) ) {
			return sprintf(
				'<p class="kct-fb-state">%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_html__( 'Odesláno na Facebook.', 'kct' ),
				esc_url( 'https://www.facebook.com/' . $this->state->fb_post_id( $db_id ) ),
				esc_html__( 'Zobrazit příspěvek', 'kct' )
			);
		}

		$note = $this->state->is_disabled( $db_id )
			? __( 'Na Facebook se neodešle.', 'kct' )
			: $this->due_note( $event );

		$toggle = $this->state->is_disabled( $db_id )
			? sprintf( '<a href="%s">%s</a>', esc_url( $url( 'on' ) ), esc_html__( 'Sdílet', 'kct' ) )
			: sprintf( '<a href="%s">%s</a>', esc_url( $url( 'off' ) ), esc_html__( 'Nesdílet', 'kct' ) );

		return sprintf(
			'<p class="kct-fb-state">%s &nbsp; %s &nbsp; <a href="%s">%s</a></p>',
			esc_html( $note ),
			$toggle,
			esc_url( $url( 'now' ) ),
			esc_html__( 'Odeslat hned', 'kct' )
		);
	}

	/**
	 * Kdy se akce odešle, slovy.
	 */
	private function due_note( array $event ): string {
		$date = '';

		foreach ( array( $event['start']['date'] ?? '', $event['date'] ?? '' ) as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$date = trim( $value );
				break;
			}
		}

		$timestamp = '' !== $date ? strtotime( $date ) : false;

		if ( ! $timestamp ) {
			return __( 'Akce nemá datum, na Facebook se neodešle.', 'kct' );
		}

		$send = $timestamp - ( $this->schedule->lead_days() * DAY_IN_SECONDS );

		if ( $send < strtotime( '-' . self::WINDOW_DAYS . ' days' ) ) {
			return __( 'Termín odeslání už uplynul — pošlete ručně.', 'kct' );
		}

		/* translators: %s: datum odeslání na Facebook. */
		return sprintf( __( 'Na Facebook se odešle %s.', 'kct' ), wp_date( 'j. n. Y', $send ) );
	}
```

- [ ] **Step 2: Vypiš ovládání na stránce akce**

V `themes/kct/template-parts/content-akce.php` najdi blok s odkazem
„Převést na vlastní akci a upravit" a doplň pod něj, uvnitř téhož
`if ( current_user_can( 'edit_posts' ) )`:

```php
					echo kct_container()->get( \Kct\Features\DbEventShare::class )->control_html( $event );
```

Výsledný blok:

```php
			if ( ! $db_event_id ) {
				kct_entry_footer();
			} else {
				if ( current_user_can( 'edit_posts' ) ) {
					$url = add_query_arg( array(
						'kct-action' => 'convert-action',
						'db_id'      => $db_event_id,
						'_wpnonce'   => wp_create_nonce( 'kct-convert-action' ),
					), admin_url( 'admin-post.php' ) );

					echo '<a class="" href="' . esc_url( $url ) . '">Převést na vlastní akci a upravit</a>';

					echo kct_container()->get( \Kct\Features\DbEventShare::class )->control_html( $event );
				}
			} ?>
```

- [ ] **Step 3: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Features/DbEventShare.php && php -l themes/kct/template-parts/content-akce.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct
curl -sk "https://sokct.test/akce-db/23954/" -o /dev/null -w "HTTP %{http_code}\n"
```

Očekávaný výstup: dvakrát `No syntax errors detected`, pak `HTTP 200`.
Ovládání se nepřihlášenému nezobrazí, to je správně.

---

## Task 10: WP-CLI a ověření výběru

**Files:**
- Modify: `src/CLI.php`

- [ ] **Step 1: Přidej příkaz**

Na konec třídy `Kct\CLI`, před uzavírací závorku:

```php
	/**
	 * Vypíše nebo odešle databázové akce, kterým nastal den odeslání.
	 *
	 * Ve výchozím stavu jen vypisuje. Prochází všechny weby v síti.
	 *
	 * ## OPTIONS
	 *
	 * [--send]
	 * : Opravdu odeslat na Facebook.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct fb_due
	 *     wp kct fb_due --send
	 */
	public function fb_due( $args, $assoc_args ) {
		$send  = ! empty( $assoc_args['send'] );
		$sites = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );

		WP_CLI::log( $send
			? __( 'Odesílám databázové akce …', 'kct' )
			: __( 'Nanečisto (odeslání zapneš přepínačem --send) …', 'kct' ) );

		$total = 0;

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			// SettingsRepository drží nastavení v paměti po celý proces
			// a kontejner je singleton — bez tohohle by všechny weby dostaly
			// nastavení toho prvního, včetně cizího Page ID a tokenu.
			kct_container()->get( \Kct\Repositories\SettingsRepository::class )->reset();

			$feature = kct_container()->get( \Kct\Features\DbEventShare::class );
			$due     = $feature->due();

			if ( $due ) {
				WP_CLI::log( sprintf( '  %s', parse_url( get_home_url( $site_id ), PHP_URL_HOST ) ) );
			}

			foreach ( $due as $event ) {
				$ok = $send ? $feature->send( $event ) : true;

				WP_CLI::log( sprintf(
					'    %-8s %-44s %s',
					$event['db_id'],
					mb_substr( (string) $event['title'], 0, 42 ),
					$send ? ( $ok ? 'odesláno' : 'CHYBA' ) : 'odeslalo by se'
				) );

				$total++;
			}

			restore_current_blog();
		}

		$message = sprintf(
			/* translators: %d: počet akcí. */
			__( 'Akcí: %d.', 'kct' ),
			$total
		);

		if ( $send ) {
			WP_CLI::success( $message );
		} else {
			WP_CLI::log( $message . ' ' . __( 'Nic se neodeslalo.', 'kct' ) );
		}
	}
```

- [ ] **Step 2: Přidej reset do SettingsRepository**

Bez toho by příkaz pro všechny weby použil nastavení prvního. V
`src/Repositories/SettingsRepository.php` přidej na konec třídy:

```php
	/**
	 * Zahodí nastavení drženou v paměti.
	 *
	 * Repozitář je v kontejneru singleton a options si drží po celý proces,
	 * takže po switch_to_blog() by dál vracel nastavení předchozího webu —
	 * včetně Page ID a tokenu Facebooku. Kdo přepíná weby, musí zavolat tohle.
	 */
	public function reset(): void {
		$this->options = [];
	}
```

- [ ] **Step 3: Ověř syntaxi a suchý běh**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/CLI.php && php -l src/Repositories/SettingsRepository.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test kct fb_due
```

Očekávaný výstup: hláška o suchém běhu, případně weby a akce, a na konci
`Nic se neodeslalo.` **Ve sloupci `fb_share` nesmí nic přibýt** — ověř:

```bash
echo "SELECT COUNT(*) FROM wp_db_events WHERE fb_share IS NOT NULL;" | ddev wp --url=https://sokct.test db query --skip-column-names
```

Očekávaný výstup: `0`.

- [ ] **Step 4: Ověř právo napříč weby**

Zapiš do `/Users/martin/Sites/sokct/rights.php`:

```php
<?php
foreach ( get_sites( array( 'number' => 0, 'fields' => 'ids' ) ) as $id ) {
	switch_to_blog( $id );
	kct_container()->get( \Kct\Repositories\SettingsRepository::class )->reset();

	$events = kct_container()->get( \Kct\Features\Events::class );
	$all    = $events->get_events();
	$mine   = array_filter( $all, static fn( $e ) => $events->lists_event( $e ) );

	printf( "%-28s id_code %-8s vypisuje %4d, právo na %4d\n",
		parse_url( get_home_url( $id ), PHP_URL_HOST ),
		get_option( 'kct_options', array() )['id_code'] ?? '—',
		count( $all ), count( $mine ) );

	restore_current_blog();
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file rights.php && rm rights.php
```

Očekávaný výstup: sokct (kód 102, oblast) má právo na výrazně víc akcí než
odborové weby (šestimístné kódy). Weby bez vyplněného `id_code` musí mít
právo na **0** akcí.

- [ ] **Step 5: Ověření na produkci po nasazení**

**Nasazení i odeslání spouští Martin, ne agent.** Po nasazení:

```bash
wp kct fb_due
```

Zkontroluje, co by se odeslalo a na kterém webu. Pak na konkrétní akci
tlačítkem **Odeslat hned** na `/akce-db/{id}` a ověří, že příspěvek na
Facebooku je fotka na výšku, adresa v textu vede správně a odkaz „Zobrazit
příspěvek" funguje.

Poslední kontrola je dotaz na stav:

```bash
wp db query "SELECT db_id, LEFT(title,40), fb_share FROM wp_db_events WHERE fb_share IS NOT NULL"
```

Pod klíčem webu musí být ID příspěvku ve tvaru `{page_id}_{post_id}`, ne
samotné ID fotky.

---

## Poznámky k údržbě

**Import maže řádky akcí, které feed označí `deleted=Y`** — se řádkem zmizí
i stav odeslání. Je to tak správně: zrušená akce, která by se do feedu
vrátila, je nová akce a pozvánka na ni se má poslat znovu.

**Okno tří dnů je pojistka, ne fronta.** Akce starší se samy neodešlou nikdy;
kdo chce takovou poslat, použije tlačítko na stránce akce.

**Kdo přepíná weby v jednom procesu, musí zavolat
`SettingsRepository::reset()`.** Bez toho dostane nastavení prvního webu,
včetně cizího Page ID a tokenu. Týká se to WP-CLI příkazů a všeho, co jede
pod `switch_to_blog()`.
