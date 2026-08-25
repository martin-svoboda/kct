# Automatické sdílení na Facebook — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-05-facebook-sharing-design.md`](../specs/2026-08-05-facebook-sharing-design.md)

**Cíl:** Po publikaci aktuality nebo události se automaticky odešle příspěvek na
Facebook stránku odboru, s viditelným stavem v editoru a s Open Graph tagy, aby
náhledová karta na FB vypadala k světu.

**Architektura:** Dvě nové features (`FacebookShare`, `OpenGraph`) registrované
v `FeaturesManager`, které kontejner PHP-DI sestaví sám. Vlastní logika je
v namespace `Kct\Facebook` rozdělená na čtyři třídy bez WP hooků (`Credentials`,
`GraphClient`, `MessageComposer`, `ShareState`) plus `ShareMetabox` pro výpis stavu
v editoru. Odesílání běží v `wp_schedule_single_event`, ne synchronně při uložení.

**Tech stack:** PHP 8.0+, WordPress multisite, PHP-DI (`config.php`),
wpify/custom-fields (metaboxy a options page), WP-CLI, Facebook Graph API.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám. Každý úkol proto končí
ověřením, ne commitem.

**V projektu není PHPUnit ani jiná testovací infrastruktura.** Ověřování stojí na
`php -l`, WP-CLI příkazech a kontrole v prohlížeči. Nezaváděj kvůli tomuto plánu
testovací framework — to je samostatné rozhodnutí.

**Příkazy:** lokálně přes ddev z kořene projektu (`/Users/martin/Sites/sokct`):

```bash
ddev wp kct fb_check
```

Na serveru totéž bez prefixu (`wp kct fb_check`). V plánu píšu `ddev wp`.

**Pozor — na Martinově stroji ddev nefunguje.** Chybí binárka `mkcert`, ddev selže na
vytváření certifikátů a hlásí „Project is not currently running", přestože kontejnery
běží. Dokud to platí, používej místo `ddev wp …`:

```bash
docker exec -u www-data ddev-sokct-web wp kct fb_check
```

a místo `ddev exec php -l …` systémové PHP:

```bash
php -l wp-content/plugins/kct/src/Facebook/GraphClient.php
```

**Názvy WP-CLI příkazů mají podtržítko, ne pomlčku.** WP-CLI registruje podpříkazy
pod jménem metody, takže funguje `wp kct fb_check`, ne `wp kct fb-check` — stejně
jako u stávajících `import_events` a `update_events`.

**Facebook lokálně nefunguje.** Graph API si musí stáhnout sdílený odkaz. Na ddev
jde ověřit všechno kromě skutečného odeslání (na to je `--dry-run`). Ostrý test
až na produkci nebo veřejném stagingu — to je poslední úkol plánu.

**Předpoklad pro úkoly 2, 5 a 8:** existuje Meta aplikace, page access token
a Page ID (viz sekce 2 specu). Bez nich se dá dokončit úkol 1, 3, 4 a 7.

**Coding standards:** WordPress Coding Standards, tabulátory, mezery uvnitř závorek
(`if ( ! empty( $var ) )`), pole přes `array()`. Řiď se okolním kódem v `src/`.

---

## Mapa souborů

| soubor | odpovědnost |
|---|---|
| `src/Facebook/Credentials.php` | **nový** — čte Page ID, token a nastavení; zná konstanty ve `wp-config.php` |
| `src/Facebook/GraphClient.php` | **nový** — HTTP volání Graph API, překlad odpovědi na jednotné pole |
| `src/Facebook/MessageComposer.php` | **nový** — složí text a odkaz z `WP_Post` |
| `src/Facebook/ShareState.php` | **nový** — jediné místo, které zná meta klíče |
| `src/Facebook/ShareMetabox.php` | **nový** — výpis stavu odeslání v editoru |
| `src/Features/FacebookShare.php` | **nový** — hooky, plánování cronu, obsluha chyb, metabox s poli |
| `src/Features/OpenGraph.php` | **nový** — OG tagy do `wp_head` |
| `src/Managers/FeaturesManager.php` | úprava — registrace obou features |
| `src/Settings.php` | úprava — sekce Facebook v nastavení |
| `src/CLI.php` | úprava — příkazy `fb_check` a `fb_share` |
| `src/Plugin.php` | úprava — úklid při deaktivaci a odinstalaci (závěrečná kontrola) |

Oproti specu přibyl `Credentials.php` (čtení konfigurace bylo ve specu popsané, ale
nemělo vlastní třídu) a `ShareMetabox.php` (stav se vypisuje ve vlastním nativním
metaboxu — `html` pole wpify/custom-fields se skládá při registraci, kdy ještě není
známý editovaný příspěvek). Chování odpovídá specu.

---

## Task 1: Konfigurace a nastavení

**Files:**
- Create: `src/Facebook/Credentials.php`
- Modify: `src/Settings.php` (konstruktor + metoda `setup()`, nová privátní metoda
  `forget_option_if_overridden_by_constant()`)

- [x] **Krok 1: Vytvoř třídu `Credentials`**

Soubor `src/Facebook/Credentials.php`:

```php
<?php

namespace Kct\Facebook;

use Kct\Repositories\SettingsRepository;

/**
 * Čte konfiguraci sdílení na Facebook.
 *
 * Konstanty ve wp-config.php mají přednost před hodnotami v nastavení, aby šel
 * token držet mimo databázi a mimo zálohy.
 */
class Credentials {
	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * ID Facebook stránky, na kterou se odesílají příspěvky.
	 */
	public function page_id(): string {
		if ( defined( 'KCT_FB_PAGE_ID' ) ) {
			return (string) KCT_FB_PAGE_ID;
		}

		return (string) $this->scalar_option( 'fb_page_id' );
	}

	/**
	 * Page access token pro Graph API.
	 *
	 * Vrací tajemství — nikdy ho nelogovat ani nikam nevypisovat (chybové
	 * hlášky, debug výstupy, WP-CLI apod.).
	 */
	public function token(): string {
		if ( defined( 'KCT_FB_PAGE_TOKEN' ) ) {
			return (string) KCT_FB_PAGE_TOKEN;
		}

		return (string) $this->scalar_option( 'fb_page_token' );
	}

	/**
	 * Jsou vyplněné obě hodnoty potřebné k odeslání na Facebook?
	 */
	public function is_configured(): bool {
		return '' !== $this->page_id() && '' !== $this->token();
	}

	/**
	 * Výchozí stav přepínače "Sdílet na Facebook" u nového příspěvku.
	 */
	public function share_by_default(): bool {
		return (bool) $this->settings->get_option( 'fb_share_default' );
	}

	/**
	 * ID přílohy s výchozím OG obrázkem.
	 */
	public function default_image_id(): int {
		return (int) $this->scalar_option( 'fb_default_image' );
	}

	/**
	 * Je hodnota daná konstantou ve wp-config.php?
	 *
	 * Testuje se jen defined() — prázdná konstanta je legitimní způsob, jak
	 * sdílení vypnout, a nemá tiše spadnout zpátky na hodnotu z nastavení.
	 */
	public function is_from_constant( string $field ): bool {
		return match ( $field ) {
			'fb_page_id'    => defined( 'KCT_FB_PAGE_ID' ),
			'fb_page_token' => defined( 'KCT_FB_PAGE_TOKEN' ),
			default         => false,
		};
	}

	/**
	 * Hodnota nastavení, jen pokud je skalární.
	 *
	 * Sanitizace v knihovně wpify/custom-fields je řízená seznamem `items` —
	 * klíč, který v `items` chybí (přesně případ pole přebitého konstantou),
	 * se uloží syrový z POSTu, klidně jako pole. `(string) $array` by pak
	 * vyhodilo "Array to string conversion".
	 */
	private function scalar_option( string $key ): string|int|float|bool|null {
		$value = $this->settings->get_option( $key );

		return is_scalar( $value ) ? $value : null;
	}
}
```

- [x] **Krok 2: Ověř syntaxi**

```bash
ddev exec php -l wp-content/plugins/kct/src/Facebook/Credentials.php
```

Očekávaný výstup: `No syntax errors detected`

- [x] **Krok 3: Přidej sekci Facebook do nastavení**

V `src/Settings.php` přidej import `use Kct\Facebook\Credentials;`. Konstruktor
uprav na injekci `Credentials` (zbytek projektu injektuje služby konstruktorem,
service locator `kct_container()->get()` je tu jen výjimka pro věci mimo DI, jako
`SettingsRepository` ve starém zakomentovaném kódu):

```php
	public function __construct( CustomFields $wcf, private Credentials $credentials ) {
		$this->wcf = $wcf;

		$this->setup();

		add_action( 'admin_notices', array( $this, 'settings_notices' ) );
	}
```

Na začátek metody `setup()` (před sestavením pole `$settings`) přidej:

```php
		// Konstanta ve wp-config.php má přednost — pole v nastavení se pak
		// zobrazí jen jako needitovatelná informace, kde hodnota vznikla.
		// Dřívější hodnota uložená v nastavení se zároveň z databáze smaže —
		// jinak by dál ležela v autoloadované option, byla v každé záloze a
		// knihovna by ji dál posílala do HTML administrace (read-only režim
		// jen skryje input, hodnotu neschová).
		if ( $this->credentials->is_from_constant( 'fb_page_id' ) ) {
			$this->forget_option_if_overridden_by_constant( 'fb_page_id' );

			$fb_page_id_field = array(
				'id'      => 'fb_page_id_readonly',
				'type'    => 'html',
				'content' => sprintf(
					'<p><strong>%s</strong> — %s</p>',
					esc_html__( 'ID Facebook stránky', 'kct' ),
					esc_html__( 'hodnotu přebíjí konstanta KCT_FB_PAGE_ID ve wp-config.php.', 'kct' )
				),
			);
		} else {
			$fb_page_id_field = array(
				'title' => __( 'ID Facebook stránky', 'kct' ),
				'desc'  => __( 'Číselné ID stránky, na kterou se budou odesílat příspěvky.', 'kct' ),
				'id'    => 'fb_page_id',
				'type'  => 'text',
			);
		}

		if ( $this->credentials->is_from_constant( 'fb_page_token' ) ) {
			$this->forget_option_if_overridden_by_constant( 'fb_page_token' );

			$fb_page_token_field = array(
				'id'      => 'fb_page_token_readonly',
				'type'    => 'html',
				'content' => sprintf(
					'<p><strong>%s</strong> — %s</p>',
					esc_html__( 'Page access token', 'kct' ),
					esc_html__( 'hodnota je nastavena konstantou KCT_FB_PAGE_TOKEN ve wp-config.php. Případná dříve uložená hodnota byla z databáze odstraněna.', 'kct' )
				),
			);
		} else {
			$fb_page_token_field = array(
				'title' => __( 'Page access token', 'kct' ),
				'desc'  => __( 'Dlouhodobý token stránky z Meta aplikace. Pozor: token uložený zde leží v autoloadované databázové option, načítá se při každém requestu webu a je součástí každé zálohy databáze. Bezpečnější je definovat konstantu KCT_FB_PAGE_TOKEN ve wp-config.php.', 'kct' ),
				'id'    => 'fb_page_token',
				'type'  => 'password',
			);
		}
```

Pozn. k `'type' => 'html'` položkám: knihovna wpify/custom-fields tenhle typ pole
vykresluje bez popisku (`noLabel: true`), proto je název sloučený rovnou do
`content` a klíč `'title'` se u nich vůbec nepoužívá.

Do pole `$settings` pak za položku `id_code` přidej:

