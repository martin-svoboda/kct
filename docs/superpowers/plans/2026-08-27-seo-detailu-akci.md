# SEO detailů akcí — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-27-seo-detailu-akci-design.md`](../specs/2026-08-27-seo-detailu-akci-design.md)

**Cíl:** Detaily akcí `/akce-db/{id}` dostanou vlastní titulek, popisek, kanonickou
adresu, Open Graph tagy, `schema.org/Event` a místo v XML sitemapě, aby je vyhledávače
přestaly zahazovat jako duplicitu výpisu blogu.

**Architektura:** Nová feature `EventSeo` registrovaná ve `FeaturesManager`, kterou
kontejner PHP-DI sestaví sám. Logika je v namespace `Kct\Seo` rozdělená na třídy bez
WP hooků (`EventSeoData` — skládání hodnot, `CanonicalSites` — mapa odborových webů)
a dvě implementace výstupu za jedním rozhraním (`RankMathOutput`, `StandaloneOutput`),
protože ne každý web v síti má Rank Math.

**Tech stack:** PHP 8.0+, WordPress multisite, PHP-DI (`config.php`), Rank Math filtry,
WP-CLI, wpify/model.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám. Každý úkol proto končí
ověřením, ne commitem.

**V projektu není PHPUnit ani jiná testovací infrastruktura.** Ověřování stojí na
`php -l`, `ddev wp eval` a `curl`. Nezaváděj kvůli tomuto plánu testovací framework —
to je samostatné rozhodnutí.

**Příkazy:** lokálně přes ddev z kořene projektu (`/Users/martin/Sites/sokct`). Ddev
v tomto projektu funguje, ověřeno:

```bash
ddev wp --url=https://sokct.test option get blogname
```

Kdyby ddev selhal na chybějícím `mkcert` (hlásí „Project is not currently running“,
přestože kontejnery běží), použij místo toho:

```bash
docker exec -u www-data ddev-sokct-web wp --url=https://sokct.test option get blogname
```

**Vždy uváděj `--url`.** Je to multisite; bez `--url` skončí WP-CLI varováním
`Undefined array key "HTTP_HOST"` a sáhne na špatný web.

**Coding standards:** WordPress Coding Standards, tabulátory, mezery uvnitř závorek
(`if ( ! empty( $var ) )`), pole přes `array()`. Řiď se okolním kódem v `src/`.

**Nesahej na import ani na administraci.** DB akce zůstávají jen v tabulce
`wp_db_events`. Převod akce na CPT `akce` zůstává ruční přes stávající tlačítko.

**Předpoklad pro Task 3: opravená konstanta `NOBLOGREDIRECT`.** Ve `wp-config.php`
je `define( 'NOBLOGREDIRECT', 'sokct.test' )` bez schématu. WordPress ji na hlavním
webu pošle do hlavičky `Location` (`wp-includes/ms-functions.php:2208`), prohlížeč
ji vyhodnotí jako relativní cestu a vznikne nekonečná smyčka přesměrování — dnes
to potkává každou neexistující adresu. Task 3 zavádí 404 pro neznámé akce, takže by
bez opravy skončilo ve stejné smyčce.

Ověř před začátkem Tasku 3:

```bash
curl -sk -o /dev/null -w "%{http_code} → %{redirect_url}\n" https://sokct.test/neexistujici-test/
```

Očekávané: `404 → `. Když přijde `302 → sokct.test`, konstanta ještě opravená není —
buď ji smaž, nebo doplň schéma (`'https://sokct.cz'`). **Zásah do `wp-config.php`
patří Martinovi**, tady si ho jen vyžádej a počkej.

**Weby v síti a jejich filtr akcí** (`kct_options.id_code`):

| web | id_code | typ |
|---|---|---|
| `sokct.test` | `102` | region — oblastní web, záložní kanonický cíl |
| `kctricany.test` | `102100` | odbor |
| `kctpodebrady.sokct.test` | `102093` | odbor, **nemá Rank Math** |
| `kctrakovnik.sokct.test` | `102131` | odbor |
| `kctzdice.sokct.test` | `102033` | odbor |
| `kctvltavin.test` | `102126` | odbor |

---

## Mapa souborů

| soubor | odpovědnost |
|---|---|
| `src/Seo/EventSeoData.php` | **nový** — z pole akce poskládá titulek, popisek, obrázek a `Event` graf; žádné WP hooky |
| `src/Seo/CanonicalSites.php` | **nový** — mapa `department → home_url`, kanonická adresa akce, párování s CPT |
| `src/Seo/EventSeoOutput.php` | **nový** — rozhraní pro výstup |
| `src/Seo/RankMathOutput.php` | **nový** — registruje filtry Rank Mathu |
| `src/Seo/StandaloneOutput.php` | **nový** — vypisuje tagy do `wp_head` na webech bez SEO pluginu |
| `src/Seo/EventSitemapProvider.php` | **nový** — provider `akce-db-sitemap.xml` pro Rank Math |
| `src/Features/EventSeo.php` | **nový** — rozpozná kontext, propojí data s výstupem, řeší 404 a přesměrování spárovaných akcí |
| `src/Repositories/EventRepository.php` | úprava — `find_by_db_id()` |
| `src/Features/Events.php` | úprava — `get_event()` nesmí padat na neexistující `db_id` |
| `src/Managers/FeaturesManager.php` | úprava — registrace feature |
| `themes/kct/template-parts/content-akce.php` | úprava — poznámka u proběhlých akcí |
| `assets/styles/core/components/eventpost.scss` | úprava — styl té poznámky |

Oproti specu přibyly `CanonicalSites.php` (spec kanonizaci popisuje, ale nedává jí
vlastní třídu) a `EventSitemapProvider.php`. Chování odpovídá specu.

**Odchylka od specu, kapitola 3:** spec říká „odebrat `CollectionPage`, přidat
`Event`“. `CollectionPage` ale není samostatná entita — je to `@type` entity
`WebPage`, kterou Rank Math nastaví, protože `is_home()` je na virtuální stránce
pravdivé (`includes/modules/schema/snippets/class-webpage.php:74`). Místo odebírání
se proto entita **přetypuje** na `WebPage` a opraví se jí `name` a `url`. Záměr je
stejný, provedení přesnější.

**Datové poměry, ze kterých plán vychází** (naměřeno na 318 řádcích `wp_db_events`):

| pole | vyplněno |
|---|---|
| `details` (typ akce) | 318 |
| `details[].km` | 277 |
| `place` | 313 |
| `content` | 97, delší než 80 znaků jen 44 |
| `image` | **6** — záložní obrázek je hlavní cesta, ne výjimka |
| `proposal` | 92, ale je to odkaz na PDF, ne text |

---

## Task 1: `EventSeoData` — skládání titulku a popisku

**Soubory:**
- Vytvoř: `wp-content/plugins/kct/src/Seo/EventSeoData.php`

- [x] **Krok 1: Vytvoř třídu**

Vytvoř `src/Seo/EventSeoData.php`:

```php
<?php

namespace Kct\Seo;

use DateTimeImmutable;

/**
 * Skládá SEO hodnoty z pole akce.
 *
 * Třída záměrně nezná WordPress hooky ani Rank Math — dostane pole, jaké vrací
 * Events::get_event(), a vrátí řetězce. Díky tomu se dá ověřit přes `wp eval`
 * bez renderování stránky.
 */
class EventSeoData {

	/** Popisek se nesmí protáhnout přes délku, kterou vyhledávače zobrazí. */
	const DESCRIPTION_LIMIT = 155;

	/**
	 * Titulek stránky bez názvu webu — ten doplní Rank Math ze šablony.
	 *
	 * Formát: "46. Krajem nezbedného bakaláře — Rakovník, 5. 9. 2026"
	 */
	public function title( array $event ): string {
		$title = $this->text( $event['title'] ?? '' );

		if ( '' === $title ) {
			return '';
		}

		$year = (int) ( $event['year'] ?? 0 );
		if ( $year > 0 ) {
			$title = $year . '. ' . $title;
		}

		// Krátký název obce, ne podrobné místo srazu — to je v start.place
		// a bývá dlouhé přes sto znaků.
		$parts = array_filter( array(
			trim( (string) ( $event['place'] ?? '' ) ),
			$this->format_date( $event['date'] ?? '' ),
		) );

		if ( $parts ) {
			$title .= ' — ' . implode( ', ', $parts );
		}

		return $title;
	}

	/**
	 * Popisek složený z dat akce.
	 *
	 * Volný text (`content`) má jen 97 z 318 akcí a použitelnou délku 44, takže
	 * popisek stojí na datech a text ho jen doplní, když po nich zbude místo.
	 */
	public function description( array $event ): string {
		$sentences = array_filter( array(
			$this->discipline_sentence( $event ),
			$this->when_where_sentence( $event ),
			$this->organiser_sentence( $event ),
		) );

		$description = implode( ' ', $sentences );

		$content = trim( wp_strip_all_tags( (string) ( $event['content'] ?? '' ) ) );
		if ( $content ) {
			$remaining = self::DESCRIPTION_LIMIT - mb_strlen( $description ) - 1;

			// Kratší útržek než třicet znaků je v ukázce k ničemu a jen useká
			// větu uprostřed slova.
			if ( $remaining >= 30 ) {
				$description = trim( $description . ' ' . $this->shorten( $content, $remaining ) );
			}
		}

		return $this->shorten( $description, self::DESCRIPTION_LIMIT );
	}

	/**
	 * Disciplína a délky tras: "Pěší turistika 10–40 km."
	 *
	 * `details` míchá disciplíny s bonusy (sleva, turistické známky). Řadí je
	 * `weight` — 30 pěší, 50 cyklo, 136 IVV, 180 sleva KČT — takže disciplína
	 * je položka s nejnižší vahou a bonusy do popisku nepatří.
	 */
	private function discipline_sentence( array $event ): string {
		$details = $this->details( $event );

		if ( ! $details ) {
			return '';
		}

		usort( $details, static function ( $a, $b ) {
			return (int) ( $a['weight'] ?? 0 ) <=> (int) ( $b['weight'] ?? 0 );
		} );

		$name = trim( (string) ( $details[0]['name'] ?? '' ) );
		if ( '' === $name ) {
			return '';
		}

		$km = '';
		foreach ( $details as $detail ) {
			// `km` je v datech buď řetězec ("10–40 km"), nebo prázdné pole.
			if ( is_string( $detail['km'] ?? null ) && '' !== trim( $detail['km'] ) ) {
				$km = trim( $detail['km'] );
				break;
			}
		}

		return rtrim( $name . ( $km ? ' ' . $km : '' ), '.' ) . '.';
	}

	/** Termín a místo: "5. 9. 2026, Rakovník, start 6:00–12:00." */
	private function when_where_sentence( array $event ): string {
		$parts = array_filter( array(
			$this->format_date( $event['date'] ?? '' ),
			trim( (string) ( $event['place'] ?? '' ) ),
		) );

		if ( ! $parts ) {
			return '';
		}

		$time = trim( (string) ( $event['start']['time'] ?? '' ) );
		if ( $time ) {
			$parts[] = 'start ' . $time;
		}

		return implode( ', ', $parts ) . '.';
	}

	/** Pořadatel: "Pořádá KČT, odbor Rakovník." */
	private function organiser_sentence( array $event ): string {
		$name = trim( (string) ( $event['organiser']['name'] ?? '' ) );

		return $name ? 'Pořádá ' . rtrim( $name, '.' ) . '.' : '';
	}

	/** Normalizuje `details` na seznam polí — v datech bývá i jediný záznam bez obalu. */
	private function details( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) || ! $details ) {
			return array();
		}

		if ( isset( $details['detailid'] ) ) {
			return array( $details );
		}

		return array_values( array_filter( $details, 'is_array' ) );
	}

	/** "2026-09-05" → "5. 9. 2026"; prázdné nebo nečitelné datum vrátí prázdný řetězec. */
	private function format_date( $date ): string {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date );

		return $timestamp ? date_i18n( 'j. n. Y', $timestamp ) : '';
	}

	/** Zkrátí na hranici slova a doplní výpustku. */
	private function shorten( string $text, int $limit ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $limit - 1 );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > $limit / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, ' ,.;:–-' ) . '…';
	}
}
```

- [x] **Krok 2: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/EventSeoData.php
```

Očekávaný výstup: `No syntax errors detected`

- [x] **Krok 3: Ověř titulek a popisek na reálné akci**

`db_id 23954` je „Krajem nezbedného bakaláře“, 46. ročník, Rakovník, 5. 9. 2026,
pěší turistika 10–40 km, pořádá KČT odbor Rakovník.

```bash
ddev wp --url=https://sokct.test eval '
$events = kct_container()->get( Kct\Features\Events::class );
$data   = new Kct\Seo\EventSeoData();
$event  = $events->get_event( 0, 23954 );
echo "TITULEK: " . $data->title( $event ) . "\n";
$d = $data->description( $event );
echo "POPISEK (" . mb_strlen( $d ) . "): " . $d . "\n";
'
```

Očekávaný výstup:

```
TITULEK: 46. Krajem nezbedného bakaláře — Rakovník, 5. 9. 2026
POPISEK (91): Pěší turistika 10–40 km. 5. 9. 2026, Rakovník, start 6:00–12:00. Pořádá KČT, odbor Rakovník.
```

Titulek musí být bez sufixu s názvem webu a popisek nesmí přesáhnout 155 znaků.

- [x] **Krok 4: Ověř akci s volným textem**

`db_id 21542` má `content` „Individuální novoroční vycházka na sopečný vrch Dědek…“.

```bash
ddev wp --url=https://sokct.test eval '
$events = kct_container()->get( Kct\Features\Events::class );
$data   = new Kct\Seo\EventSeoData();
$d      = $data->description( $events->get_event( 0, 21542 ) );
echo mb_strlen( $d ) . ": " . $d . "\n";
'
```

Očekávané: popisek začíná disciplínou a termínem, na konci je kus volného textu
zakončený výpustkou, celkem nejvýš 155 znaků.

- [x] **Krok 5: Ověř akci bez místa**

Najdi akci s prázdným `place` a zkontroluj, že popisek nemá osamocenou čárku
(nesmí vzniknout „5. 9. 2026, , start…“):

```bash
ddev wp --url=https://sokct.test eval '
global $wpdb;
$id = $wpdb->get_var( "SELECT db_id FROM {$wpdb->prefix}db_events WHERE place = \"\" LIMIT 1" );
if ( ! $id ) { echo "Všechny akce mají place — krok přeskoč.\n"; exit; }
$events = kct_container()->get( Kct\Features\Events::class );
$data   = new Kct\Seo\EventSeoData();
echo $id . " → " . $data->description( $events->get_event( 0, (int) $id ) ) . "\n";
'
```

- [x] **Krok 6: Ověř, že se nic nerozbilo na všech akcích**

Projede všech 318 akcí a vypíše ty, kde by titulek nebo popisek zůstal prázdný
nebo přetekl limit:

```bash
ddev wp --url=https://sokct.test eval '
global $wpdb;
$events = kct_container()->get( Kct\Features\Events::class );
$data   = new Kct\Seo\EventSeoData();
$ids    = $wpdb->get_col( "SELECT db_id FROM {$wpdb->prefix}db_events" );
$bad    = 0;
foreach ( $ids as $id ) {
	$e = $events->get_event( 0, (int) $id );
	$t = $data->title( $e );
	$d = $data->description( $e );
	if ( "" === $t || "" === $d || mb_strlen( $d ) > 155 ) {
		echo "PROBLÉM {$id}: [{$t}] [" . mb_strlen( $d ) . "] {$d}\n";
		$bad++;
	}
}
echo "Zkontrolováno " . count( $ids ) . ", problémů: {$bad}\n";
'
```

Očekávané: `problémů: 0`.

---

## Task 2: `find_by_db_id()` a `CanonicalSites`

**Soubory:**
- Uprav: `wp-content/plugins/kct/src/Repositories/EventRepository.php`
- Vytvoř: `wp-content/plugins/kct/src/Seo/CanonicalSites.php`

- [x] **Krok 1: Přidej vyhledání CPT akce podle `db_id`**

Do `src/Repositories/EventRepository.php` přidej za `find_all_published_by_date()`:

```php
	/**
	 * Najde publikovaný CPT příspěvek spárovaný s akcí z databáze KČT.
	 *
	 * Používá se pro rozpoznání duplicity: když akce má vlastní příspěvek,
	 * nemá virtuální stránka /akce-db/{id} existovat jako druhá adresa téhož
	 * obsahu.
	 *
	 * @param int $db_id ID akce v databázi KČT.
	 *
	 * @return int ID příspěvku, nebo 0 když spárovaný není.
	 */
	public function find_by_db_id( int $db_id ): int {
		if ( $db_id <= 0 ) {
			return 0;
		}

		$posts = get_posts( array(
			'post_type'              => $this->post_type(),
			'post_status'            => 'publish',
			'numberposts'            => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_key'               => 'db_id',
			'meta_value'             => $db_id,
		) );

		return $posts ? (int) $posts[0] : 0;
	}