```php
			array(
				'id'    => 'fb_section',
				'title' => __( 'Sdílení na Facebook', 'kct' ),
				'type'  => 'title',
			),
			$fb_page_id_field,
			$fb_page_token_field,
			array(
				'title' => __( 'Sdílet automaticky', 'kct' ),
				'label' => __( 'Nové aktuality a akce mají sdílení ve výchozím stavu zapnuté.', 'kct' ),
				'id'    => 'fb_share_default',
				'type'  => 'toggle',
			),
			array(
				'title' => __( 'Výchozí náhledový obrázek', 'kct' ),
				'desc'  => __( 'Použije se u příspěvků bez náhledového obrázku.', 'kct' ),
				'id'    => 'fb_default_image',
				'type'  => 'attachment',
			),
```

Pozn.: položka typu `title` musí mít vlastní `id` (`fb_section`) — knihovna
wpify/custom-fields jinak přiřadí `uniqid()` a group sanitizér by ho při
každém uložení nastavení zapsal do autoloadované option `kct_options`, kde by
se navždy hromadily náhodné klíče.

Za metodu `setup()` přidej privátní metodu, která smaže z `kct_options` hodnotu
pole přebitého konstantou (jen pokud tam ještě je — nezapisuje se do DB při
každém requestu):

```php
	private function forget_option_if_overridden_by_constant( string $key ): void {
		$options = get_option( self::KEY, array() );

		if ( empty( $options[ $key ] ) ) {
			return;
		}

		unset( $options[ $key ] );
		update_option( self::KEY, $options );
	}
```

- [x] **Krok 4: Zkontroluj nastavení v administraci**

```bash
ddev launch /wp-admin/options-general.php?page=kct_options
```

Očekávané: pod polem s kódem odboru je sekce „Sdílení na Facebook" a v ní čtyři
řádky — bez konstant ve `wp-config.php` to jsou dvě editovatelná pole (ID
stránky, token) a dva přepínače/výběr (Sdílet automaticky, Výchozí obrázek). Je-li
definovaná konstanta `KCT_FB_PAGE_ID` a/nebo `KCT_FB_PAGE_TOKEN`, odpovídající
pole se nahradí needitovatelným informačním řádkem — pořád čtyři řádky v sekci,
ale ne čtyři shodná pole k vyplnění.

Vyplň testovací hodnotu do „ID Facebook stránky", ulož a ověř, že se hodnota vrátí:

```bash
ddev wp eval 'var_dump( kct_container()->get( Kct\Facebook\Credentials::class )->page_id() );'
```

Očekávané: vypíše zadanou hodnotu.

---

## Task 2: Klient Graph API a příkaz `fb_check`

**Files:**
- Create: `src/Facebook/GraphClient.php`
- Modify: `src/CLI.php`

- [x] **Krok 1: Vytvoř klienta**

Soubor `src/Facebook/GraphClient.php`:

```php
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
```

- [x] **Krok 2: Ověř syntaxi**

```bash
ddev exec php -l wp-content/plugins/kct/src/Facebook/GraphClient.php
```

Očekávaný výstup: `No syntax errors detected`

- [x] **Krok 3: Přidej příkaz `fb_check` do CLI**

V `src/CLI.php` přidej import `use Kct\Facebook\Credentials;` a `use Kct\Facebook\GraphClient;`
a novou metodu za `update_events()`:

```php
	/**
	 * Ověří připojení k Facebooku a vypíše název připojené stránky.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct fb_check
	 */
	public function fb_check() {
		$credentials = kct_container()->get( Credentials::class );

		if ( ! $credentials->is_configured() ) {
			WP_CLI::error( __( 'Chybí Page ID nebo token. Vyplň je v Nastavení → KČT.', 'kct' ) );
		}

		$result = kct_container()->get( GraphClient::class )->verify( $credentials->token() );

		if ( empty( $result['ok'] ) ) {
			// Kód 0 znamená, že se nepodařilo Facebook vůbec zastihnout (chyba
			// spojení) — od skutečné chybové odpovědi API to je potřeba odlišit.
			if ( 0 === (int) $result['code'] ) {
				WP_CLI::error( sprintf( __( 'Nepodařilo se spojit s Facebookem: %s', 'kct' ), $result['message'] ) );
			}

			WP_CLI::error( sprintf( __( 'Facebook vrátil chybu %1$d: %2$s', 'kct' ), $result['code'], $result['message'] ) );
		}

		$page_name = $result['name'] ?? '';
		$page_id   = $result['id'] ?? '?';

		WP_CLI::success( sprintf( __( 'Připojeno ke stránce „%1$s“ (ID %2$s).', 'kct' ), $page_name, $page_id ) );

		// /me vrací identitu, ke které token patří — u uživatelského tokenu
		// nebo tokenu jiné stránky se liší od Page ID v nastavení.
		if ( isset( $result['id'] ) && $result['id'] !== $credentials->page_id() ) {
			WP_CLI::warning( sprintf(
				__( 'Token patří k jinému účtu, než je nastavené Page ID. Vráceno ID %1$s, v nastavení je %2$s.', 'kct' ),
				$result['id'],
				$credentials->page_id()
			) );
		}
	}
```

- [x] **Krok 4: Ověř příkaz bez konfigurace**

Vyprázdni pole s Page ID v nastavení a spusť:

```bash
ddev wp kct fb_check
```

Očekávané: `Error: Chybí Page ID nebo token. Vyplň je v Nastavení → KČT.`

- [x] **Krok 5: Ověř příkaz s konfigurací**

Vyplň skutečné Page ID a token, pak:

```bash
ddev wp kct fb_check
```

Očekávané: `Success: Připojeno ke stránce „…“ (ID …).` Pokud vrácené ID neodpovídá
nastavenému Page ID (token patří k jinému účtu/stránce), přibude ještě
`Warning: Token patří k jinému účtu…`.

Pokud token ještě nemáš, tento krok odlož na úkol 8 a pokračuj dál.

---

## Task 3: Skládání textu a příkaz `fb_share --dry-run`

**Files:**
- Create: `src/Facebook/MessageComposer.php`
- Modify: `src/CLI.php`

- [x] **Krok 1: Vytvoř `MessageComposer`**

Soubor `src/Facebook/MessageComposer.php`:

```php
<?php

namespace Kct\Facebook;

use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PostPostType;
use WP_Post;

/**
 * Skládá text a odkaz příspěvku na Facebook.
 *
 * Čistá třída — žádné HTTP, žádný zápis do databáze.
 */
class MessageComposer {
	/**
	 * Max. délka perexu běžné aktuality a akce ve znacích.
	 *
	 * Redakční limit, ne technický strop — Facebook zobrazí v náhledu jen
	 * první pár řádků, delší text čtenář stejně nedočte bez rozkliknutí.
	 */
	const MAX_EXCERPT = 300;

	/**
	 * Krátká aktualita se nekrátí vůbec — nemá odkaz na detail (viz link()),
	 * takže useknutý konec by byl pro čtenáře nedostupný. Tahle konstanta
	 * proto není redakční limit, ale tvrdá pojistka proti stropu délky
	 * příspěvku na zeď stránky v Graph API (řádově desítky tisíc znaků).
	 */
	const MAX_SHORT_NEWS = 60000;

	/**
	 * Text příspěvku. Vlastní text redaktora má vždy přednost.
	 */
	public function compose( WP_Post $post ): string {
		$custom = get_post_meta( $post->ID, ShareState::META_MESSAGE, true );

		if ( ! empty( $custom ) ) {
			return trim( (string) $custom );
		}

		if ( EventPostType::KEY === $post->post_type ) {
			return $this->event_message( $post );
		}

		$length = $this->is_short_news( $post ) ? self::MAX_SHORT_NEWS : self::MAX_EXCERPT;

		return trim( $post->post_title . "\n\n" . $this->excerpt( $post, $length ) );
	}

	/**
	 * Odkaz, ze kterého Facebook složí náhledovou kartu.
	 *
	 * Krátké aktuality nemají proklik na detail — obsah se zobrazuje celý ve
	 * výpise a web na detail nikde neodkazuje. Posílají se proto bez odkazu.
	 */
	public function link( WP_Post $post ): ?string {
		if ( $this->is_short_news( $post ) ) {
			return null;
		}

		$permalink = get_permalink( $post );

		return $permalink ? $permalink : null;
	}

	private function is_short_news( WP_Post $post ): bool {
		return PostPostType::KEY === $post->post_type && (bool) get_post_meta( $post->ID, 'short_news', true );
	}

	private function event_message( WP_Post $post ): string {
		$start = get_post_meta( $post->ID, 'start', true );

		// Knihovna wpify/custom-fields umí skupinu polí uložit i jako objekt
		// (stdClass) — Events::update_start_date() ošetřuje stejný případ ze
		// stejného důvodu. Bez toho by is_array() selhalo, $start by zůstalo
		// prázdné pole a fallback níž by vzal místo ze samostatné mety misto
		// hodnoty, kterou redaktor skutečně vyplnil ve skupině 'start'.
		if ( is_object( $start ) ) {
			$start = (array) $start;
		} elseif ( ! is_array( $start ) ) {
			$start = array();
		}

		// U akcí importovaných z centrální databáze KČT je 'start.date' uložené
		// jako prázdný řetězec a skutečné datum leží v samostatné metě 'date' —
		// klíč v poli tedy existuje, jen je prázdný, takže by ho `??` nechalo
		// být a na samostatnou metu vůbec nespadlo. Proto se testuje empty(),
		// ne isset().
		$date  = ! empty( $start['date'] ) ? $start['date'] : get_post_meta( $post->ID, 'date', true );
		$time  = ! empty( $start['time'] ) ? $start['time'] : '';
		$place = ! empty( $start['place'] ) ? $start['place'] : get_post_meta( $post->ID, 'place', true );

		$lines = array( $post->post_title );

		if ( ! empty( $date ) ) {
			$lines[] = sprintf(
				/* translators: %s: naformátované datum (a případně čas) začátku akce. */
				__( 'Kdy: %s', 'kct' ),
				$this->format_event_date( (string) $date, (string) $time )
			);
		}

		if ( ! empty( $place ) ) {
			$lines[] = sprintf(
				/* translators: %s: místo konání akce. */
				__( 'Kde: %s', 'kct' ),
				$place
			);
		}

		$excerpt = $this->excerpt( $post, self::MAX_EXCERPT );

		if ( '' !== $excerpt ) {
			$lines[] = '';
			$lines[] = $excerpt;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Naformátuje datum akce v časovém pásmu webu.
	 *
	 * strtotime() by 'Y-m-d' přečetl jako půlnoc UTC a datum ve formátu jako
	 * '01/02/2026' navíc americky (2. ledna místo 1. února) — proto se
	 * parsuje explicitně podle očekávaného formátu a v časovém pásmu webu.
	 * Nepodaří-li se datum takhle přečíst, vypíše se syrová hodnota z pole,
	 * ať redaktor vidí, že je potřeba ji opravit, místo aby zmizela úplně.
	 *
	 * createFromFormat() navíc přetečené datum (např. '2026-02-30' nebo
	 * '0000-00-00' z importu KČT) tiše přepočítá na jiný, validní den —
	 * vrátí objekt, ale zpřístupní varování přes getLastErrors(). Bez téhle
	 * kontroly by na Facebook odešlo sebevědomě špatné datum. Od PHP 8.2
	 * vrací getLastErrors() `false` (ne prázdné pole), když je vše v pořádku.
	 */
	private function format_event_date( string $date, string $time ): string {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();

		$invalid = is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) );

		if ( ! $parsed || $invalid ) {
			return '' !== $time ? $date . ', ' . $time : $date;
		}

		// Dny v týdnu se uprostřed české věty píší malým písmenem ("Kdy:
		// sobota …", ne "Kdy: Sobota …").
		$formatted = mb_strtolower( wp_date( 'l j. n. Y', $parsed->getTimestamp() ), 'UTF-8' );

		if ( '' !== $time ) {
			$formatted .= ', ' . $time;
		}

		return $formatted;
	}

	/**
	 * Perex bez HTML, zkrácený na daný počet znaků.
	 */
	private function excerpt( WP_Post $post, int $length ): string {
		$text = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;

		// Bloky serializované na jednom řádku (tabulka, nadpis + odstavec z
		// klasického editoru) by se bez zalomení slepily do jednoho slova
		// ("Trasa15 kmCenazdarma") — zalomení se vkládá ještě před stripem
		// tagů, dokud jsou uzavírací tagy k dispozici.
		$text = preg_replace( '#</(p|div|li|h[1-6]|tr|td|th|figcaption|blockquote)>#i', '$0' . "\n", $text ) ?? $text;
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );

		// Entity se dekódují až po odstranění tagů — jinak by '&lt;script&gt;'
		// ožilo jako skutečný tag. Bez dekódování by navíc zůstávalo '&nbsp;'
		// za každou jednopísmennou předložkou, kterou tam sype Gutenberg.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$text = preg_replace( '/[ \t\x{00a0}]+/u', ' ', $text ) ?? '';
		$text = preg_replace( '/\n{3,}/u', "\n\n", $text ) ?? '';
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return $this->truncate( $text, $length );
	}

	/**
	 * Zkrátí text na hranici slova a odstraní případnou useknutou entitu na
	 * konci — bez toho by řez uprostřed slova nebo uprostřed entity vypadal
	 * nedbale ("Trasa vede kolem přehra…").
	 */
	private function truncate( string $text, int $length ): string {
		$cut = mb_substr( $text, 0, $length );
		$cut = preg_replace( '/&[^;\s]{0,8}$/u', '', $cut ) ?? $cut;

		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > (int) ( $length * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		// rtrim() pracuje po bajtech — vícebajtové znaky jako pomlčka U+2013
		// by se v seznamu znaků k odstranění rozpadly na jednotlivé bajty a
		// klidně odřízly poslední bajt platného vícebajtového znaku (třeba
		// 'Ó') těsně před nimi. preg_replace() s '/u' pracuje po znacích.
		$cut = preg_replace( '/[\s,.;:–—-]+$/u', '', $cut ) ?? $cut;

		return $cut . '…';
	}
}
```