```

- [x] **Krok 2: Ověř párování**

`db_id 24065` je spárované s příspěvkem 2044, `db_id 23954` spárované není.

```bash
ddev wp --url=https://sokct.test eval '
$repo = kct_container()->get( Kct\Repositories\EventRepository::class );
echo "24065 → " . $repo->find_by_db_id( 24065 ) . "\n";
echo "23954 → " . $repo->find_by_db_id( 23954 ) . "\n";
'
```

Očekávaný výstup:

```
24065 → 2044
23954 → 0
```

- [x] **Krok 3: Vytvoř `CanonicalSites`**

Vytvoř `src/Seo/CanonicalSites.php`:

```php
<?php

namespace Kct\Seo;

use Kct\Repositories\DbEventRepository;
use Kct\Settings;

/**
 * Určuje, který web v síti je kanonický pro danou akci.
 *
 * Detail akce renderují všechny weby v síti (DbEventRepository čte sdílenou
 * tabulku přes switch_to_blog), takže bez tohoto pravidla by po zindexování
 * vznikla stejná stránka na pěti doménách. Kanonický je web pořádajícího
 * odboru, pokud nějaký v síti existuje; jinak oblastní web.
 */
class CanonicalSites {

	const TRANSIENT = 'kct_canonical_sites';
	const TTL       = 12 * HOUR_IN_SECONDS;

	public function __construct( private DbEventRepository $db_event_repository ) {
	}

	/**
	 * Kanonická adresa detailu akce.
	 *
	 * @param array $event Pole akce z Events::get_event().
	 */
	public function url_for( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		if ( $db_id <= 0 ) {
			return '';
		}

		return trailingslashit( $this->home_url_for( $event ) ) . 'akce-db/' . $db_id . '/';
	}

	/** Je aktuální web kanonický pro tuto akci? Podle toho se plní sitemapa. */
	public function is_canonical_here( array $event ): bool {
		return untrailingslashit( $this->home_url_for( $event ) )
			=== untrailingslashit( home_url() );
	}

	/** Domovská adresa webu, který je pro akci kanonický. */
	private function home_url_for( array $event ): string {
		$department = $this->department_of( $event );
		$map        = $this->map();

		if ( $department && isset( $map['departments'][ $department ] ) ) {
			return $map['departments'][ $department ];
		}

		return $map['region'] ?: home_url();
	}

	/**
	 * Kód pořádajícího odboru.
	 *
	 * U CPT akcí `department` v poli chybí — Events::get_event() prochází jen
	 * klíče příspěvku a EventModel tuhle vlastnost zakomentovanou nemá, takže
	 * se dotáhne z databázové akce podle db_id.
	 */
	private function department_of( array $event ): string {
		$department = (int) ( $event['department'] ?? 0 );

		if ( ! $department && ! empty( $event['db_id'] ) ) {
			$db_event = $this->db_event_repository->get_by_db_id( (int) $event['db_id'] );

			if ( $db_event ) {
				$department = (int) $db_event->department;
			}
		}

		return $department ? (string) $department : '';
	}

	/**
	 * Mapa `department → home_url` a adresa oblastního webu.
	 *
	 * get_sites() s přepínáním blogů je pro každý požadavek zbytečně drahé,
	 * proto síťový transient. Zastarání nezpůsobí škodu, jen zpoždění.
	 */
	public function map(): array {
		$cached = get_site_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$map = array(
			'departments' => array(),
			'region'      => '',
		);

		foreach ( get_sites( array( 'number' => 200 ) ) as $site ) {
			switch_to_blog( (int) $site->blog_id );

			$options = get_option( Settings::KEY, array() );
			$code    = isset( $options['id_code'] ) ? trim( (string) $options['id_code'] ) : '';
			$url     = home_url();

			restore_current_blog();

			if ( '' === $code ) {
				continue;
			}

			// Délka kódu rozlišuje oblast od odboru — stejně jako
			// SettingsRepository::code_type().
			if ( 6 === strlen( $code ) ) {
				$map['departments'][ $code ] = $url;
			} elseif ( 3 === strlen( $code ) && ! $map['region'] ) {
				$map['region'] = $url;
			}
		}

		set_site_transient( self::TRANSIENT, $map, self::TTL );

		return $map;
	}

	/** Zahodí mapu — volá se při změně nastavení nebo složení sítě. */
	public function flush(): void {
		delete_site_transient( self::TRANSIENT );
	}
}
```

- [x] **Krok 4: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/CanonicalSites.php
php -l wp-content/plugins/kct/src/Repositories/EventRepository.php
```

Očekávaný výstup: dvakrát `No syntax errors detected`

- [x] **Krok 5: Ověř mapu webů**

```bash
ddev wp --url=https://sokct.test eval '
$sites = kct_container()->get( Kct\Seo\CanonicalSites::class );
$sites->flush();
print_r( $sites->map() );
'
```

Očekávané: `region` je `https://sokct.test`, v `departments` jsou klíče
`102100`, `102093`, `102131` s adresami odborových webů.

- [x] **Krok 6: Ověř kanonické adresy**

`db_id 23954` patří odboru 102131 (kctrakovnik), `db_id 23981` odboru 102073
(vlastní web nemá).

```bash
ddev wp --url=https://sokct.test eval '
$events = kct_container()->get( Kct\Features\Events::class );
$sites  = kct_container()->get( Kct\Seo\CanonicalSites::class );
foreach ( array( 23954, 23981 ) as $id ) {
	$e = $events->get_event( 0, $id );
	echo $id . " → " . $sites->url_for( $e )
		. " | tady kanonicky: " . var_export( $sites->is_canonical_here( $e ), true ) . "\n";
}
'
```

Očekávaný výstup:

```
23954 → https://kctrakovnik.sokct.test/akce-db/23954/ | tady kanonicky: false
23981 → https://sokct.test/akce-db/23981/ | tady kanonicky: true
```

- [x] **Krok 7: Ověř, že totéž platí i z odborového webu**

```bash
ddev wp --url=https://kctricany.test eval '
$events = kct_container()->get( Kct\Features\Events::class );
$sites  = kct_container()->get( Kct\Seo\CanonicalSites::class );
echo $sites->url_for( $events->get_event( 0, 23954 ) ) . "\n";
'
```

Očekávané: `https://kctrakovnik.sokct.test/akce-db/23954/` — kanonická adresa
nezávisí na tom, kde se stránka zobrazuje.

---

## Task 3: Feature `EventSeo`, 404 pro neznámé akce a přesměrování spárovaných

Tři věci najednou, protože všechny visí na `template_redirect` a na jednom volání
`get_event()`:

1. **Neznámé `db_id` dnes vrací HTTP 500.** `Events::get_event()` volá `to_array()`
   na `null`, když akce v tabulce není — ověřeno na `/akce-db/999999/`. Až se adresy
   zaindexují a import smaže zrušenou akci (feed ji značí `deleted=Y`), bude Google
   místo 404 dostávat serverovou chybu a mrtvou adresu si v indexu podrží.
2. **CPT akce spárovaná s `db_id` má dvě adresy** — `/akce/3-den-s-klubem…/`
   i `/akce-db/24065/`. Takových dvojic je deset a po zaindexování je z toho duplicita.
3. Samotná feature, která propojí data s výstupem.

**Soubory:**
- Uprav: `wp-content/plugins/kct/src/Features/Events.php`
- Vytvoř: `wp-content/plugins/kct/src/Seo/EventSeoOutput.php`
- Vytvoř: `wp-content/plugins/kct/src/Features/EventSeo.php`
- Uprav: `wp-content/plugins/kct/src/Managers/FeaturesManager.php`

- [x] **Krok 0: Oprav pád na neexistující akci**

V `src/Features/Events.php`, metoda `get_event()`, nahraď blok načtení databázové
akce (kolem řádku 461):

```php
		// Získání dat z databázové akce pokud jsou
		if ( $bd_id ) {
			$event_db      = $this->db_event_repository->get_by_db_id( (int) $bd_id );
			$event_db_data = $event_db->to_array();
		}
```

za:

```php
		// Získání dat z databázové akce pokud jsou
		if ( $bd_id ) {
			$event_db = $this->db_event_repository->get_by_db_id( (int) $bd_id );

			// Akce v tabulce být nemusí — buď je db_id z URL vymyšlené, nebo ji
			// import smazal, protože ji feed označil jako zrušenou. Bez téhle
			// pojistky spadne to_array() na null a stránka vrátí 500.
			if ( $event_db ) {
				$event_db_data = $event_db->to_array();
			}
		}
```

Ověř, že už nepadá:

```bash
php -l wp-content/plugins/kct/src/Features/Events.php
ddev wp --url=https://sokct.test eval '
$events = kct_container()->get( Kct\Features\Events::class );
var_dump( $events->get_event( 0, 999999 ) );
'
```

Očekávané: `array(0) {}` místo fatální chyby.

- [x] **Krok 1: Vytvoř rozhraní výstupu**

Vytvoř `src/Seo/EventSeoOutput.php`:

```php
<?php

namespace Kct\Seo;

/**
 * Výstup SEO hodnot do stránky.
 *
 * Dvě implementace: přes filtry Rank Mathu tam, kde je nainstalovaný,
 * a vlastním výpisem do wp_head tam, kde není (kctpodebrady).
 */
interface EventSeoOutput {

	/**
	 * @param array  $event     Pole akce z Events::get_event().
	 * @param string $canonical Kanonická adresa akce.
	 * @param bool   $is_single Jde o CPT příspěvek (true), nebo virtuální stránku (false)?
	 */
	public function render( array $event, string $canonical, bool $is_single ): void;
}
```

- [x] **Krok 2: Vytvoř feature**

Vytvoř `src/Features/EventSeo.php`:

```php
<?php

namespace Kct\Features;

use Kct\Repositories\EventRepository;
use Kct\Seo\CanonicalSites;
use Kct\Seo\EventSeoData;
use Kct\Seo\RankMathOutput;
use Kct\Seo\StandaloneOutput;

/**
 * SEO detailů akcí.
 *
 * Virtuální stránky /akce-db/{id} vznikají rewrite pravidlem na index.php?db_id=…,
 * takže je WordPress vyhodnotí jako výpis blogu a Rank Math jim dá titulek
 * a kanonickou adresu stránky nastavené jako page_for_posts. Feature to přepíše
 * a doplní strukturovaná data.
 */
class EventSeo {

	public function __construct(
		private Events $events,
		private EventSeoData $data,
		private CanonicalSites $sites,
		private EventRepository $event_repository
	) {
		// Obojí na `wp`, ne na `template_redirect`. Rank Math skládá titulek,
		// popisek i kanonickou adresu na akci `wp` (class-frontend.php:76 volá
		// Paper::get()), která běží dřív — pozdější set_404() by tedy změnilo
		// jen stavovou hlavičku a 404 stránka by se dál tvářila jako výpis
		// aktualit včetně kanonické adresy na něj.
		add_action( 'wp', array( $this, 'handle_event_request' ), 1 );
		add_action( 'wp', array( $this, 'setup' ) );

		// Mapa webů nesmí přežít změnu, která ji zneplatňuje.
		add_action( 'wp_initialize_site', array( $this, 'flush_sites' ) );
		add_action( 'wp_delete_site', array( $this, 'flush_sites' ) );
		add_action( 'update_option_' . \Kct\Settings::KEY, array( $this, 'flush_sites' ) );

		// Mapa drží i permalinky spárovaných příspěvků, takže ji zneplatní
		// i uložení nebo smazání akce — jinak by kanonická adresa až půl dne
		// ukazovala na starý slug.
		add_action( 'save_post_' . $this->event_repository->post_type(), array( $this, 'flush_sites' ) );
		add_action( 'deleted_post', array( $this, 'flush_sites' ) );
	}

	public function flush_sites(): void {
		$this->sites->flush();
	}

	/**
	 * Obsluha požadavku na virtuální stránku akce.
	 *
	 * Řeší dva stavy, které by jinak skončily špatně: akce, která v tabulce
	 * není (dřív HTTP 500), a akce, která má vlastní CPT příspěvek, takže by
	 * tentýž obsah žil na dvou adresách.
	 */
	public function handle_event_request(): void {
		$db_id = (int) get_query_var( 'db_id' );

		if ( $db_id <= 0 ) {
			return;
		}

		// Převedená akce má vlastní příspěvek. Trvalé přesměrování, protože ten
		// stav je konečný — jednou převedená akce se zpátky nevrací.
		$post_id = $this->event_repository->find_by_db_id( $db_id );

		if ( $post_id ) {
			wp_safe_redirect( get_permalink( $post_id ), 301, 'kct' );
			exit;
		}

		// Akce neexistuje — buď je db_id z URL vymyšlené, nebo ji import smazal,
		// protože ji feed označil jako zrušenou. Vyhledávač musí dostat 404,
		// jinak si mrtvou adresu podrží v indexu.
		if ( ! $this->events->get_event( 0, $db_id ) ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			// Šablona index.php větví podle db_id dřív než podle is_404(),
			// takže by i na 404 vykreslila prázdný detail akce.
			set_query_var( 'db_id', '' );
		}
	}

	/** Rozpozná kontext a předá data výstupu. */
	public function setup(): void {
		$context = $this->context();

		if ( ! $context ) {
			return;
		}

		list( $event, $canonical, $is_single ) = $context;

		$this->output()->render( $event, $canonical, $is_single );
	}

	/**
	 * @return array|null [pole akce, kanonická adresa, je to CPT příspěvek?]
	 */
	private function context(): ?array {
		$db_id = (int) get_query_var( 'db_id' );

		if ( $db_id > 0 ) {
			$event = $this->events->get_event( 0, $db_id );

			return $event ? array( $event, $this->sites->url_for( $event ), false ) : null;
		}

		if ( is_singular( $this->event_repository->post_type() ) ) {
			$post_id = get_queried_object_id();
			$event   = $this->events->get_event( $post_id, '' );

			// U příspěvku je kanonická adresa jeho permalink — příspěvek žije
			// na tomhle webu a Rank Math ho určuje správně.
			return $event ? array( $event, get_permalink( $post_id ), true ) : null;
		}

		return null;
	}

	private function output(): \Kct\Seo\EventSeoOutput {
		return class_exists( 'RankMath' )
			? kct_container()->get( RankMathOutput::class )
			: kct_container()->get( StandaloneOutput::class );
	}
}
```

- [x] **Krok 3: Zaregistruj feature**

V `src/Managers/FeaturesManager.php` přidej import a parametr konstruktoru:

```php
use Kct\Features\EventSeo;
```

```php
	public function __construct(
		Events $events,
		Roads $roads,
		FacebookShare $facebook_share,
		OpenGraph $open_graph,
		Lightbox $lightbox,
		EventSeo $event_seo
	) {
	}
```

- [x] **Krok 4: Dočasně vypni volání výstupu**

`RankMathOutput` a `StandaloneOutput` ještě neexistují. Aby šlo přesměrování ověřit
hned, zakomentuj v `EventSeo::setup()` poslední řádek:

```php
		//$this->output()->render( $event, $canonical, $is_single );
```

- [x] **Krok 5: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/EventSeoOutput.php
php -l wp-content/plugins/kct/src/Features/EventSeo.php
php -l wp-content/plugins/kct/src/Managers/FeaturesManager.php
```

Očekávaný výstup: třikrát `No syntax errors detected`

- [x] **Krok 6: Ověř přesměrování spárované akce**

```bash
curl -sk -o /dev/null -w "%{http_code} → %{redirect_url}\n" https://sokct.test/akce-db/24065/
```

Očekávaný výstup:

```
301 → https://sokct.test/akce/3-den-s-klubem-ceskych-turistu-v-templu/
```

- [x] **Krok 7: Ověř, že nespárovaná akce zůstává**

```bash
curl -sk -o /dev/null -w "%{http_code} → %{redirect_url}\n" https://sokct.test/akce-db/23954/
```

Očekávaný výstup: `200 → ` (bez přesměrování)

- [x] **Krok 7b: Ověř 404 pro neznámou akci**

```bash
curl -sk -o /dev/null -w "%{http_code} → %{redirect_url}\n" https://sokct.test/akce-db/999999/
curl -sk https://sokct.test/akce-db/999999/ | grep -oE '<title>[^<]*</title>'
```

Očekávané: `404 → ` (před opravou to bylo HTTP 500) a titulek chybové stránky,
ne prázdný detail akce. Pokud přijde `302 → sokct.test`, není opravená konstanta
`NOBLOGREDIRECT` — viz „Než začneš“.

- [x] **Krok 8: Ověř, že se nerozbil zbytek webu**

```bash
for u in / /akce/ /odbory/ /aktuality-a-zpravy/ /clensvi-v-kct/; do
  printf "%-25s" "$u"
  curl -sk -o /dev/null -w "%{http_code}\n" "https://sokct.test$u"