Pozn.: `wp_strip_all_tags()` odstraní i HTML komentáře, kterými Gutenberg obaluje
bloky (`<!-- wp:paragraph -->`), takže z obsahu zůstane čistý text.

- [x] **Krok 2: Doplň konstantu meta klíče**

`MessageComposer` používá `ShareState::META_MESSAGE`, který vznikne až v úkolu 4.
Aby šel úkol ověřit hned, vytvoř zatím `src/Facebook/ShareState.php` jen s konstantami:

```php
<?php

namespace Kct\Facebook;

/**
 * Meta klíče používané při sdílení příspěvku na Facebook.
 *
 * V tomto úkolu obsahuje jen konstanty — metody pro čtení a zápis stavu
 * (is_shared(), mark_shared(), mark_error() apod.) přibudou v úkolu 4.
 */
class ShareState {
	const META_SHARE    = 'kct_fb_share';
	const META_MESSAGE  = 'kct_fb_message';
	const META_POST_ID  = 'kct_fb_post_id';
	const META_TIME     = 'kct_fb_shared_at';
	const META_ERROR    = 'kct_fb_error';
	const META_ATTEMPTS = 'kct_fb_attempts';
}
```

Metody se doplní v úkolu 4.

- [x] **Krok 3: Přidej příkaz `fb_share` do CLI**

V `src/CLI.php` přidej import `use Kct\Facebook\MessageComposer;`, `use Kct\Facebook\ShareState;` a metodu:

```php
	/**
	 * Odešle příspěvek na Facebook, nebo jen vypíše, co by odeslal.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : ID příspěvku.
	 *
	 * [--dry-run]
	 * : Jen vypíše text a odkaz, nic neodešle.
	 *
	 * [--force]
	 * : Odešle příspěvek, i když už na Facebooku odeslaný byl. Bez tohoto přepínače takový příspěvek příkaz odmítne, aby na zdi nevznikl duplikát.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct fb_share 123 --dry-run
	 *     wp kct fb_share 123
	 *     wp kct fb_share 123 --force
	 */
	public function fb_share( $args, $assoc_args ) {
		$post_id = intval( $args[0] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			WP_CLI::error( sprintf( __( 'Příspěvek %d neexistuje.', 'kct' ), $post_id ) );
		}

		$state = kct_container()->get( ShareState::class );

		if (
			empty( $assoc_args['dry-run'] )
			&& empty( $assoc_args['force'] )
			&& $state->is_shared( $post_id )
		) {
			WP_CLI::error( sprintf(
				/* translators: %s: ID příspěvku na Facebooku. */
				__( 'Příspěvek už na Facebooku odeslaný je (ID %s). Opakované odeslání vynutíš přepínačem --force.', 'kct' ),
				$state->fb_post_id( $post_id )
			) );
		}

		$composer = kct_container()->get( MessageComposer::class );
		$message  = $composer->compose( $post );
		$link     = $composer->link( $post );

		if ( '' === trim( $message ) ) {
			WP_CLI::error( __( 'Příspěvek nemá co odeslat — složený text vyšel prázdný.', 'kct' ) );
		}

		WP_CLI::line( '--- text ---' );
		WP_CLI::line( $message );
		WP_CLI::line( '--- odkaz ---' );
		WP_CLI::line( $link ? $link : __( '(bez odkazu)', 'kct' ) );

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			return;
		}

		$credentials = kct_container()->get( Credentials::class );

		if ( ! $credentials->is_configured() ) {
			WP_CLI::error( __( 'Chybí Page ID nebo token. Vyplň je v Nastavení → KČT.', 'kct' ) );
		}

		$result = kct_container()->get( GraphClient::class )->publish(
			$credentials->page_id(),
			$credentials->token(),
			$message,
			$link
		);

		if ( empty( $result['ok'] ) ) {
			$state->mark_error( $post_id, (int) $result['code'], (string) $result['message'] );

			WP_CLI::error( sprintf( __( 'Facebook vrátil chybu %1$d: %2$s', 'kct' ), $result['code'], $result['message'] ) );
		}

		// Stav se zapisuje i po ručním odeslání — jinak by ho naplánovaná
		// cron událost o minutu později odeslala znovu a v editoru by po
		// ručním odeslání nezůstala žádná stopa.
		$state->mark_shared( $post_id, (string) $result['id'] );

		WP_CLI::success( sprintf( __( 'Odesláno, ID příspěvku %s.', 'kct' ), $result['id'] ) );
	}
```

> **Pozn. z kontroly kvality (po Tasku 5):** Příkaz původně `ShareState` úplně
> obcházel — po ručním odeslání nezavolal `mark_shared()`, takže naplánovaná
> cron událost poslala tentýž příspěvek o minutu později znovu a v metaboxu po
> ručním odeslání nezůstala stopa. Kód výše je opravená verze: zapisuje stav,
> už odeslaný příspěvek odmítá a obejít to jde přepínačem `--force`. Popis
> `--force` musí být na jednom řádku — WP-CLI rozbíjí víceřádkový popis volby
> na „invalid synopsis part".

- [x] **Krok 4: Ověř text u běžné aktuality**

Najdi ID libovolné publikované aktuality a spusť:

```bash
ddev wp post list --post_type=post --post_status=publish --field=ID --posts_per_page=1
ddev wp kct fb_share <id> --dry-run
```

Očekávané: titulek, prázdný řádek, perex do 300 znaků a pod ním permalink článku.

- [x] **Krok 5: Ověř text u krátké aktuality**

```bash
ddev wp post meta update <id> short_news 1
ddev wp kct fb_share <id> --dry-run
ddev wp post meta delete <id> short_news
```

Očekávané: v druhém výpisu je delší text a místo odkazu `(bez odkazu)`.

- [x] **Krok 6: Ověř text u události**

```bash
ddev wp post list --post_type=akce --post_status=publish --field=ID --posts_per_page=1
ddev wp kct fb_share <id> --dry-run
```

Očekávané: titulek, řádek `Kdy: sobota 12. 7. 2026, 9:00` (podle dat akce), řádek
`Kde: …`, prázdný řádek a perex. Řádky bez dat se vynechají — ověř na akci bez času.

- [x] **Krok 7: Ověř vlastní text**

```bash
ddev wp post meta update <id> kct_fb_message 'Vlastní text příspěvku'
ddev wp kct fb_share <id> --dry-run
ddev wp post meta delete <id> kct_fb_message
```

Očekávané: vypíše se přesně `Vlastní text příspěvku`.

---

## Task 4: Stav sdílení a rozhraní v editoru

**Files:**
- Modify: `src/Facebook/ShareState.php` (doplnění metod)
- Create: `src/Facebook/ShareMetabox.php`

- [x] **Krok 1: Doplň metody do `ShareState`**

Přepiš `src/Facebook/ShareState.php` na:

```php
<?php

namespace Kct\Facebook;

/**
 * Jediné místo, které zná meta klíče sdílení na Facebook.
 */
class ShareState {
	const META_SHARE    = 'kct_fb_share';
	const META_MESSAGE  = 'kct_fb_message';
	const META_POST_ID  = 'kct_fb_post_id';
	const META_TIME     = 'kct_fb_shared_at';
	const META_ERROR    = 'kct_fb_error';
	const META_ATTEMPTS = 'kct_fb_attempts';

	/** Nejdelší uložená délka chybové zprávy ve znacích. */
	const MAX_ERROR_LENGTH = 500;

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
}
```

> **Pozn. z kontroly kvality (Task 4 po dokončení):** Původní verze kódu výše
> (ta, která šla do prvního běhu úkolu) mazala `META_ERROR` i v `reset_attempts()`
> a `mark_shared()` s prázdným `$fb_post_id` mlčky uložila mrtvý stav. Obsluha
> tlačítka „Zkusit znovu" v Tasku 6 volá `reset_attempts()` a hned potom
> `share()` — a `share()` může tiše skončit bez odeslání (přepínač mezitím
> vypnutý, článek vrácený do konceptu apod.). Smazání chyby v `reset_attempts()`
> by v tom případě chybu beze stopy odstranilo. Kód výše je opravená verze,
> která do repozitáře skutečně šla — code review navíc přidalo useknutí
> chybové zprávy na 500 znaků a docblocky ke všem metodám.

- [x] **Krok 2: Vytvoř metabox se stavem**

Soubor `src/Facebook/ShareMetabox.php`:

```php
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
```