done
```

Očekávané: pětkrát `200`.

---

## Task 4: `RankMathOutput` — titulek, popisek, kanonická adresa, OG

**Soubory:**
- Vytvoř: `wp-content/plugins/kct/src/Seo/RankMathOutput.php`
- Uprav: `wp-content/plugins/kct/src/Features/EventSeo.php`

- [x] **Krok 1: Vytvoř třídu**

Vytvoř `src/Seo/RankMathOutput.php`:

```php
<?php

namespace Kct\Seo;

/**
 * Přepíše hodnoty, které Rank Math na detailu akce skládá špatně.
 *
 * Filtry se registrují až uvnitř kontextu akce (volá se z EventSeo::setup()
 * na hooku `wp`), takže na ostatních stránkách webu vůbec nevzniknou.
 */
class RankMathOutput implements EventSeoOutput {

	private array $event = array();
	private string $canonical = '';
	private bool $is_single = false;

	public function __construct( private EventSeoData $data ) {
	}

	public function render( array $event, string $canonical, bool $is_single ): void {
		$this->event     = $event;
		$this->canonical = $canonical;
		$this->is_single = $is_single;

		// U CPT příspěvku skládá titulek, popisek i kanonickou adresu Rank Math
		// správně (a redakce je může ručně přepsat) — přepisovat je by editorovi
		// sebralo kontrolu. Přidává se jen JSON-LD, to řeší Task 5.
		if ( $is_single ) {
			return;
		}

		add_filter( 'rank_math/frontend/title', array( $this, 'title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'description' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );

		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'title' ) );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'description' ) );
		add_filter( 'rank_math/opengraph/facebook/og_url', array( $this, 'canonical' ) );
		// Pozor na název: `og_image` se volá až uvnitř tag(), a ten se spustí
		// jen když Rank Math nějaký obrázek našel. Virtuální stránka nemá post
		// ani featured image, takže by se filtr nikdy nespustil. Filtr `image`
		// sedí v Image::add_image() (class-image.php:222) a volá se vždy,
		// i s prázdným obrázkem.
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'image' ) );
		add_filter( 'rank_math/opengraph/facebook/og_type', array( $this, 'og_type' ) );

		add_filter( 'rank_math/opengraph/twitter/twitter_title', array( $this, 'title' ) );
		add_filter( 'rank_math/opengraph/twitter/twitter_description', array( $this, 'description' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'image' ) );
	}

	/**
	 * Titulek se sufixem názvu webu.
	 *
	 * Rank Math sufix doplňuje ze šablony `%title% %sep% %sitename%`, ale ta se
	 * na virtuální stránce vyhodnotí proti stránce page_for_posts. Skládá se
	 * proto tady, ze stejného oddělovače, jaký má web nastavený.
	 */
	public function title( $title ) {
		$own = $this->data->title( $this->event );

		if ( '' === $own ) {
			return $title;
		}

		$separator = html_entity_decode( \RankMath\Helper::get_settings( 'titles.title_separator' ) ?: '-' );

		return $own . ' ' . $separator . ' ' . get_bloginfo( 'name' );
	}

	public function description( $description ) {
		return $this->data->description( $this->event ) ?: $description;
	}

	public function canonical( $canonical ) {
		return $this->canonical ?: $canonical;
	}

	public function image( $image ) {
		return $this->image_url() ?: $image;
	}

	/** Akce je událost, ne rozcestník — `website` by tu byl nesmysl. */
	public function og_type( $type ) {
		return 'article';
	}

	/**
	 * Obrázek akce, jinak logo oblasti.
	 *
	 * Vlastní obrázek má 6 z 318 akcí, takže záloha je běžný stav. Bez ní by
	 * sdílený odkaz na Facebooku vypadal jako holý text.
	 */
	private function image_url(): string {
		$url = trim( (string) ( $this->event['image']['url'] ?? '' ) );

		if ( $url ) {
			return $url;
		}

		$logo = get_theme_mod( 'custom_logo' );

		return $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : '';
	}
}
```

- [x] **Krok 2: Zapni volání výstupu**

V `src/Features/EventSeo.php` odkomentuj řádek z Tasku 3, kroku 4:

```php
		$this->output()->render( $event, $canonical, $is_single );
```

- [x] **Krok 3: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/RankMathOutput.php
php -l wp-content/plugins/kct/src/Features/EventSeo.php
```

Očekávaný výstup: dvakrát `No syntax errors detected`

- [x] **Krok 4: Ověř hlavičku detailu akce**

```bash
curl -sk https://sokct.test/akce-db/23981/ \
  | grep -E '<title>|name="description"|rel="canonical"|og:title|og:description|og:url|og:image|og:type'
```

Očekávané: titulek „21. Kralupské kolo — Kralupy nad Vltavou, 20. 9. 2026 -
KČT Středočeská oblast“, popisek složený z dat, canonical i `og:url`
`https://sokct.test/akce-db/23981/`, `og:image` s logem oblasti, `og:type` `article`.
Nikde už nesmí být „Aktuality a zprávy z oblasti“ ani `/aktuality-a-zpravy/`.

- [x] **Krok 5: Ověř kanonickou adresu přes weby**

```bash
for host in sokct.test kctricany.test; do
  printf "%-18s" "$host"
  curl -sk "https://$host/akce-db/23954/" | grep -o 'rel="canonical" href="[^"]*"'
done
```

Očekávané: obojí `https://kctrakovnik.sokct.test/akce-db/23954/` — akce patří
odboru 102131, který má vlastní web.

- [x] **Krok 6: Ověř, že se nezměnily ostatní stránky**

```bash
for u in / /akce/ /aktuality-a-zpravy/ /novinky/100-let-turistiky-v-kralupech-nad-vltavou/; do
  echo "--- $u"
  curl -sk "https://sokct.test$u" | grep -E '<title>|rel="canonical"'
done
```

Očekávané: titulky i kanonické adresy stejné jako před změnou — zvlášť
`/aktuality-a-zpravy/` musí zůstat „Aktuality a zprávy z oblasti“.

---

## Task 5: JSON-LD — `Event`, přetypování `WebPage`, globální entity

Rank Math na CPT akcích nevypisuje **žádné** JSON-LD. `get_default_schema_type()`
propouští jen `Article`, `NewsArticle`, `BlogPosting`, `WooCommerceProduct`
a `EDDProduct` (`includes/helpers/class-schema.php:69`), takže nastavení „Event“
vrátí `false`, spadne `can_add_global_entities()` a s ním i `WebSite`, `WebPage`
a `BreadcrumbList`.

**Soubory:**
- Uprav: `wp-content/plugins/kct/src/Seo/EventSeoData.php`
- Uprav: `wp-content/plugins/kct/src/Seo/RankMathOutput.php`

- [x] **Krok 1: Přidej skládání `Event` grafu**

Do `src/Seo/EventSeoData.php` přidej před metodu `discipline_sentence()`:

```php
	/**
	 * Graf schema.org/Event.
	 *
	 * @param array  $event     Pole akce z Events::get_event().
	 * @param string $canonical Kanonická adresa akce.
	 * @param string $image     Absolutní URL obrázku, může být prázdné.
	 */
	public function event_schema( array $event, string $canonical, string $image ): array {
		$title = $this->text( $event['title'] ?? '' );
		$start = $this->iso_datetime( $event['start']['date'] ?? ( $event['date'] ?? '' ), $event['start']['time'] ?? '' );

		// Bez názvu a data by schema stejně neprošlo validací — radši nic
		// než neúplná entita.
		if ( '' === $title || '' === $start ) {
			return array();
		}

		$schema = array(
			'@type'       => 'Event',
			'name'        => $title,
			'startDate'   => $start,
			'description' => $this->description( $event ),
		);

		$end = $this->iso_datetime( $event['finish']['date'] ?? '', $event['finish']['time'] ?? '' );
		if ( $end && $end !== $start ) {
			$schema['endDate'] = $end;
		}

		// eventStatus u proběhlé akce vynecháváme — tvrdit "EventScheduled"
		// o něčem, co bylo loni, není pravda.
		if ( ! $this->is_past( $event ) ) {
			$schema['eventStatus'] = 'https://schema.org/EventScheduled';
		}

		$location = $this->location( $event );
		if ( $location ) {
			$schema['location'] = $location;
		}

		$organiser = $this->text( $event['organiser']['name'] ?? '' );
		if ( $organiser ) {
			$schema['organizer'] = array(
				'@type' => 'Organization',
				'name'  => $organiser,
			);
		}

		if ( $canonical ) {
			$schema['url'] = $canonical;
		}

		if ( $image ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/** Místo konání s GPS, když je k dispozici. */
	private function location( array $event ): array {
		// Do schematu jde podrobné místo srazu, ne krátký název obce — tady
		// na délce nezáleží a podrobnost pomáhá.
		$name = $this->text( $event['start']['place'] ?? '' )
			?: $this->text( $event['place'] ?? '' );

		if ( '' === $name ) {
			return array();
		}

		$location = array(
			'@type' => 'Place',
			'name'  => $name,
		);

		// gps_n/gps_e jsou u 79 z 318 akcí prázdné pole, ne řetězec — proto
		// text(), ne přímé přetypování. Bez toho by na těch stránkách padalo
		// "Array to string conversion".
		$lat = $this->text( $event['start']['gps_n'] ?? '' );
		$lng = $this->text( $event['start']['gps_e'] ?? '' );

		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$location['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}

		// Bez adresy Google Event nezobrazí, ale obec je adresa dost dobrá.
		$town = $this->text( $event['place'] ?? '' );
		if ( $town ) {
			$location['address'] = array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $town,
				'addressCountry'  => 'CZ',
			);
		}

		return $location;
	}

	/**
	 * Datum a čas v ISO 8601.
	 *
	 * `time` je lidský rozsah ("6:00–12:00", "do 18:00"), ne strojový čas.
	 * Bere se vedoucí H:MM; když tam žádný není, vrátí se jen datum —
	 * schema.org to připouští a je to poctivější než hádat.
	 */
	private function iso_datetime( $date, $time ): string {
		$date = trim( (string) $date );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		if ( preg_match( '/(\d{1,2}):(\d{2})/', (string) $time, $m ) ) {
			$datetime = DateTimeImmutable::createFromFormat(
				'Y-m-d H:i',
				sprintf( '%s %d:%s', $date, (int) $m[1], $m[2] ),
				wp_timezone()
			);

			if ( $datetime ) {
				return $datetime->format( 'c' );
			}
		}

		return $date;
	}

	/** Proběhla akce už? Rozhoduje datum konce, u jednodenní datum konání. */
	public function is_past( array $event ): bool {
		$date = trim( (string) ( $event['finish']['date'] ?? '' ) )
			?: trim( (string) ( $event['date'] ?? '' ) );

		$timestamp = $date ? strtotime( $date . ' 23:59:59' ) : 0;

		return $timestamp && $timestamp < current_time( 'timestamp' );
	}
```

- [x] **Krok 2: Napoj JSON-LD ve výstupu**

V `src/Seo/RankMathOutput.php` přidej do `render()` **před** podmínku
`if ( $is_single )`, aby platilo pro virtuální stránky i pro CPT příspěvky:

```php
		add_filter( 'rank_math/json_ld', array( $this, 'json_ld' ), 20 );

		// Bez tohohle Rank Math na CPT akci nevypíše vůbec nic — ani WebSite,
		// ani WebPage, ani BreadcrumbList. Nastavení "Event" v Titles & Meta
		// totiž neprojde whitelistem v get_default_schema_type().
		add_filter( 'rank_math/schema/add_global_entities', '__return_true' );
```

a přidej metodu:

```php
	/**
	 * Doplní Event a opraví entitu stránky.
	 *
	 * Na virtuální stránce je `is_home()` pravdivé, takže Rank Math WebPage
	 * otypuje jako CollectionPage a dá jí název a adresu výpisu blogu. Detail
	 * akce ale rozcestník není.
	 */
	public function json_ld( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$schema = $this->data->event_schema( $this->event, $this->canonical, $this->image_url() );

		if ( $schema ) {
			$data['richSnippet'] = $schema;
		}

		if ( ! $this->is_single && isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
			$data['WebPage']['@type'] = 'WebPage';
			$data['WebPage']['name']  = $this->data->title( $this->event );

			if ( $this->canonical ) {
				$data['WebPage']['url']  = $this->canonical;
				$data['WebPage']['@id']  = $this->canonical . '#webpage';
			}
		}

		return $data;
	}
```

- [x] **Krok 3: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/EventSeoData.php
php -l wp-content/plugins/kct/src/Seo/RankMathOutput.php
```

Očekávaný výstup: dvakrát `No syntax errors detected`

- [x] **Krok 4: Ověř `Event` na virtuální stránce**

```bash
curl -sk https://sokct.test/akce-db/23981/ \
  | grep -o '<script type="application/ld+json"[^>]*>.*</script>' \
  | sed 's/<[^>]*>//g' | python3 -m json.tool
```

Očekávané: v `@graph` je entita `Event` s `name`, `startDate` ve tvaru
`2026-09-20T…+02:00`, `location` s `geo` a `address`, `organizer`, `url`, `image`
a `eventStatus`. Entita stránky má `@type` `WebPage`, ne `CollectionPage`.

- [x] **Krok 5: Ověř `Event` na CPT akci a návrat globálních entit**

```bash
curl -sk https://sokct.test/akce/12-jarni-sraz-turistu-stredoceske-oblasti-kct/ \
  | grep -o '<script type="application/ld+json"[^>]*>.*</script>' \
  | sed 's/<[^>]*>//g' | python3 -c 'import json,sys;d=json.load(sys.stdin);print([n.get("@type") for n in d["@graph"]])'
```

Očekávané: seznam obsahuje `Event`, `WebSite` a `WebPage` — před změnou tu nebylo
**nic** (0 bloků JSON-LD).

`BreadcrumbList` v grafu **nebude** a není to chyba téhle změny: Rank Math ho
podmiňuje `Helper::is_breadcrumbs_enabled()` nezávisle na `add_global_entities`
(`class-jsonld.php`, `can_add_breadcrumb()`), a web má `breadcrumbs = off`. Chybí
proto plošně, i na běžném článku. Zapnutí drobečků je samostatný nález z auditu
(M2), ne součást tohoto úkolu — nezapínej to tady, změna se propíše na celý web.

- [x] **Krok 6: Ověř proběhlou akci**

Vyber akci s datem v minulosti a zkontroluj, že v jejím schematu **není**
`eventStatus`:

```bash
ddev wp --url=https://sokct.test eval '
global $wpdb;
$id = $wpdb->get_var( "SELECT db_id FROM {$wpdb->prefix}db_events WHERE date < CURDATE() ORDER BY date DESC LIMIT 1" );
$events = kct_container()->get( Kct\Features\Events::class );
$sites  = kct_container()->get( Kct\Seo\CanonicalSites::class );
$data   = new Kct\Seo\EventSeoData();
$e      = $events->get_event( 0, (int) $id );
$s      = $data->event_schema( $e, $sites->url_for( $e ), "" );
echo $id . " proběhla: " . var_export( $data->is_past( $e ), true )
	. " | eventStatus: " . var_export( isset( $s["eventStatus"] ), true ) . "\n";
'
```

Očekávaný výstup: `proběhla: true | eventStatus: false`

- [x] **Krok 7: Ověř, že se nerozbilo JSON-LD jinde**

```bash
for u in / /aktuality-a-zpravy/ /novinky/100-let-turistiky-v-kralupech-nad-vltavou/ /odbory/; do
  printf "%-60s" "$u"
  curl -sk "https://sokct.test$u" | grep -c 'application/ld+json'
done
```

Očekávané: `/` a příspěvek mají `1`, `/aktuality-a-zpravy/` a `/odbory/` stejný
počet jako před změnou (ověř proti `git stash` stavu, pokud si nejsi jistý).

---

## Task 6: `StandaloneOutput` — weby bez Rank Mathu

`kctpodebrady` nemá aktivní žádný plugin kromě síťového `kct`, takže filtry
Rank Mathu tam nemá kdo zavolat.

**Soubory:**
- Vytvoř: `wp-content/plugins/kct/src/Seo/StandaloneOutput.php`

- [x] **Krok 1: Vytvoř třídu**

Vytvoř `src/Seo/StandaloneOutput.php`:

```php
<?php

namespace Kct\Seo;