> **Pozn. z kontroly kvality:** Oproti původnímu návrhu výše je opravená verze
> bez inline stylu (`notice notice-error inline` misto `style="color:…"` —
> respektuje i tmavé schéma administrace), bez zdvojeného escapování
> (`wp_nonce_url()` už vrací escapovaný výstup, `esc_url()` navrch byl
> redundantní), s `translators:` komentáři u obou překladů s `%s`, s kontrolou
> `current_user_can( 'edit_post', … )` před vypsáním tlačítka „Zkusit znovu"
> (uživatel bez práva vidí jen text chyby) a s ošetřením `shared_at() === 0`
> (chybějící `META_TIME` by jinak vypsalo „1. 1. 1970").

- [x] **Krok 3: Ověř syntaxi a standardy obou souborů**

```bash
ddev exec php -l wp-content/plugins/kct/src/Facebook/ShareState.php
ddev exec php -l wp-content/plugins/kct/src/Facebook/ShareMetabox.php
ddev exec vendor/bin/phpcs --standard=WordPress-Core wp-content/plugins/kct/src/Facebook/ShareState.php wp-content/plugins/kct/src/Facebook/ShareMetabox.php
```

Očekávané: dvakrát `No syntax errors detected` a v `phpcs` nula chyb kromě
pravidla na název souboru (`class-*.php`) — PSR-4 autoloading je celoprojektový
záměr, ne chyba tohoto souboru.

Metabox se zatím nikde neregistruje — to udělá `FacebookShare` v úkolu 5.

Do `ShareState` navíc v úkolu 5 přibude zámek odesílání (`claim()`, `release()`)
— tady ještě není potřeba, protože nic neodesílá.

---

## Task 5: Feature `FacebookShare` — plánování a odeslání

**Files:**
- Create: `src/Features/FacebookShare.php`
- Modify: `src/Facebook/ShareState.php` (zámek proti souběžnému odeslání)
- Modify: `src/Managers/FeaturesManager.php`

- [x] **Krok 1: Doplň do `ShareState` zámek odesílání**

Mezi kontrolou `is_shared()` a zápisem `mark_shared()` leží celé HTTP volání
Graph API. Bez zámku projdou dva souběžné běhy (dvě spuštění wp-cronu nad
toutéž událostí) obojí kontrolami, obojí zavolají `publish()` a na Facebooku
zůstane duplicitní příspěvek, o kterém WordPress neví — uloží se jen ID toho,
kdo zapsal poslední. Do `src/Facebook/ShareState.php` proto přidej konstanty
a vlastnost

```php
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
```

a metody:

```php
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
```

Tři místa, kde se to dá snadno pokazit:

1. **`add_option()` atomický není.** V aktuálním WordPressu (ověřeno na 6.8)
   zapisuje přes `INSERT ... ON DUPLICATE KEY UPDATE option_value =
   VALUES(option_value)`, takže když dva souběžné běhy projdou její
   kontrolou existence dřív, než kterýkoli z nich stihne zapsat, druhý
   přepíše hodnotu prvního a dostane zpět úspěch — oba by si mysleli, že
   zámek drží. Proto vlastní prostý `INSERT`, který skončí na duplicitním
   klíči.
2. **Převzetí vypršelého zámku musí být jeden příkaz.** `delete_option()`
   plus nový `INSERT` jsou dva kroky — když dva běhy přečtou tentýž
   vypršelý čas, uspějí oba. Podmíněný `UPDATE ... WHERE option_value < …`
   nechá rozhodnout řádkový zámek InnoDB.
3. **`release()` smí mazat jen vlastní zámek.** Bez podmínky na hodnotu by
   běh, kterému zámek mezitím vypršel, smazal zámek svému nástupci.

- [x] **Krok 2: Vytvoř feature**

Soubor `src/Features/FacebookShare.php`:

```php
<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use Kct\Facebook\GraphClient;
use Kct\Facebook\MessageComposer;
use Kct\Facebook\ShareMetabox;
use Kct\Facebook\ShareState;
use Kct\PostTypes\EventPostType;
use Kct\PostTypes\PostPostType;
use KctDeps\Wpify\CustomFields\CustomFields;
use WP_Post;

/**
 * Automatické sdílení publikovaných aktualit a akcí na Facebook stránku.
 */
class FacebookShare {
	const CRON_HOOK = 'kct_facebook_share';

	/** Zpoždění odeslání po publikaci, aby se stihla uložit metadata. */
	const DELAY = 60;

	/**
	 * Metabox se stavem odeslání.
	 *
	 * Drží se ve vlastnosti, ne jen v uzávěře háčku — instance jinak nemá
	 * jiného vlastníka než add_action() uvnitř svého konstruktoru a její
	 * životnost by závisela na tom, co se s háčkem stane.
	 *
	 * @var ShareMetabox
	 */
	private ShareMetabox $metabox;

	/**
	 * @param CustomFields    $wcf         Knihovna pro metabox s poli redaktora.
	 * @param Credentials     $credentials Konfigurace sdílení.
	 * @param GraphClient     $client      Klient Graph API.
	 * @param MessageComposer $composer    Skládání textu a odkazu.
	 * @param ShareState      $state       Stav odeslání v post meta.
	 */
	public function __construct(
		private CustomFields $wcf,
		private Credentials $credentials,
		private GraphClient $client,
		private MessageComposer $composer,
		private ShareState $state
	) {
		add_action( 'transition_post_status', array( $this, 'maybe_schedule' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'share' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'is_protected_meta', array( $this, 'protect_meta' ), 10, 2 );

		$this->register_metabox();

		// ShareMetabox se tvoří přímo, ne přes kontejner: potřebuje seznam
		// typů příspěvků, který je definovaný tady ve feature, takže by ho
		// kontejner stejně nedokázal sestavit bez další konfigurace.
		$this->metabox = new ShareMetabox( $this->state, $this->post_types() );
	}

	/**
	 * Typy příspěvků, které se sdílejí na Facebook.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		return array( PostPostType::KEY, EventPostType::KEY );
	}

	/**
	 * Naplánuje odeslání při přechodu do stavu publikováno.
	 *
	 * Pozor: tento hook běží dřív, než se uloží metabox, takže hodnota přepínače
	 * tady ještě nemusí být aktuální. Definitivní kontrola je až v share().
	 *
	 * @param string $new_status Nový stav příspěvku.
	 * @param string $old_status Předchozí stav příspěvku.
	 * @param mixed  $post       Příspěvek, kterého se změna týká.
	 */
	public function maybe_schedule( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		// Heslem chráněný příspěvek se nesdílí — jeho obsah je záměrně
		// neveřejný, viz share().
		if ( '' !== $post->post_password ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( $post->ID ) ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, array( $post->ID ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( $post->ID ) );
	}

	/**
	 * Odešle příspěvek na Facebook.
	 *
	 * Podmínky se kontrolují znovu a celé — argument cron události pochází
	 * z pole naplánovaných úloh v options, ne z ověřeného requestu, a hook
	 * je veřejný název akce, který může spustit kdokoli. Příspěvek mezitím
	 * mohl změnit typ, stav i heslo.
	 *
	 * Odeslání chrání zámek (ShareState::claim()), aby dva souběžné běhy
	 * nezveřejnily tentýž příspěvek dvakrát.
	 *
	 * Pozor na požadavek, který na straně Facebooku uspěje, ale u nás skončí
	 * timeoutem: příspěvek na zdi vznikne, my ale dostaneme chybu a uložíme
	 * ji. Opakování (Task 6) proto nesmí opakovat slepě — u chyby spojení
	 * (kód 0) hrozí, že vytvoří duplicitní příspěvek.
	 *
	 * Pozor také na switch_to_blog(): SettingsRepository drží nastavení
	 * v paměti po celý proces a kontejner je singleton, takže volání share()
	 * uvnitř switch_to_blog() by vzalo Page ID i token původního webu.
	 * Dnes to nenastane (nikde v pluginu se pod switch_to_blog() nepublikuje
	 * příspěvek typu post ani akce), ale až taková cesta vznikne, je tohle
	 * místo, které se rozbije.
	 *
	 * @param int $post_id ID příspěvku k odeslání.
	 */
	public function share( $post_id = 0 ): void {
		$post = get_post( intval( $post_id ) );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->post_types(), true ) ) {
			return;
		}

		// Heslem chráněný příspěvek se nesdílí: obsah je záměrně neveřejný,
		// ale stav zůstává 'publish', takže by prošel dál a MessageComposer
		// by z něj složil perex ze syrového post_content.
		if ( '' !== $post->post_password ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( $post->ID ) ) {
			return;
		}

		if ( ! $this->state->should_share( $post->ID, $this->credentials->share_by_default() ) ) {
			return;
		}

		// Zámek se zabírá až po všech kontrolách — běh, který stejně nic
		// neodešle, nemá blokovat ten, který by odeslat mohl.
		if ( ! $this->state->claim( $post->ID ) ) {
			return;
		}

		try {
			// Kontrola se opakuje pod zámkem: mezi ní a zabráním zámku je pár
			// řádků, ve kterých mohl souběžný běh odeslání dokončit.
			if ( $this->state->is_shared( $post->ID ) ) {
				return;
			}

			$result = $this->client->publish(
				$this->credentials->page_id(),
				$this->credentials->token(),
				$this->composer->compose( $post ),
				$this->composer->link( $post )
			);

			if ( ! empty( $result['ok'] ) ) {
				$this->state->mark_shared( $post->ID, (string) $result['id'] );

				return;
			}

			$this->state->mark_error( $post->ID, (int) $result['code'], (string) $result['message'] );
		} finally {
			$this->state->release( $post->ID );
		}
	}

	/**
	 * Metabox s poli pro redaktora.
	 */
	private function register_metabox(): void {
		$this->wcf->create_metabox( array(
			'id'         => 'kct_facebook',
			'title'      => __( 'Facebook', 'kct' ),
			'post_types' => $this->post_types(),
			'context'    => 'side',
			'priority'   => 'default',
			'items'      => array(
				array(
					'type'  => 'toggle',
					'id'    => ShareState::META_SHARE,
					'label' => __( 'Sdílet na Facebook', 'kct' ),
				),
				array(
					'type'  => 'textarea',
					'id'    => ShareState::META_MESSAGE,
					'label' => __( 'Text příspěvku', 'kct' ),
					'desc'  => __( 'Necháte-li prázdné, použije se automaticky složený text.', 'kct' ),
				),
			),
		) );
	}

	/**
	 * Meta se neposílají do REST API a needituje je kdokoli.
	 */
	public function register_meta(): void {
		foreach ( $this->post_types() as $post_type ) {
			foreach ( $this->state_meta_keys() as $key => $type ) {
				register_post_meta( $post_type, $key, array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					// Právo se ptá na konkrétní příspěvek, ne na obecné
					// edit_posts — to má i redaktor, který na tenhle
					// příspěvek nesmí.
					'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
						return current_user_can( 'edit_post', $object_id );
					},
				) );
			}
		}
	}

	/**
	 * Označí stavové meta za chráněné.
	 *
	 * Typ „akce" má v supports 'custom-fields', takže bez tohoto filtru by
	 * stavové klíče byly vidět v boxu Vlastní pole a redaktor by mohl
	 * kct_fb_post_id přepsat nebo smazat — a tím rozbít ochranu proti
	 * opakovanému odeslání.
	 *
	 * @param bool   $protected Je meta chráněná podle dosavadního vyhodnocení?
	 * @param string $meta_key  Klíč meta.
	 *
	 * @return bool
	 */
	public function protect_meta( $protected, $meta_key ) {
		if ( isset( $this->state_meta_keys()[ $meta_key ] ) ) {
			return true;
		}

		return $protected;
	}

	/**
	 * Stavové meta klíče a jejich datové typy.
	 *
	 * @return array<string, string>
	 */
	private function state_meta_keys(): array {
		return array(
			ShareState::META_POST_ID  => 'string',
			ShareState::META_TIME     => 'integer',
			ShareState::META_ERROR    => 'array',
			ShareState::META_ATTEMPTS => 'integer',
		);
	}
}
```

> **Pozn. z kontroly kvality (Task 5 po dokončení):** Původní verze kódu
> neměla zámek (viz krok 1), v `share()` nekontrolovala typ příspěvku ani
> heslo, `auth_callback` se ptala na obecné `edit_posts` místo práva ke
> konkrétnímu příspěvku, stavové meta nebyly chráněné filtrem
> `is_protected_meta` (typ „akce" má v `supports` `custom-fields`, takže by
> je redaktor mohl ručně přepsat), `share()` neměla výchozí hodnotu
> parametru (událost bez argumentů by shodila `do_action` na
> `ArgumentCountError` a zabila i ostatní čekající události téhož běhu
> wp-cronu) a instance `ShareMetabox` se nikam neukládala. Druhé kolo
> kontroly našlo v zámku ještě dvě díry: převzetí vypršelého zámku
> nebylo atomické (mazání a nový zápis byly dva kroky) a `is_shared()`
> se neopakovalo pod zámkem, takže souběžný běh mohl mezi kontrolou
> a zabráním zámku odeslání dokončit a druhý běh by poslal duplikát.
> Kód výše je opravená verze, která do repozitáře skutečně šla.

- [x] **Krok 3: Zaregistruj feature**

Přepiš `src/Managers/FeaturesManager.php`:

```php
<?php

namespace Kct\Managers;

use Kct\Features\Events;
use Kct\Features\FacebookShare;
use Kct\Features\Roads;

final class FeaturesManager {
	public function __construct(
		Events $events,
		Roads $roads,
		FacebookShare $facebook_share
	) {
	}
}
```

- [x] **Krok 4: Ověř, že se plugin načte a kontejner feature sestaví**

```bash
ddev wp eval 'var_dump( get_class( kct_container()->get( Kct\Features\FacebookShare::class ) ) );'
```

Očekávané: `string(26) "Kct\Features\FacebookShare"` a žádná fatální chyba.

- [x] **Krok 5: Ověř metabox v editoru**

```bash
ddev launch /wp-admin/post-new.php
```

Očekávané: v editoru (v panelu kompatibilních metaboxů pod obsahem) je box
„Facebook" s přepínačem a textovým polem. Ulož koncept a ověř uložení hodnoty:

```bash
ddev wp post meta list <id> --keys=kct_fb_share,kct_fb_message
```

- [x] **Krok 6: Ověř naplánování cronu**

Zapni sdílení u konceptu, vyplň Page ID i token (můžou být i nefunkční) a publikuj.
Pak:

```bash
ddev wp cron event list --fields=hook,next_run_relative --format=table | grep kct_facebook_share
```

Očekávané: jedna naplánovaná událost `kct_facebook_share` zhruba za minutu.

- [x] **Krok 7: Ověř, že editace publikovaného článku nic nespustí**

```bash
ddev wp cron event run kct_facebook_share
```

(Bez funkčního tokenu skončí chybou — to je v tomto kroku v pořádku.) Pak článek
uprav a znovu ulož:

```bash
ddev wp post update <id> --post_title='Upravený titulek'
ddev wp cron event list --fields=hook --format=csv | grep kct_facebook_share
```

Očekávané: žádná nová naplánovaná událost.

- [x] **Krok 8: Ověř zámek proti souběhu**

Volání Graph API odchyť filtrem `pre_http_request` (v odchycené funkci uspi běh
na několik sekund, ať okno souběhu vůbec vznikne) a spusť dvakrát paralelně
`do_action( 'kct_facebook_share', <id> )` — dva procesy, které vyrazí ve stejný
okamžik. Očekávané: Graph API zavolá jen jeden z nich, druhý skončí bez odeslání
a po doběhnutí nezůstane v tabulce options žádný řádek `kct_fb_sending_*`.

Ověř i chování samotného zámku:

- platný zámek nesmí jít převzít, zámek starší než `LOCK_TTL` ano,
- převzetí vypršelého zámku spusť taky dvakrát paralelně — uspět smí právě jeden,
- `release()` běhu, kterému zámek mezitím převzal jiný, nesmí řádek smazat.

A ověř druhou kontrolu `is_shared()` pod zámkem: filtrem `get_post_metadata`
nasimuluj, že se příspěvek označí jako odeslaný až mezi první kontrolou
a zabráním zámku. Očekávané: běh se k volání Graph API vůbec nedostane.

- [x] **Krok 9: Ověř kontroly v `share()`**

Publikovanou aktualitu se zapnutým sdílením převeď na `page` a spusť
naplánovanou událost — nesmí se odeslat nic. Totéž u heslem chráněného
příspěvku: ten se nesmí ani naplánovat, ani odeslat.

---

## Task 6: Obsluha chyb, opakování a ověření připojení

**Files:**
- Modify: `src/Features/FacebookShare.php`
- Modify: `src/Facebook/ShareMetabox.php` (upozornění u chyby spojení)
- Modify: `src/Settings.php` (tlačítko „Ověřit připojení")
- Modify: `src/CLI.php` (úklid upozornění po úspěchu)

- [x] **Krok 1: Doplň opakování a hlášení chyb**

V `src/Features/FacebookShare.php` přidej konstanty pod `const DELAY = 60;`:

```php
	/** Odstupy opakovaných pokusů v sekundách: 5 min, 30 min, 2 h. */
	const RETRY_DELAYS = array( 300, 1800, 7200 );

	/** Option, do které se ukládá chyba tokenu pro upozornění v administraci. */
	const TOKEN_ERROR_OPTION = 'kct_fb_token_error';

	/** Akce nonce u tlačítka „Ověřit připojení" v nastavení. */
	const VERIFY_NONCE = 'kct-fb-verify';

	/** Předpona transientu s výsledkem ověření připojení. */
	const VERIFY_RESULT_PREFIX = 'kct_fb_verify_result_';

	/** Jak dlouho (v sekundách) výsledek ověření čeká na zobrazení. */
	const VERIFY_RESULT_TTL = 60;
```

Do konstruktoru přidej za `add_filter( 'is_protected_meta', ... )`:

```php
		add_action( 'admin_init', array( $this, 'handle_retry' ) );
		add_action( 'admin_init', array( $this, 'handle_verify' ) );
		add_action( 'admin_notices', array( $this, 'token_notice' ) );
		add_action( 'admin_notices', array( $this, 'verify_notice' ) );
```

Přidej `use Kct\Settings;` mezi importy — odkaz na `Settings::KEY` při skládání
adresy stránky nastavení.

V metodě `share()` uvnitř `try` doplň obě větve (kód je dnes pod zámkem
`ShareState::claim()`, viz Task 5 — `handle_failure()` patří dovnitř `try`,
aby `finally` zámek vždycky uvolnilo):

```php
			if ( ! empty( $result['ok'] ) ) {
				$this->state->mark_shared( $post->ID, (string) $result['id'] );

				// Odeslání prokázalo, že token funguje — upozornění z dřívějška
				// už neplatí a nemá dál strašit v administraci.
				delete_option( self::TOKEN_ERROR_OPTION );

				return;
			}

			$this->state->mark_error( $post->ID, (int) $result['code'], (string) $result['message'] );
			$this->handle_failure( $post->ID, (int) $result['code'], (string) $result['message'] );
```

V docbloku `share()` zároveň uprav poznámku o timeoutu — rozhodnutí, co s ním,
je teď v `handle_failure()`:

```php
	 * Pozor na požadavek, který na straně Facebooku uspěje, ale u nás skončí
	 * timeoutem: příspěvek na zdi vznikne, my ale dostaneme chybu a uložíme
	 * ji. Právě proto se chyba spojení (kód 0) automaticky neopakuje, viz
	 * handle_failure().
```

Pak přidej nové metody:

```php
	/**
	 * Rozhodne, co dál po neúspěšném odeslání.
	 *
	 * Buď naplánuje další pokus s rostoucím odstupem podle RETRY_DELAYS, nebo
	 * pokusy zastaví — u chyb, se kterými opakování nic nesvede.
	 *
	 * Neplatný token (kód 190) se neopakuje: dokud ho někdo nevymění, dopadne
	 * každý další pokus stejně. Místo opakování se uloží option, ze které
	 * token_notice() vypíše upozornění do administrace — netýká se totiž
	 * jednoho příspěvku, ale sdílení na celém webu.
	 *
	 * Neopakuje se ani chyba spojení (kód 0), a to záměrně: požadavek, který
	 * u nás skončil timeoutem, mohl na straně Facebooku uspět, takže by
	 * opakování přidalo na zeď druhý stejný příspěvek. WP_Error nerozliší
	 * timeout od nedostupné sítě (obojí je `http_request_failed`), takže se
	 * s oběma zachází stejně opatrně: automaticky nic, rozhodnutí zůstává na
	 * člověku. Ten si může zeď stránky prohlédnout a použít tlačítko „Zkusit
	 * znovu" v editoru. Metabox u kódu 0 na tuhle možnost upozorňuje.
	 *
	 * @param int    $post_id ID příspěvku.
	 * @param int    $code    Kód chyby vrácený GraphClient.
	 * @param string $message Text chyby vrácený GraphClient.
	 */
	private function handle_failure( int $post_id, int $code, string $message ): void {
		if ( GraphClient::ERROR_INVALID_TOKEN === $code ) {
			// Bez autoloadu — na frontendu tahle hodnota k ničemu není.
			update_option( self::TOKEN_ERROR_OPTION, $message, false );

			return;
		}

		if ( 0 === $code ) {
			return;
		}

		// mark_error() počítadlo právě zvýšilo, takže po prvním selhání je na
		// jedné a sahá se na index 0. Pokusů je tedy o jeden víc, než kolik má
		// RETRY_DELAYS položek: první odeslání plus tři opakování.
		$index = $this->state->attempts( $post_id ) - 1;

		if ( ! isset( self::RETRY_DELAYS[ $index ] ) ) {
			return;
		}

		wp_schedule_single_event(
			time() + self::RETRY_DELAYS[ $index ],
			self::CRON_HOOK,
			array( $post_id )
		);
	}

	/**
	 * Obsluha tlačítka „Zkusit znovu" z metaboxu v editoru.
	 *
	 * Odeslání se zkusí rovnou v tomto requestu, ne přes cron — redaktor, který
	 * na tlačítko klikl, má výsledek vidět hned po návratu do editoru.
	 */
	public function handle_retry(): void {
		if ( ! $this->is_action( 'fb_retry' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce se ověřuje hned pod tím, vůči tomuto ID.
		$post_id = isset( $_REQUEST['post'] ) ? intval( $_REQUEST['post'] ) : 0;

		if ( ! $post_id ) {
			return;
		}

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'kct-fb-retry-' . $post_id )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}

		// Nonce sám o sobě nestačí: platí pro přihlášeného uživatele, ale
		// neříká nic o tom, jestli na tenhle příspěvek smí.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'K odeslání tohoto příspěvku na Facebook nemáte oprávnění.', 'kct' ), '', array( 'response' => 403 ) );
		}

		// reset_attempts() maže jen počítadlo, ne uloženou chybu — share() níž
		// může na některé z podmínek tiše skončit a v editoru má zůstat vidět,
		// proč se předtím neodeslalo.
		$this->state->reset_attempts( $post_id );

		// Čekající událost je teď k ničemu: odeslání proběhne rovnou tady.
		// Nechat ji viset by navíc rozbilo odstupy opakování — WordPress tiše
		// zahodí událost naplánovanou do deseti minut od už existující
		// (wp-includes/cron.php), takže by se další pokus nenaplánoval.
		$scheduled = wp_next_scheduled( self::CRON_HOOK, array( $post_id ) );

		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK, array( $post_id ) );
		}

		$this->share( $post_id );

		$redirect = get_edit_post_link( $post_id, 'raw' );

		wp_safe_redirect( $redirect ? $redirect : admin_url() );
		exit;
	}

	/**
	 * Upozornění na neplatný token.
	 *
	 * Neplatný token zastaví sdílení všech příspěvků, ne jen toho jednoho, u
	 * kterého se to zrovna projevilo — patří proto do administrace, ne jen do
	 * metaboxu v editoru.
	 */
	public function token_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_option( self::TOKEN_ERROR_OPTION );

		if ( ! $message ) {
			return;
		}

		$text = sprintf(
			/* translators: %s: text chyby vrácený Facebookem. */
			__( 'Sdílení na Facebook nefunguje — token je neplatný nebo vypršel. Facebook hlásí: %s', 'kct' ),
			(string) $message
		);

		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $text ),
			esc_url( $this->settings_url() ),
			esc_html__( 'Otevřít nastavení', 'kct' )
		);
	}

	/**
	 * Jde o požadavek z daného tlačítka v administraci?
	 *
	 * `kct-action` je obyčejný parametr URL, který může poslat kdokoli — sám
	 * o sobě tedy k ničemu neopravňuje. Slouží jen k rozpoznání, o kterou akci
	 * jde; nonce i oprávnění se kontrolují až v obsluze.
	 *
	 * @param string $action Název očekávané akce.
	 */
	private function is_action( string $action ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání akce, nonce se ověřuje v obsluze.
		if ( ! isset( $_REQUEST['kct-action'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- viz výš.
		return $action === sanitize_key( wp_unslash( $_REQUEST['kct-action'] ) );
	}

	/**
	 * Adresa stránky s nastavením pluginu.
	 */
	private function settings_url(): string {
		return add_query_arg( array( 'page' => Settings::KEY ), admin_url( 'options-general.php' ) );
	}
```

- [x] **Krok 2: Upozorni v metaboxu, že chyba spojení se neopakuje**

Chyba s kódem 0 znamená, že se spojení nedokončilo — příspěvek na zdi přesto
mohl vzniknout, proto se automaticky neopakuje. Redaktor to musí vědět dřív, než
klikne na „Zkusit znovu". V `src/Facebook/ShareMetabox.php` do `render_error()`
za výpis chyby:

```php
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
```

- [x] **Krok 3: Ověř syntaxi**

`ddev` v tomhle prostředí nefunguje, jede se přes kontejner napřímo:

```bash
docker exec ddev-sokct-web php -l /var/www/html/wp-content/plugins/kct/src/Features/FacebookShare.php
```

Očekávané: `No syntax errors detected`

- [x] **Krok 4: Ověř opakování a chování u jednotlivých kódů chyb**

Facebook se nevolá doopravdy — odpovědi podstrčí filtr `pre_http_request`,
konfigurace přijde z konstant `KCT_FB_PAGE_ID` / `KCT_FB_PAGE_TOKEN`
definovaných až uvnitř skriptu (`Credentials` je čte při volání). Skript se pustí
přes `docker exec -u www-data ddev-sokct-web wp eval-file … --url=ricany.sokct.test`
a mezi pokusy se vždycky uklidí naplánovaná událost, aby simuloval, že proběhla.

Očekávané:

| pokus | uložená chyba | `kct_fb_attempts` | další naplánovaný pokus |
|-------|---------------|-------------------|-------------------------|
| 1     | kód 100       | 1                 | za 300 s                |
| 2     | kód 100       | 2                 | za 1800 s               |
| 3     | kód 100       | 3                 | za 7200 s               |
| 4     | kód 100       | 4                 | nic                     |

Dál:
- kód 190: `kct_fb_attempts` = 1, **žádná** naplánovaná událost, option
  `kct_fb_token_error` obsahuje text chyby a neautoloaduje se,
- kód 0 (timeout): uložená chyba, **žádná** naplánovaná událost,
- úspěch: `kct_fb_post_id` vyplněné, počítadlo i chyba pryč a option
  `kct_fb_token_error` smazaná.

- [x] **Krok 5: Ověř tlačítko „Zkusit znovu"**

Přes prohlížeč to nejde (nginx v kontejneru neběží), takže se `handle_retry()`
volá přímo — `$_REQUEST` se naplní ručně, `wp_die` i přesměrování se filtry
`wp_die_handler` a `wp_redirect` převedou na výjimku, aby skript pokračoval.

Očekávané:
- jiná hodnota v `kct-action` → obsluha neudělá nic,
- bez nonce i s neplatným nonce → `wp_die` 403 „Chyba v ověření zabezpečení.",
- předplatitel s vlastním platným nonce → `wp_die` 403 o chybějícím oprávnění,
  počítadlo pokusů se nezmění,
- administrátor s platným nonce → počítadlo se vynuluje, proběhne odeslání
  (v testu selže) a request skončí přesměrováním do editoru příspěvku.

- [x] **Krok 6: Přidej tlačítko „Ověřit připojení" do nastavení**

V `src/Settings.php` přidej `use Kct\Features\FacebookShare;` a do pole
`$settings` za položku `fb_default_image`:

```php
			array(
				'label' => __( 'Připojení k Facebooku', 'kct' ),
				'title' => __( 'Ověřit připojení', 'kct' ),
				'desc'  => __( 'Zkusí se připojit k Facebooku a vypíše název stránky, ke které token patří.', 'kct' ),
				'id'    => 'fb_verify',
				'type'  => 'button',
				'url'   => $this->fb_verify_url(),
			),
```

Pole typu `button` bere jako popisek tlačítka `title`, kdežto `label` je nadpis
řádku v tabulce nastavení — proto jsou obojí a liší se.

A metodu, která adresu skládá:

```php
	/**
	 * Adresa tlačítka „Ověřit připojení" i s jednorázovým tokenem.
	 *
	 * Nonce vzniká, jen když se zobrazuje samotná stránka nastavení: setup()
	 * běží v `plugins_loaded` při každém requestu včetně frontendu a
	 * wp_create_nonce() by tam předčasně vynutilo určení přihlášeného
	 * uživatele. Na ostatních obrazovkách se tlačítko stejně nevykresluje.
	 *
	 * Adresa se skládá funkcí add_query_arg(), ne wp_nonce_url() — ta výsledek
	 * prožene esc_html(), takže by se do URL dostalo `&amp;`. V HTML odkazu to
	 * je správně, ale tohle pole vykresluje React a atribut by nastavil doslova,
	 * takže by z nonce vznikl parametr `amp;_wpnonce`.
	 */
	private function fb_verify_url(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- jen rozpoznání obrazovky, nic se nemění.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! is_admin() || self::KEY !== $page ) {
			return '';
		}

		return add_query_arg(
			array(
				'kct-action' => 'fb_verify',
				'_wpnonce'   => wp_create_nonce( FacebookShare::VERIFY_NONCE ),
			),
			admin_url( 'index.php' )
		);
	}
```

V `src/Features/FacebookShare.php` přidej obsluhu a výpis výsledku:

```php
	/**
	 * Obsluha tlačítka „Ověřit připojení" v nastavení.
	 *
	 * Výsledek se ukládá do transientu vázaného na uživatele a vypíše ho
	 * verify_notice() po přesměrování zpět na stránku nastavení.
	 */
	public function handle_verify(): void {
		if ( ! $this->is_action( 'fb_verify' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'K ověření připojení k Facebooku nemáte oprávnění.', 'kct' ), '', array( 'response' => 403 ) );
		}

		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), self::VERIFY_NONCE )
		) {
			wp_die( esc_html__( 'Chyba v ověření zabezpečení.', 'kct' ), '', array( 'response' => 403 ) );
		}

		set_transient( $this->verify_result_key(), $this->verify_result(), self::VERIFY_RESULT_TTL );

		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Ověří připojení a složí větu, která se vypíše správci.
	 *
	 * Token se do výsledku nikdy nedostane — vypisuje se jen název stránky
	 * a hlášení, které vrátil Facebook.
	 */
	private function verify_result(): string {
		if ( ! $this->credentials->is_configured() ) {
			return __( 'Chybí ID stránky nebo token.', 'kct' );
		}

		$result = $this->client->verify( $this->credentials->token() );

		if ( empty( $result['ok'] ) ) {
			// Kód 0 znamená, že se Facebook nepodařilo vůbec zastihnout — od
			// chybové odpovědi API je to potřeba odlišit.
			if ( 0 === (int) $result['code'] ) {
				return sprintf(
					/* translators: %s: popis chyby spojení. */
					__( 'Nepodařilo se spojit s Facebookem: %s', 'kct' ),
					(string) $result['message']
				);
			}

			return sprintf(
				/* translators: 1: číselný kód chyby, 2: text chyby vrácený Facebookem. */
				__( 'Facebook vrátil chybu %1$d: %2$s', 'kct' ),
				(int) $result['code'],
				(string) $result['message']
			);
		}

		// Token prokazatelně funguje, upozornění z dřívějška už neplatí.
		delete_option( self::TOKEN_ERROR_OPTION );

		$message = sprintf(
			/* translators: %s: název připojené Facebook stránky. */
			__( 'Připojeno ke stránce „%s".', 'kct' ),
			(string) ( $result['name'] ?? '' )
		);

		$id = (string) ( $result['id'] ?? '' );

		// /me vrací identitu, ke které token patří — u uživatelského tokenu
		// nebo tokenu jiné stránky se liší od ID stránky v nastavení.
		if ( '' !== $id && $id !== $this->credentials->page_id() ) {
			$message .= ' ' . sprintf(
				/* translators: 1: ID vrácené Facebookem, 2: ID stránky uložené v nastavení. */
				__( 'Pozor: token patří k jinému účtu, než je nastavené ID stránky (Facebook vrátil %1$s, v nastavení je %2$s).', 'kct' ),
				$id,
				$this->credentials->page_id()
			);
		}

		return $message;
	}

	/**
	 * Výsledek ověření připojení po návratu z tlačítka v nastavení.
	 */
	public function verify_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_transient( $this->verify_result_key() );

		if ( ! $message ) {
			return;
		}

		delete_transient( $this->verify_result_key() );

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			esc_html( (string) $message )
		);
	}

	/**
	 * Klíč transientu s výsledkem ověření.
	 *
	 * Vázaný na uživatele — výsledek patří tomu, kdo na tlačítko klikl, a nemá
	 * vyskočit jinému správci, který má zrovna otevřenou administraci.
	 */
	private function verify_result_key(): string {
		return self::VERIFY_RESULT_PREFIX . get_current_user_id();
	}
```

- [x] **Krok 7: Ukliď upozornění i po příkazech WP-CLI**

Úspěšné `wp kct fb_check` i `wp kct fb_share` dokazují, že token funguje —
upozornění v administraci by po nich nemělo zůstat viset. V `src/CLI.php` přidej
`use Kct\Features\FacebookShare;` a do obou úspěšných větví:

```php
		delete_option( FacebookShare::TOKEN_ERROR_OPTION );
```

- [x] **Krok 8: Ověř tlačítko „Ověřit připojení"**

Prohlížeč nefunguje, takže se stránka nastavení vykreslí ve skriptu
bootstrapovaném přes `wp-load.php` mimo WP-CLI (knihovna wpify/custom-fields se
pod WP-CLI nezaregistruje). Před `require wp-load.php` je potřeba nastavit
`$_GET['page'] = 'kct_options'` a `define( 'WP_ADMIN', true )`, po něm
`require_once ABSPATH . 'wp-admin/includes/admin.php'`, `set_current_screen()`
a odpálit `admin_menu`, `admin_init` a hook stránky.

Očekávané: v HTML stránky je položka `fb_verify` typu `button` s adresou
`…/wp-admin/index.php?kct-action=fb_verify&_wpnonce=…` — s obyčejným `&`, ne
`&amp;`. Samotné `handle_verify()` pak (s podstrčenými odpověďmi přes
`pre_http_request`):
- předplatitel → `wp_die` 403 o chybějícím oprávnění,
- administrátor bez nonce → `wp_die` 403 „Chyba v ověření zabezpečení.",
- administrátor s nonce a platným tokenem → transient s názvem stránky, option
  `kct_fb_token_error` smazaná, přesměrování na stránku nastavení,
- token patřící jinému účtu → k hlášce se přidá varování o nesouhlasícím ID,
- neplatný token → „Facebook vrátil chybu 190: …".

`token_notice()` i `verify_notice()` vypíšou hlášku jen správci a token se do
HTML nedostane; `verify_notice()` transient po vypsání smaže, takže se hláška
neopakuje.

- [x] **Krok 9: Ukliď testovací data**

Testovací příspěvky, uživatele, meta, options (`kct_fb_token_error`, zámky
`kct_fb_sending_*`), transienty `kct_fb_verify_result_*` i naplánované události
`kct_facebook_share` smaž.

```bash
docker exec -u www-data ddev-sokct-web wp option list --url=ricany.sokct.test --search="*kct_fb*" --format=csv
docker exec -u www-data ddev-sokct-web wp cron event list --url=ricany.sokct.test --fields=hook --format=csv | grep kct_facebook_share
```

Očekávané: prázdný výpis.

---

## Task 7: Open Graph tagy

**Files:**
- Create: `src/Features/OpenGraph.php`
- Modify: `src/Managers/FeaturesManager.php`

- [x] **Krok 1: Vytvoř feature**

Soubor `src/Features/OpenGraph.php`:

```php
<?php

namespace Kct\Features;

use Kct\Facebook\Credentials;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

/**
 * Open Graph tagy pro slušný náhled odkazu na sociálních sítích.
 *
 * Web nemá SEO plugin; pokud ho někdy dostane, tato feature se vypne, aby tagy
 * nebyly v HTML dvakrát.
 */
class OpenGraph {
	/**
	 * @param Credentials $credentials Konfigurace sdílení, odsud se čte výchozí obrázek.
	 */
	public function __construct( private Credentials $credentials ) {
		add_action( 'wp_head', array( $this, 'render' ), 5 );
	}

	/**
	 * Vypíše Open Graph a Twitter Card tagy do hlavičky stránky.
	 *
	 * Priorita 5 na wp_head — má se vykreslit dřív než ostatní výstup v hlavičce.
	 */
	public function render(): void {
		if ( $this->has_seo_plugin() || is_404() ) {
			return;
		}

		// Archiv, který se navenek tváří jako platná stránka (WordPress ho
		// nepošle na 404), ale nemá k sobě žádný skutečný objekt — typicky
		// autor s neexistujícím slugem. Bez téhle pojistky by og:url spadl na
		// homepage a rozbitý odkaz by na Facebooku vypadal jako plnohodnotný.
		if ( ! is_singular() && $this->is_broken_archive() ) {
			return;
		}

		$post = is_singular() ? get_post() : null;

		$title       = $post instanceof WP_Post ? get_the_title( $post ) : $this->context_title();
		$description = $post instanceof WP_Post ? $this->description( $post ) : $this->context_description();
		$url         = $post instanceof WP_Post ? get_permalink( $post ) : $this->context_url();
		$image       = $this->image_url( $post );

		// Statická titulní stránka je pořád "webem", ne článkem, i když je
		// technicky singulární WP_Post.
		$type = ( $post instanceof WP_Post && ! is_front_page() ) ? 'article' : 'website';

		$tags = array(
			array( 'og:type', $type, 'attr' ),
			array( 'og:title', $title, 'attr' ),
			array( 'og:description', $description, 'attr' ),
			array( 'og:url', $url, 'url' ),
			array( 'og:site_name', get_bloginfo( 'name' ), 'attr' ),
			array( 'og:locale', get_locale(), 'attr' ),
		);

		foreach ( $tags as $tag ) {
			list( $property, $content, $escaper ) = $tag;

			// false se objeví typicky u get_permalink() nad neplatným
			// příspěvkem — bez toho testu by esc_url()/esc_attr() z něj
			// udělaly prázdný řetězec a tag by se vypsal prázdný místo toho,
			// aby se přeskočil.
			if ( '' === $content || false === $content ) {
				continue;
			}

			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				'url' === $escaper ? esc_url( $content ) : esc_attr( $content )
			);
		}

		if ( $image ) {
			printf(
				'<meta property="og:image" content="%s" />' . "\n",
				esc_url( $image[0] )
			);
			printf(
				'<meta property="og:image:width" content="%d" />' . "\n",
				intval( $image[1] )
			);
			printf(
				'<meta property="og:image:height" content="%d" />' . "\n",
				intval( $image[2] )
			);
		}

		printf(
			'<meta name="twitter:card" content="%s" />' . "\n",
			$image ? 'summary_large_image' : 'summary'
		);
	}

	/**
	 * Běží na webu aktivní SEO plugin, který si OG tagy řeší sám?
	 */
	private function has_seo_plugin(): bool {
		return class_exists( 'WPSEO_Options' ) || class_exists( 'RankMath' );
	}

	/**
	 * Archiv, u kterého get_queried_object() nevrací očekávaný objekt.
	 *
	 * WordPress u některých neplatných archivů (typicky autor s neexistujícím
	 * slugem) nepřesměruje na 404 a is_author() zůstane pravdivé, i když
	 * queried object chybí — get_the_archive_title() pak vrátí jen holý
	 * prefix ("Autor:") a v context_url() by nezbylo nic jiného než spadnout
	 * na homepage. Radši se pro takovou stránku nevypíše nic.
	 */
	private function is_broken_archive(): bool {
		if ( is_author() ) {
			return ! ( get_queried_object() instanceof WP_User );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return ! ( get_queried_object() instanceof WP_Term );
		}

		if ( is_post_type_archive() ) {
			return ! ( get_queried_object() instanceof WP_Post_Type );
		}

		return false;
	}

	/**
	 * Titulek pro stránky mimo singulární obsah — homepage, archivy, vyhledávání.
	 *
	 * get_the_archive_title() vrací text s prefixem typu "Kategorie: …", někdy
	 * i obalený HTML — proto se prohání přes wp_strip_all_tags().
	 */
	private function context_title(): string {
		if ( is_front_page() ) {
			return get_bloginfo( 'name' );
		}

		// Výpis příspěvků na vlastní adrese (nastavení "Zobrazovat na úvodní
		// stránce" → statická stránka + stránka příspěvků). Bez tohohle by
		// spadl na název webu jako kterákoli jiná nerozpoznaná stránka.
		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );

			return $page_id ? get_the_title( $page_id ) : get_bloginfo( 'name' );
		}

		if ( is_search() ) {
			return sprintf(
				/* translators: %s: hledaný výraz. */
				__( 'Výsledky hledání: %s', 'kct' ),
				get_search_query()
			);
		}

		if ( is_archive() ) {
			$title = trim( wp_strip_all_tags( get_the_archive_title() ) );

			if ( '' !== $title ) {
				return $title;
			}
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Popis pro stránky mimo singulární obsah — u archivů popis rubriky nebo
	 * jiné taxonomie, jinak popis webu.
	 */
	private function context_description(): string {
		if ( is_archive() ) {
			$description = trim( wp_strip_all_tags( get_the_archive_description() ) );

			if ( '' !== $description ) {
				return $description;
			}
		}

		return get_bloginfo( 'description' );
	}

	/**
	 * Adresa aktuální stránky mimo singulární obsah.
	 *
	 * Facebook bere og:url jako kanonickou adresu sdíleného odkazu — když by
	 * tag na archivu nebo ve výpisu pořád ukazoval na homepage, sdílení by se
	 * přiřadilo k homepage a náhled by ukázal něco jiného, než na co člověk
	 * klikl.
	 */
	private function context_url(): string {
		if ( is_front_page() ) {
			return $this->paginate( home_url( '/' ) );
		}

		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			$url     = $page_id ? get_permalink( $page_id ) : false;

			return $this->paginate( $url ? $url : home_url( '/' ) );
		}

		if ( is_search() ) {
			return $this->paginate( get_search_link() );
		}

		// Datový archiv nemá k sobě žádný objekt — get_queried_object() je
		// pro něj vždy null, protože žádný konkrétní objekt "rok/měsíc/den"
		// neexistuje. Adresa se proto skládá přímo z query vars.
		if ( is_date() ) {
			return $this->paginate( $this->date_archive_link() );
		}

		$queried = get_queried_object();

		if ( $queried instanceof WP_Term ) {
			$link = get_term_link( $queried );

			return $this->paginate( is_string( $link ) ? $link : home_url( '/' ) );
		}

		if ( $queried instanceof WP_Post_Type ) {
			$link = get_post_type_archive_link( $queried->name );

			return $this->paginate( $link ? $link : home_url( '/' ) );
		}

		if ( $queried instanceof WP_User ) {
			return $this->paginate( get_author_posts_url( $queried->ID ) );
		}

		return home_url( '/' );
	}

	/**
	 * Adresa datumového archivu (rok, měsíc, nebo den).
	 */
	private function date_archive_link(): string {
		if ( is_day() ) {
			return get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		}

		if ( is_month() ) {
			return get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		}

		if ( is_year() ) {
			return get_year_link( get_query_var( 'year' ) );
		}

		return home_url( '/' );
	}

	/**
	 * Adresa druhé a další stránky výpisu, je-li aktuální request stránkovaný.
	 *
	 * get_pagenum_link() si adresu skládá sám z aktuálního requestu, ne
	 * z předané $url — ta se použije jen na první, nestránkované stránce.
	 *
	 * @param string $url Adresa první stránky výpisu.
	 */
	private function paginate( string $url ): string {
		return is_paged() ? get_pagenum_link( get_query_var( 'paged' ) ) : $url;
	}

	/**
	 * Popis pro og:description — perex, jinak začátek obsahu, ořezaný na 200 znaků.
	 *
	 * Heslem chráněný příspěvek nesmí popis nabídnout: titulek WordPress
	 * zamaskuje ("Chráněno: …"), ale obsah by se jinak vypsal do veřejného
	 * HTML a Facebook scraper by si ho nacacheoval.
	 *
	 * @param WP_Post $post Příspěvek, ze kterého se popis skládá.
	 */
	private function description( WP_Post $post ): string {
		if ( '' !== $post->post_password ) {
			return get_bloginfo( 'description' );
		}

		$text = $post->post_excerpt ?: $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		return mb_strlen( $text ) > 200 ? rtrim( mb_substr( $text, 0, 200 ) ) . '…' : $text;
	}

	/**
	 * Náhledový obrázek: featured image příspěvku, jinak výchozí obrázek z nastavení.
	 *
	 * Velikost 'full': registrovaná velikost by WordPress prohnal přes
	 * image_constrain_size_for_editor() a omezil ji na $content_width tématu
	 * (640 px) — pod doporučenými 1200×630 pro Facebook, který si kartu
	 * skládá z deklarovaných rozměrů ještě před stažením obrázku.
	 *
	 * Bez SEO pluginu se default_image_id() čte z nastavení na každém
	 * requestu bez cache — transient s invalidací by se hodil, až provoz webu
	 * tenhle dotaz navíc skutečně zatíží.
	 *
	 * @param WP_Post|null $post Zobrazený příspěvek, nebo null mimo singulární stránku.
	 *
	 * @return array{0: string, 1: int, 2: int}|null
	 */
	private function image_url( ?WP_Post $post ): ?array {
		$attachment_id = $post instanceof WP_Post ? get_post_thumbnail_id( $post ) : 0;

		if ( ! $attachment_id ) {
			$attachment_id = $this->credentials->default_image_id();
		}

		if ( ! $attachment_id ) {
			return null;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		return $image ? array( $image[0], $image[1], $image[2] ) : null;
	}
}
```

- [x] **Krok 2: Zaregistruj feature**

V `src/Managers/FeaturesManager.php` přidej import `use Kct\Features\OpenGraph;`
a parametr `OpenGraph $open_graph` do konstruktoru.

- [x] **Krok 3: Ověř tagy na článku s obrázkem**

```bash
ddev wp post list --post_type=post --post_status=publish --field=url --posts_per_page=1
curl -s <url> | grep -E 'og:|twitter:'
```

Očekávané: `og:type` = article, `og:title` s titulkem článku, `og:description`,
`og:url`, `og:site_name`, `og:locale`, trojice `og:image*` a `twitter:card`.
`og:image:width`/`og:image:height` musí odpovídat skutečnému nahranému souboru
(velikost `full`), ne registrované velikosti `large` — tu WordPress kvůli
`$content_width` tématu zmenší pod doporučených 1200×630 pro Facebook.

- [x] **Krok 4: Ověř fallback obrázku**

Nastav v Nastavení → KČT výchozí náhledový obrázek, otevři článek **bez**
náhledového obrázku:

```bash
curl -s <url-clanku-bez-obrazku> | grep 'og:image'
```

Očekávané: `og:image` ukazuje na výchozí obrázek.

- [x] **Krok 5: Ověř homepage a archivy**

```bash
ddev exec curl -s http://localhost | grep -E 'og:type|og:title|og:url'
ddev exec curl -s <url-archivu-akce> | grep -E 'og:type|og:title|og:url'
```

Očekávané na homepage: `og:type` = website, `og:title` = název webu, `og:url` =
`home_url( '/' )`.

Očekávané na archivu (post type archiv, rubrika, štítek…): `og:type` = website,
`og:title` = titulek archivu (bez HTML a bez zdvojených prefixů), `og:url` =
skutečná adresa archivu, ne homepage — `og:url` je pro Facebook kanonická adresa
sdíleného odkazu, takže homepage na archivu by mu sdílení mylně přiřadila.

- [x] **Krok 6: Ověř zbylé okrajové případy**

```bash
curl -s <url-heslem-chraneneho-prispevku> | grep -E 'og:description'
curl -s <url-datumoveho-archivu> | grep -E 'og:type|og:title|og:url'
curl -s <url-neexistujici-stranky> | grep -E 'og:|twitter:'
curl -s <url-druhe-stranky-archivu-nebo-vypisu> | grep 'og:url'
```

Očekávané:
- heslem chráněný příspěvek: `og:description` buď chybí, nebo obsahuje jen
  popis webu — nikdy obsah příspěvku (titulek si WordPress zamaskuje sám,
  „Chráněno: …", popis ale ne).
- datumový archiv (rok/měsíc/den): `og:url` míří na adresu archivu
  (`/2024/03/` apod.), ne na homepage.
- neexistující stránka (404): žádné `og:`/`twitter:` tagy ve výstupu.
- druhá a další stránka archivu nebo výpisu: `og:url` obsahuje `/page/2/`
  (nebo obdobu), ne adresu první stránky.

Feature se navíc sama nevypíše na statické titulní stránce, kterou WordPress
technicky bere jako singulární `WP_Post` — tam je `og:type` = website, ne
article (výjimka pro `is_front_page()`), a na vlastní stránce příspěvků
(nastavení „Zobrazovat na úvodní stránce" → stránka + stránka příspěvků) je
`og:title`/`og:url` podle té stránky, ne podle homepage.

---

## Závěrečná kontrola celku: opravy před ostrým testem

Úkoly 1–7 byly odkontrolované po částech, závěrečná kontrola celku ale našla, že
se funkce rozpadá na spojích mezi nimi. Tenhle úkol je opravou těch nálezů;
proběhl před ostrým testem (Task 8), protože bez něj by sdílení na produkci
neodeslalo nic.

**Files:**
- Modify: `src/Features/FacebookShare.php`
- Modify: `src/Facebook/ShareState.php`
- Modify: `src/CLI.php`
- Modify: `src/Settings.php`
- Modify: `src/Plugin.php`
- Modify: spec `docs/superpowers/specs/2026-08-05-facebook-sharing-design.md`

- [x] **Krok 1: Sdílení bylo fakticky vypnuté (Z1)**

Položka toggle `kct_fb_share` v `FacebookShare::register_metabox()` neměla
`default`, takže knihovna wpify/custom-fields registrovala výchozí hodnotu `false`
a při každém uložení příspěvku ji zapsala do meta. `ShareState::should_share()`
spadne na globální nastavení jen tehdy, když meta **neexistuje** — po prvním
uložení tedy vracelo vždy `false` a nic by se neodeslalo. Oprava: položka má
`'default' => $this->credentials->share_by_default()`.

Ověřeno mimo WP-CLI (knihovna se pod WP-CLI neregistruje) simulací uložení
z editoru: se zapnutým globálním přepínačem `should_share()` vrací `true`,
s vypnutým `false`.

- [x] **Krok 2: WP-CLI `fb_share` obcházel zámek, obsluhu chyb i kontroly (Z2, Z3)**

Příkaz byl druhým orchestrátorem, který znal jen půlku pravidel: odesílal i pod
cizím zámkem (duplicitní příspěvek na zdi), po chybě 190 nenastavil
`kct_fb_token_error` a zvyšoval počítadlo pokusů bez naplánování opakování.
Oprava: mimo `--dry-run` volá `FacebookShare::share()`. Vlastní cestu má jen
`--force`, ale i ta zabírá zámek přes `ShareState::claim()`/`release()` a po chybě
volá `FacebookShare::handle_failure()` (metoda je proto nově veřejná, ne přes
reflexi). Před odesláním se ruší čekající cron událost, aby se nerozbily odstupy
opakování.

- [x] **Krok 3: Tlačítko „Zkusit znovu" umělo tiše neudělat nic (Z4)**

`share()` nic nevrací a na kterékoli podmínce tiše končí. `FacebookShare` proto
nově umí `snapshot()` + `outcome()`, které rozliší odesláno / selhalo (s důvodem
od Facebooku) / neodesláno kvůli podmínce a složí hlášku. Obsluha tlačítka ji
ukládá do transientu vázaného na uživatele (stejný vzor jako `verify_result`)
a `retry_notice()` ji vypíše v administraci. Stejné hlášky používá i WP-CLI, takže
obě cesty říkají totéž.

- [x] **Krok 4: Drobnosti (D1, D5, D6)**

- Filtr `is_protected_meta` pokrývá i `kct_fb_share` a `kct_fb_message` — u typu
  „akce" (má `custom-fields` v supports) byly vidět v boxu Vlastní pole. Metabox
  knihovny to nerozbilo: wpify zapisuje přes `update_post_meta()`, kterého se
  filtr netýká.
- `FacebookShare::settings_url()` zmizel, adresu nastavení dává `Settings::get_settings_url()`
  (nově statická, dosud neměla v pluginu jediné použití).
- `Plugin::deactivate()` ruší i události `kct_facebook_share`, maže option
  `kct_fb_token_error`, transienty s výsledky tlačítek a zbylé zámky
  `kct_fb_sending_*`. `Plugin::uninstall()` navíc maže stavová meta všech
  příspěvků a nastavení Facebooku z `kct_options`. Na multisite obojí projde weby
  sítě přes `switch_to_blog()`.

- [x] **Krok 5: Srovnání dokumentace (D7, D8)**

Spec dopsán tak, aby odpovídal repozitáři: `Credentials`, `ShareMetabox` a zámek
proti souběhu v §2 a §3, kontrola `kct_fb_share` až v `share()` v §4, stav odeslání
jako vlastní metabox v §6, velikost obrázku `full` a `og:locale` z `get_locale()`
v §7, úklid při deaktivaci a odinstalaci v §8, chování WP-CLI v §9. V plánu jsou
odškrtnuté kroky úkolů 1–7.

- [x] **Krok 6: Ověření na lokále (bez skutečného volání Facebooku)**

HTTP se mockuje přes `pre_http_request`. Ověřeno: publikace naplánuje událost,
cron odešle a uvolní zámek; pod cizím zámkem neodešle nic ani cron, ani CLI;
chyba 190 z CLI nastaví `kct_fb_token_error` a neopakuje se; běžná chyba plánuje
opakování s odstupy 5 min → 30 min → 2 h a po třetím se vzdá; tlačítko „Zkusit
znovu" vypíše hlášku pro všechny tři výsledky; `is_protected_meta()` platí pro
všech šest klíčů; po deaktivaci nezůstanou události, option, transienty ani zámky.

---

## Task 8: Ostrý test na veřejném webu

Tento úkol se dělá na produkci nebo veřejně dostupném stagingu — Facebook si musí
umět stáhnout sdílený odkaz. Předpokládá nasazený kód a vyplněné Page ID + token.

- [ ] **Krok 1: Ověř připojení**

```bash
wp kct fb_check
```

Očekávané: `Success: Připojeno ke stránce „…"`

- [ ] **Krok 2: Ověř OG tagy Facebook debuggerem**

Otevři https://developers.facebook.com/tools/debug/, vlož URL libovolného článku
a klikni na „Scrape Again".

Očekávané: náhled s obrázkem, titulkem a popisem, žádné varování o chybějícím
`og:image`.

- [ ] **Krok 3: Publikuj testovací aktualitu**

Vytvoř aktualitu s náhledovým obrázkem, zapnutým přepínačem „Sdílet na Facebook"
a publikuj ji. Počkej dvě minuty (nebo spusť `wp cron event run kct_facebook_share`).

Očekávané: příspěvek na FB stránce s obrázkovou kartou; v editoru box
„Facebook — stav odeslání" s časem a odkazem.

- [ ] **Krok 4: Publikuj krátkou aktualitu**

Nová aktualita se zapnutým přepínačem „Krátká aktualita".

Očekávané: příspěvek na FB obsahuje celý text a **žádný odkaz ani náhledovou kartu**.

- [ ] **Krok 5: Publikuj událost**

Nová akce s vyplněným datem, časem a místem startu.

Očekávané: příspěvek s řádky `Kdy:` a `Kde:` a s odkazem na detail akce.

- [ ] **Krok 6: Ověř, že úprava nespustí druhé sdílení**

Uprav a znovu ulož článek z kroku 3.

Očekávané: na FB nepřibude druhý příspěvek.

- [ ] **Krok 7: Ověř vypnutí sdílení**

Publikuj aktualitu s **vypnutým** přepínačem.

Očekávané: na FB nic nepřibude, v editoru se neobjeví box se stavem.

- [ ] **Krok 8: Ukliď testovací obsah**

Smaž testovací příspěvky na webu i na Facebook stránce.

---

## Poznámky k provozu

- **WP-Cron.** Na webu s malým provozem se naplánovaná událost spustí až při dalším
  requestu. Pokud by zpoždění vadilo, řešením je systémový cron
  (`define( 'DISABLE_WP_CRON', true );` + cron na `wp cron event run --due-now`).
- **Verze Graph API.** `GraphClient::API_VERSION` je potřeba jednou za čas zvednout.
  Příznak, že je čas: `wp kct fb_check` začne vracet chybu o nepodporované verzi.
- **Rozjetí dalšího webu v síti.** Vyplnit Page ID a token v jeho nastavení. Pokud
  bude mít vlastní Meta aplikaci a vlastního správce, čeká tě App Review — viz
  sekce 2 specu.