/**
 * Výstup pro weby bez SEO pluginu.
 *
 * Feature OpenGraph se na těchto webech stará o běžné stránky, ale detail akce
 * nezná — je to virtuální stránka bez WP_Post, takže by pro ni složila tagy
 * z výpisu blogu. Tahle třída ji na detailu akce zastoupí.
 */
class StandaloneOutput implements EventSeoOutput {

	private array $event = array();
	private string $canonical = '';

	public function __construct( private EventSeoData $data ) {
	}

	public function render( array $event, string $canonical, bool $is_single ): void {
		// U CPT příspěvku odvede práci feature OpenGraph — je to skutečný
		// WP_Post a tagy z něj složí správně.
		if ( $is_single ) {
			return;
		}

		$this->event     = $event;
		$this->canonical = $canonical;

		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_action( 'wp_head', array( $this, 'head' ), 4 );

		// Feature OpenGraph vypisuje na prioritě 5 a pro tenhle kontext by
		// složila tagy z výpisu blogu. Tady je nahrazujeme.
		remove_action( 'wp_head', array( kct_container()->get( \Kct\Features\OpenGraph::class ), 'render' ), 5 );
	}

	public function document_title( $title ) {
		$own = $this->data->title( $this->event );

		return $own ? $own . ' - ' . get_bloginfo( 'name' ) : $title;
	}

	public function head(): void {
		$title       = $this->data->title( $this->event );
		$description = $this->data->description( $this->event );
		$image       = trim( (string) ( $this->event['image']['url'] ?? '' ) );

		if ( ! $image ) {
			$logo  = get_theme_mod( 'custom_logo' );
			$image = $logo ? (string) wp_get_attachment_image_url( $logo, 'full' ) : '';
		}

		$tags = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:type', 'article' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', $this->canonical ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'property', 'og:image', $image ),
			array( 'name', 'twitter:card', 'summary_large_image' ),
			array( 'name', 'twitter:title', $title ),
			array( 'name', 'twitter:description', $description ),
			array( 'name', 'twitter:image', $image ),
		);

		if ( $this->canonical ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $this->canonical ) );
		}

		foreach ( $tags as $tag ) {
			list( $attr, $name, $content ) = $tag;

			if ( '' === $content ) {
				continue;
			}

			printf(
				'<meta %1$s="%2$s" content="%3$s" />' . "\n",
				esc_attr( $attr ),
				esc_attr( $name ),
				esc_attr( $content )
			);
		}

		$schema = $this->data->event_schema( $this->event, $this->canonical, $image );

		if ( $schema ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode(
					array(
						'@context' => 'https://schema.org',
						'@graph'   => array( $schema ),
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				)
			);
		}
	}
}
```

- [x] **Krok 2: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/StandaloneOutput.php
```

Očekávaný výstup: `No syntax errors detected`

- [x] **Krok 3: Ověř výstup na webu bez Rank Mathu**

```bash
curl -sk https://kctpodebrady.sokct.test/akce-db/23981/ \
  | grep -E '<title>|name="description"|rel="canonical"|og:|twitter:|ld\+json'
```

Očekávané: titulek akce, popisek z dat, canonical na `https://sokct.test/akce-db/23981/`
(odbor 102073 vlastní web nemá), OG i Twitter tagy a jeden blok JSON-LD s `Event`.

- [x] **Krok 4: Ověř, že tagy nejsou dvakrát**

```bash
curl -sk https://kctpodebrady.sokct.test/akce-db/23981/ | grep -c 'og:title'
```

Očekávaný výstup: `1`

- [x] **Krok 5: Ověř, že běžné stránky toho webu zůstaly beze změny**

```bash
curl -sk https://kctpodebrady.sokct.test/ | grep -cE 'og:title'
```

Očekávaný výstup: `1` — feature `OpenGraph` se na domovské musí pořád uplatnit.

---

## Task 7: Sitemapa

**Soubory:**
- Vytvoř: `wp-content/plugins/kct/src/Seo/EventSitemapProvider.php`
- Uprav: `wp-content/plugins/kct/src/Features/EventSeo.php`

- [x] **Krok 1: Vytvoř provider**

Vytvoř `src/Seo/EventSitemapProvider.php`:

```php
<?php

namespace Kct\Seo;

use Kct\Repositories\DbEventRepository;
use Kct\Repositories\EventRepository;
use RankMath\Sitemap\Providers\Provider;

/**
 * Sitemapa detailů akcí pro Rank Math.
 *
 * Web vypisuje jen akce, pro které je kanonický — akce odborů s vlastním webem
 * patří do sitemapy toho webu. Akce převedené na CPT se vynechávají, jejich
 * virtuální adresa přesměrovává na příspěvek.
 */
class EventSitemapProvider implements Provider {

	const TYPE = 'akce-db';

	public function __construct(
		private DbEventRepository $db_event_repository,
		private EventRepository $event_repository,
		private CanonicalSites $sites
	) {
	}

	public function handles_type( $type ) {
		return self::TYPE === $type;
	}

	public function get_index_links( $max_entries ) {
		$count = count( $this->urls() );

		if ( ! $count ) {
			return array();
		}

		return array(
			array(
				'loc'     => \RankMath\Sitemap\Router::get_base_url( self::TYPE . '-sitemap.xml' ),
				'lastmod' => '',
			),
		);
	}

	public function get_sitemap_links( $type, $max_entries, $current_page ) {
		return $this->urls();
	}

	/** @return array Seznam položek pro sitemapu. */
	private function urls(): array {
		$links = array();

		foreach ( $this->db_event_repository->find_all_by_date( '2000-01-01' ) as $db_event ) {
			$event = $db_event->to_array();

			if ( ! $this->sites->is_canonical_here( $event ) ) {
				continue;
			}

			// Převedená akce má vlastní příspěvek, který je v akce-sitemap.xml.
			if ( $this->event_repository->find_by_db_id( (int) $db_event->db_id ) ) {
				continue;
			}

			$links[] = array(
				'loc' => $this->sites->url_for( $event ),
				'mod' => $db_event->date,
			);
		}

		return $links;
	}
}
```

- [x] **Krok 2: Ověř, že provider dostane všechny akce**

`DbEventRepository::find_all_by_date()` má výchozí `date_from` `2023-01-01`, což je
starší než nejstarší řádek v tabulce (2022-02-21 je datum uvnitř serializovaného
pole `start`, sloupec `date` začíná později). Ověř to:

```bash
ddev wp --url=https://sokct.test eval '
$repo = kct_container()->get( Kct\Repositories\DbEventRepository::class );
echo count( $repo->find_all_by_date( "2000-01-01" ) ) . " akcí\n";
'
```

Očekávaný výstup: `318 akcí`

Metodu `find_all()` **nepřidávej** — zděděná varianta z wpify/model nepřepíná na
hlavní web, takže by z odborového webu sáhla na neexistující tabulku. Provider proto
volá `find_all_by_date( '2000-01-01' )`, která přepnutí řeší.

- [x] **Krok 3: Zaregistruj provider**

Do `src/Features/EventSeo.php` přidej do konstruktoru:

```php
		add_filter( 'rank_math/sitemap/providers', array( $this, 'sitemap_provider' ) );
```

a metodu:

```php
	/** Sitemapa akcí se registruje jen tam, kde je Rank Math. */
	public function sitemap_provider( $providers ) {
		if ( class_exists( 'RankMath' ) ) {
			$providers[] = kct_container()->get( \Kct\Seo\EventSitemapProvider::class );
		}

		return $providers;
	}
```

- [x] **Krok 4: Ověř syntaxi**

```bash
php -l wp-content/plugins/kct/src/Seo/EventSitemapProvider.php
php -l wp-content/plugins/kct/src/Features/EventSeo.php
```

Očekávaný výstup: dvakrát `No syntax errors detected`

- [x] **Krok 5: Zahoď cache sitemapy a ověř index**

```bash
ddev wp --url=https://sokct.test eval 'RankMath\Sitemap\Cache::invalidate_storage();'
curl -sk https://sokct.test/sitemap_index.xml | grep -o '<loc>[^<]*</loc>'
```

Očekávané: v seznamu přibyl `https://sokct.test/akce-db-sitemap.xml`.

- [x] **Krok 6: Ověř obsah sitemapy**

```bash
curl -sk https://sokct.test/akce-db-sitemap.xml | grep -c '<loc>'
curl -sk https://sokct.test/akce-db-sitemap.xml | grep -c 'kctrakovnik'
```

Očekávané: první číslo je počet akcí, pro které je oblastní web kanonický
a které nemají CPT příspěvek. Druhé musí být `0` — cizí domény do sitemapy
tohoto webu nepatří.

- [x] **Krok 7: Ověř sitemapu odborového webu**

**`kctrakovnik` na to nepoužívej** — má aktivní plugin `maintenance` a vrací
stránku „ve výstavbě". Odborový web s Rank Mathem a bez údržby je `kctzdice`:

```bash
ddev wp --url=https://kctzdice.sokct.test eval-file <soubor s Cache::invalidate_storage()>
curl -sk --max-time 20 https://kctzdice.sokct.test/akce-db-sitemap.xml | grep -c '<loc>'
```

Naměřený počet akcí, pro které je web kanonický (tedy očekávaný počet položek
v jeho sitemapě):

| web | kanonických akcí |
|---|---|
| `sokct.test` | 249 |
| `kctricany.test` | 6 |

Součet přes celou síť je 318 minus dvanáct akcí spárovaných s příspěvkem —
ty do `akce-db-sitemap.xml` nepatří, protože jejich virtuální adresa
přesměrovává a kanonická je permalink příspěvku v `akce-sitemap.xml`.

---

## Task 8: Poznámka u proběhlých akcí

Spec, kapitola 4: proběhlá akce zůstává indexovatelná, ale návštěvník musí hned
poznat, že jde o archiv. Bez toho vypadá stránka loňského pochodu jako pozvánka.

**Soubory:**
- Uprav: `wp-content/plugins/kct/themes/kct/template-parts/content-akce.php`
- Uprav: `wp-content/plugins/kct/assets/styles/core/components/eventpost.scss`

- [x] **Krok 1: Vypiš poznámku pod hlavičku akce**

V `themes/kct/template-parts/content-akce.php` najdi konec hlavičky:

```php
	</header><!-- .entry-header -->
	<div class="kct-block infoboxes">
```

a vlož mezi ně:

```php
	</header><!-- .entry-header -->
	<?php
	$kct_seo_data = new \Kct\Seo\EventSeoData();
	if ( $kct_seo_data->is_past( $event ) ) :
		$kct_past_date = $event['finish']['date'] ?? ( $event['date'] ?? '' );
		?>
		<div class="container">
			<p class="event-past-notice">
				<?php
				printf(
					/* translators: %s: datum konání akce */
					esc_html__( 'Tato akce už proběhla %s.', 'kct' ),
					esc_html( date_i18n( 'j. n. Y', strtotime( $kct_past_date ) ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>
	<div class="kct-block infoboxes">
```

- [x] **Krok 2: Přidej styl**

Na konec `assets/styles/core/components/eventpost.scss` přidej:

```scss
.event-past-notice {
	margin: 1.25rem 0 0;
	padding: .75rem 1rem;
	border-left: 4px solid var(--primary);
	background: var(--bg-muted, #f2f4f1);
	font-weight: 600;
	color: var(--text-muted);
}
```

- [x] **Krok 3: Ověř syntaxi a sestav styly**

```bash
php -l wp-content/plugins/kct/themes/kct/template-parts/content-akce.php
```

Očekávaný výstup: `No syntax errors detected`

```bash
cd wp-content/plugins/kct && rm -rf node_modules/.cache && npm run build && cd -
```

Vyčištění `node_modules/.cache` před buildem není zbytečnost — bez něj se změny
v SCSS někdy neprojeví, protože webpack drží starou verzi.

- [x] **Krok 4: Ověř na proběhlé akci**

```bash
ddev wp --url=https://sokct.test eval '
global $wpdb;
echo $wpdb->get_var( "SELECT db_id FROM {$wpdb->prefix}db_events WHERE date < CURDATE() ORDER BY date DESC LIMIT 1" );
'
```

Vezmi vypsané `db_id` a zkontroluj stránku:

```bash
curl -sk https://sokct.test/akce-db/<db_id>/ | grep -o 'event-past-notice.*</p>'
```

Očekávané: poznámka „Tato akce už proběhla …“ s datem konání.

- [x] **Krok 5: Ověř, že se u budoucí akce nezobrazuje**

```bash
curl -sk https://sokct.test/akce-db/23981/ | grep -c 'event-past-notice'
```

Očekávaný výstup: `0` — akce se koná 20. 9. 2026.

---

## Task 9: Závěrečné ověření celku

- [x] **Krok 1: Projdi kontrolní seznam ze specu**

Spec, kapitola 7. Projdi všech sedm bodů a zapiš výsledek.

- [x] **Krok 2: Ověř, že žádná akce nevrací chybu**

```bash
ddev wp --url=https://sokct.test eval '
global $wpdb;
echo implode( "\n", $wpdb->get_col( "SELECT db_id FROM {$wpdb->prefix}db_events LIMIT 40" ) );
' | while read id; do
  code=$(curl -sk -o /dev/null -w "%{http_code}" "https://sokct.test/akce-db/$id/")
  [ "$code" = "200" ] || [ "$code" = "301" ] || echo "PROBLÉM $id → $code"
done
echo "hotovo"
```

Očekávané: žádný řádek „PROBLÉM“.

- [x] **Krok 3: Ověř, že se nezměnil zbytek webu**

Projeď kontrolní stránky a porovnej titulek, kanonickou adresu a počet bloků
JSON-LD s výstupem před změnou:

```bash
for u in / /akce/ /odbory/ /aktuality-a-zpravy/ /clensvi-v-kct/ /stredoceska-desitka-2026/; do
  echo "--- $u"
  curl -sk "https://sokct.test$u" | grep -E '<title>|rel="canonical"'
  printf "ld+json: "; curl -sk "https://sokct.test$u" | grep -c 'application/ld+json'
done
```

- [x] **Krok 4: Ověř 404 a ostatní weby sítě**

```bash
curl -sk -o /dev/null -w "%{http_code}\n" https://sokct.test/akce-db/999999/
for host in kctricany.test kctpodebrady.sokct.test kctrakovnik.sokct.test; do
  printf "%-28s" "$host"
  curl -sk -o /dev/null -w "%{http_code}\n" "https://$host/"
done
```

Očekávané: neexistující `db_id` vrátí `404` (ne 500 jako před opravou a ne 302
do smyčky); domovské stránky všech webů `200`.

- [x] **Krok 5: Zkontroluj strukturovaná data validátorem**

Lokální doména není zvenčí dostupná, takže zkopíruj vygenerované JSON-LD
z kroku 4 Tasku 5 a vlož ho do validátoru:

- https://validator.schema.org/
- https://search.google.com/test/rich-results (volba „Kód“)

Očekávané: `Event` bez chyb. Varování u chybějícího `offers` je v pořádku —
většina akcí je bez startovného a vymýšlet si cenu by bylo horší.

- [x] **Krok 6: Shrň, co zbývá na produkci**

Sepiš pro Martina, co po nasazení udělat ručně:

1. Zahodit cache sitemap na všech webech sítě.
2. V Search Console poslat `sitemap_index.xml` znovu k načtení.
3. Počítat s tím, že počet indexovaných stránek povyskočí z jedné na několik
   stovek — výkyv v „Discovered – currently not indexed“ je čekaný.
4. Nahlásit do centrální databáze KČT duplicitu „Uzlařská regata, 30. ročník“
   (`db_id` 23990 a 23306, stejný odbor, dvě různá data).

---

## Poznámky k provozu

**Mapa webů se drží v síťovém transientu 12 hodin.** Když přibude odborový web,
akce se do zahození transientu kanonizují na oblastní. Invalidaci obstarávají
hooky `wp_initialize_site`, `wp_delete_site` a `update_option_kct_options`;
ručně jde vynutit přes:

```bash
ddev wp --url=https://sokct.test eval 'kct_container()->get( Kct\Seo\CanonicalSites::class )->flush();'
```

**Filtry Rank Mathu se registrují až v kontextu akce.** `EventSeo::setup()` běží
na hooku `wp` a filtry přidá jen tehdy, když `get_query_var( 'db_id' )` vrátí číslo
nebo jde o single CPT `akce`. Na ostatních stránkách žádný filtr nevznikne, takže
riziko, že se přepíše titulek celého webu, je omezené na chybu v `context()`.

**Nastavení `pt_akce_default_rich_snippet` zůstává `event`.** Rank Math z něj sice
nic negeneruje, ale mění chování editoru a `add_global_entities` si vynucujeme
filtrem. Přepnutí na `article` by vedlo k tomu, že by Rank Math k akcím přidával
i entitu `Article`, což by u události bylo zavádějící.
