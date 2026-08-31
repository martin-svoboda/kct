# Časování sdílení na Facebook — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-31-casovani-sdileni-design.md`](../specs/2026-08-31-casovani-sdileni-design.md)

**Cíl:** Rozdělit výchozí stav sdílení na aktuality a akce zvlášť, odesílat
akce s odstupem před jejich začátkem (výchozí 12 dní v 9:00) a odstranit
nastavení „Výchozí náhledový obrázek", které už nemá co dělat.

**Architektura:** Výpočet času odeslání jde do samostatné třídy
`Facebook\ShareSchedule` bez háků a bez HTTP — dostane pole akce a vrátí
timestamp, nebo `null` pro „neodesílat". `Features\FacebookShare` ji zavolá
uvnitř `share()`, tedy až ve chvíli, kdy jsou metadata jistě uložená, a když
je čas v budoucnu, přeplánuje se a skončí bez odeslání.

**Tech stack:** PHP 8.0+, WordPress multisite, PHP-DI (`config.php`),
wpify/custom-fields, WP-Cron.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám. Každý úkol končí
ověřením, ne commitem.

**Nikdy neodesílej nic na Facebook.** Kód, který měníš, publikuje na veřejnou
stránku. Nevolej `publish()`, `publish_photo()` ani `wp kct fb_share`.
Ověřování stojí na čtení kódu a na volání čistých funkcí.

**Nikdy nezapisuj do databáze kvůli ověření.** Žádné `update_option`,
`wp_insert_post`, editace příspěvků ani zápis post meta.

**V projektu není PHPUnit** a nezavádí se. Ověřuje se přes `php -l`
a `ddev wp eval-file`.

**Příkazy** se pouští z kořene projektu `/Users/martin/Sites/sokct`. Cesty
v plánu jsou relativní ke kořeni pluginu
`/Users/martin/Sites/sokct/wp-content/plugins/kct`.

Víceřádkový PHP kód nedávej do `ddev wp eval` — zapiš ho do souboru v kořeni
**projektu** a pusť `ddev wp --url=https://sokct.test eval-file <soubor>`,
pak soubor smaž.

**Po každé změně tříd smaž zkompilovaný kontejner:**

```bash
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

**Stav prostředí, na kterém se nemusíš ptát:** Facebook není nastavený ani
lokálně, ani na produkci (`kct_options` má jen `add_style` a `id_code`).
`is_configured()` proto vrací false a nic se neodešle — to je v pořádku a pro
ověřování v tomhle plánu to nevadí, protože se testují čisté funkce.

---

## Struktura souborů

| Soubor | Odpovědnost |
|---|---|
| `src/Facebook/ShareSchedule.php` | **nový** — výpočet času odeslání akce. Bez háků, bez HTTP, bez zápisu. |
| `src/Facebook/Credentials.php` | Výchozí stav sdílení podle typu obsahu; ubývá výchozí obrázek. |
| `src/Facebook/ShareState.php` | Nová meta konstanta pro přepsání počtu dní. |
| `src/Facebook/ShareMetabox.php` | Řádek s časem odeslání ve stavovém metaboxu. |
| `src/Settings.php` | Dvě nastavení místo jednoho, dvě nová pro časování, ubývá výchozí obrázek. |
| `src/Features/FacebookShare.php` | Zavolá `ShareSchedule`, přeplánuje při změně termínu, rozdělí metabox. |
| `src/Features/OpenGraph.php` | Ubývá záloha výchozím obrázkem. |

---

## Task 1: ShareSchedule — výpočet času odeslání

**Files:**
- Create: `src/Facebook/ShareSchedule.php`

Jediná část s netriviální logikou: časové pásmo, čtyři případy, přepsání
u konkrétní akce. Proto stojí zvlášť a dá se ověřit na vymyšlených datech.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Facebook;

use DateTimeImmutable;
use Exception;
use Kct\Repositories\SettingsRepository;

/**
 * Kdy se má akce odeslat na Facebook.
 *
 * Pozvánka na pochod, který je za půl roku, na Facebooku zapadne dřív, než
 * bude aktuální — odesílá se proto s odstupem před začátkem akce.
 *
 * Čistá třída: žádné WordPress háky, žádné HTTP, žádný zápis. Dostane pole
 * akce a vrátí čas, nebo null pro „neodesílat".
 */
class ShareSchedule {

	/**
	 * Kolik dní před začátkem akce se odesílá.
	 *
	 * Dvanáctka není kulaté číslo, ale volba podle dne v týdnu. Většina akcí
	 * je o víkendu a zveřejňovat pozvánku o víkendu je špatně — lidi plánují
	 * další víkend na začátku týdne. Odstup 12 dní to trefí:
	 *
	 *     akce v sobotu  →  odeslání v pondělí
	 *     akce v neděli  →  odeslání v úterý
	 *
	 * Kdo tohle číslo mění, mění den v týdnu, na který odeslání padne.
	 */
	public const DEFAULT_LEAD_DAYS = 12;

	/** V kolik hodin se odesílá. */
	public const DEFAULT_HOUR = 9;

	public function __construct( private SettingsRepository $settings ) {
	}

	/**
	 * Čas odeslání akce, nebo null když se odeslat nemá.
	 *
	 * @param array    $event          Pole akce z Features\Events::get_event().
	 * @param int|null $override_days  Přepsání počtu dní u konkrétní akce; null = nastavení webu.
	 *
	 * @return int|null Unixový čas, nebo null pro „neodesílat".
	 */
	public function target_for_event( array $event, ?int $override_days = null ): ?int {
		$date = $this->start_date( $event );

		if ( '' === $date ) {
			// Bez data se nedá nic spočítat. Odeslat hned je lepší než
			// neodeslat vůbec — akce bez data se v datech z importu vyskytují.
			return time();
		}

		$zone = wp_timezone();

		try {
			$start = new DateTimeImmutable( $date . ' 00:00:00', $zone );
		} catch ( Exception $e ) {
			return time();
		}

		$now = new DateTimeImmutable( 'now', $zone );

		// Porovnává se den, ne okamžik: akce, která začíná dnes dopoledne, se
		// pořád ještě pošle. Až akce od včerejška dál se považuje za
		// proběhlou — pozvánka na loňský pochod je horší než žádný příspěvek.
		if ( $start->format( 'Y-m-d' ) < $now->format( 'Y-m-d' ) ) {
			return null;
		}

		$target = $start
			->setTime( $this->hour(), 0 )
			->modify( '-' . $this->lead_days( $override_days ) . ' days' );

		// Akce, která začíná dřív než za nastavený odstup, se odešle hned.
		return max( $target->getTimestamp(), time() );
	}

	/**
	 * Kolik dní předem se odesílá — po zohlednění přepsání u akce.
	 *
	 * @param int|null $override Přepsání u konkrétní akce; null = nastavení webu.
	 */
	public function lead_days( ?int $override = null ): int {
		if ( null !== $override ) {
			return max( 0, $override );
		}

		$value = $this->settings->get_option( 'fb_event_lead_days' );

		return is_numeric( $value ) ? max( 0, (int) $value ) : self::DEFAULT_LEAD_DAYS;
	}

	/** V kolik hodin se odesílá. */
	public function hour(): int {
		$value = $this->settings->get_option( 'fb_event_hour' );

		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_HOUR;
		}

		$hour = (int) $value;

		return ( $hour >= 0 && $hour <= 23 ) ? $hour : self::DEFAULT_HOUR;
	}

	/**
	 * Datum začátku akce ve tvaru Y-m-d, nebo prázdný řetězec.
	 *
	 * Přednost má `start.date`, protože to je skutečný začátek; `date` je
	 * u části importovaných akcí jediné, co je vyplněné.
	 */
	private function start_date( array $event ): string {
		foreach ( array( $event['start']['date'] ?? '', $event['date'] ?? '' ) as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/ShareSchedule.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/ShareSchedule.php`

- [ ] **Step 3: Ověř výpočet na vymyšlených datech**

Zapiš do `/Users/martin/Sites/sokct/scheduletest.php`:

```php
<?php
$s    = kct_container()->get( \Kct\Facebook\ShareSchedule::class );
$zone = wp_timezone();
$fmt  = static function ( $ts ) use ( $zone ) {
	if ( null === $ts ) { return 'NEODESÍLAT'; }
	return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $zone )->format( 'D j. n. Y H:i' );
};
$day  = static fn( $offset ) => ( new DateTimeImmutable( 'now', $zone ) )->modify( $offset )->format( 'Y-m-d' );

$cases = array(
	'akce za měsíc'        => array( array( 'start' => array( 'date' => $day( '+30 days' ) ) ), null ),
	'akce za 3 dny'        => array( array( 'start' => array( 'date' => $day( '+3 days' ) ) ), null ),
	'akce dnes'            => array( array( 'start' => array( 'date' => $day( '+0 days' ) ) ), null ),
	'akce včera'           => array( array( 'start' => array( 'date' => $day( '-1 day' ) ) ), null ),
	'bez data'             => array( array(), null ),
	'jen klíč date'        => array( array( 'date' => $day( '+30 days' ) ), null ),
	'přepsání 3 dny'       => array( array( 'start' => array( 'date' => $day( '+30 days' ) ) ), 3 ),
	'přepsání 0 dní'       => array( array( 'start' => array( 'date' => $day( '+30 days' ) ) ), 0 ),
	'přepsání záporné'     => array( array( 'start' => array( 'date' => $day( '+30 days' ) ) ), -5 ),
	'rozbité datum'        => array( array( 'start' => array( 'date' => 'nesmysl' ) ), null ),
);

foreach ( $cases as $name => $case ) {
	printf( "%-18s %s\n", $name, $fmt( $s->target_for_event( $case[0], $case[1] ) ) );
}

echo "\nden v týdnu při odstupu 12 dní:\n";
foreach ( array( 'monday', 'saturday', 'sunday' ) as $weekday ) {
	$start  = ( new DateTimeImmutable( 'next ' . $weekday, $zone ) )->modify( '+4 weeks' );
	$target = $s->target_for_event( array( 'start' => array( 'date' => $start->format( 'Y-m-d' ) ) ), null );
	printf( "  akce v %-9s → %s\n", $start->format( 'D' ), $fmt( $target ) );
}

printf( "\nnastavení: %d dní, %d:00\n", $s->lead_days(), $s->hour() );
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test eval-file scheduletest.php && rm scheduletest.php
```

Očekávaný výstup — zkontroluj těchto šest věcí:

```
akce za měsíc      datum 30 dní dopředu minus 12 dní, čas 09:00
akce za 3 dny      dnešní datum a čas (odstup je delší než do akce)
akce dnes          dnešní datum a čas
akce včera         NEODESÍLAT
bez data           dnešní datum a čas
přepsání 3 dny     o 9 dní později než „akce za měsíc“
```

a v tabulce dnů v týdnu musí sobotní akce vyjít na **Mon** a nedělní na
**Tue**. Nastavení na konci musí být `12 dní, 9:00`.

---

## Task 2: Credentials — výchozí stav podle typu, konec výchozího obrázku

**Files:**
- Modify: `src/Facebook/Credentials.php`

- [ ] **Step 1: Nahraď share_by_default() a odstraň default_image_id()**

V `src/Facebook/Credentials.php` nahraď metodu `share_by_default()` tímto
a metodu `default_image_id()` **smaž celou i s docblockem**:

```php
	/**
	 * Výchozí stav přepínače „Sdílet na Facebook" u nového příspěvku.
	 *
	 * Aktuality a akce mají vlastní nastavení — jsou to jiné druhy obsahu
	 * a redakce u nich může chtít jiné chování.
	 *
	 * @param string $post_type Typ obsahu, kterého se dotaz týká.
	 */
	public function share_default_for( string $post_type ): bool {
		$key     = EventPostType::KEY === $post_type ? 'fb_share_default_akce' : 'fb_share_default_post';
		$options = $this->settings->get_options();

		if ( array_key_exists( $key, $options ) ) {
			return (bool) $options[ $key ];
		}

		// Dokud nová nastavení nikdo neuložil, platí staré společné. Bez toho
		// by web, kde bylo sdílení zapnuté, po nasazení tiše přestal sdílet.
		//
		// array_key_exists(), ne get_option(): ta vrací false i pro nenastavený
		// klíč, takže by nešlo rozlišit „neuloženo" od „uloženo vypnuté".
		return (bool) ( $options['fb_share_default'] ?? false );
	}
```

Nahoře v souboru doplň import:

```php
use Kct\PostTypes\EventPostType;
```

- [ ] **Step 2: Ověř syntaxi a že po výchozím obrázku nezbyly odkazy**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Facebook/Credentials.php
grep -rn 'default_image_id\|share_by_default' src/
```

Očekávaný výstup: `No syntax errors detected`, pak výpisy z
`src/Features/OpenGraph.php` a `src/Features/FacebookShare.php` — ty se
opraví v Tasku 5 a 6. Jinde nic.

---

## Task 3: Nastavení webu

**Files:**
- Modify: `src/Settings.php`

- [ ] **Step 1: Nahraď pole „Sdílet automaticky" a „Výchozí náhledový obrázek"**

V `src/Settings.php` najdi tyto dva bloky v poli `$settings`:

```php
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

a nahraď je tímto:

```php
			array(
				'title' => __( 'Sdílet automaticky — aktuality', 'kct' ),
				'label' => __( 'Nové aktuality mají sdílení ve výchozím stavu zapnuté.', 'kct' ),
				'id'    => 'fb_share_default_post',
				'type'  => 'toggle',
			),
			array(
				'title' => __( 'Sdílet automaticky — akce', 'kct' ),
				'label' => __( 'Nové akce mají sdílení ve výchozím stavu zapnuté.', 'kct' ),
				'id'    => 'fb_share_default_akce',
				'type'  => 'toggle',
			),
			array(
				'title' => __( 'Kolik dní před akcí odeslat', 'kct' ),
				'desc'  => __( 'Pozvánka se odešle s tímto odstupem před začátkem akce. Výchozích 12 dní není náhodné číslo: sobotní akce tím vyjde na pondělí a nedělní na úterý, tedy na začátek týdne, kdy lidé plánují další víkend. Nula znamená odeslat v den akce.', 'kct' ),
				'id'    => 'fb_event_lead_days',
				'type'  => 'number',
				'min'   => 0,
				'max'   => 365,
			),
			array(
				'title' => __( 'V kolik hodin odeslat', 'kct' ),
				'desc'  => __( 'Hodina, ve které se pozvánka na akci odešle. Bez ní by odešla v tu denní dobu, kdy byla akce náhodou publikována.', 'kct' ),
				'id'    => 'fb_event_hour',
				'type'  => 'number',
				'min'   => 0,
				'max'   => 23,
			),
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Settings.php
grep -c 'fb_default_image' src/Settings.php
```

Očekávaný výstup: `No syntax errors detected`, pak `0`.

---

## Task 4: Meta pro přepsání u akce

**Files:**
- Modify: `src/Facebook/ShareState.php`

- [ ] **Step 1: Přidej konstantu**

V `src/Facebook/ShareState.php` přidej za konstantu `META_MESSAGE`:

```php
	/** Přepsání počtu dní před akcí u konkrétní akce; prázdné = nastavení webu. */
	const META_LEAD_DAYS = 'kct_fb_lead_days';
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/ShareState.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/ShareState.php`

---

## Task 5: FacebookShare — plánování, přeplánování, rozdělený metabox

**Files:**
- Modify: `src/Features/FacebookShare.php`

- [ ] **Step 1: Doplň závislosti a hák**

V konstruktoru přidej dva parametry a jeden hák. Konstruktor:

```php
	public function __construct(
		private CustomFields $wcf,
		private Credentials $credentials,
		private GraphClient $client,
		private MessageComposer $composer,
		private ShareState $state,
		private OgImages $og_images,
		private ShareSchedule $schedule,
		private Events $events
	) {
```

Za řádek `add_action( 'transition_post_status', ... );` přidej:

```php
		// Termín akce se může po publikaci posunout. Naplánované odeslání se
		// proto zruší a rozhodne se znovu — priorita 20, ať jsou metadata
		// s novým datem už uložená.
		add_action( 'save_post_' . EventPostType::KEY, array( $this, 'reschedule' ), 20, 2 );
```

Nahoře v souboru doplň import (`Credentials`, `GraphClient` a další už tam
jsou; `EventPostType` také):

```php
use Kct\Facebook\ShareSchedule;
```

`Events` je v namespace `Kct\Features` stejně jako `FacebookShare`, takže
import nepotřebuje.

- [ ] **Step 2: Přidej konstantu tolerance**

Za konstantu `RETRY_DELAYS` přidej:

```php
	/**
	 * O kolik sekund musí být vypočtený čas odeslání v budoucnu, aby se
	 * přeplánovalo.
	 *
	 * Bez tolerance by se běh, kterému vyjde cíl o pár sekund dopředu, ještě
	 * jednou přeplánoval a odeslání by se zbytečně odložilo o další minutu.
	 */
	const SCHEDULE_TOLERANCE = 120;
```

- [ ] **Step 3: Rozhodni čas odeslání v share()**

V metodě `share()` najdi blok:

```php
		if ( ! $this->state->should_share( $post->ID, $this->credentials->share_by_default() ) ) {
			return;
		}
```

a nahraď ho tímto:

```php
		if ( ! $this->state->should_share( $post->ID, $this->credentials->share_default_for( $post->post_type ) ) ) {
			return;
		}

		// Akce se neodesílají hned po publikaci, ale s odstupem před začátkem.
		//
		// Rozhoduje se až tady, ne v maybe_schedule(): transition_post_status
		// běží uvnitř wp_insert_post(), tedy dřív, než metaboxy uloží svoje
		// metadata — datum akce tam ještě nemusí být. Proto má tenhle běh
		// zpoždění DELAY, viz komentář u té konstanty.
		if ( EventPostType::KEY === $post->post_type ) {
			$target = $this->schedule->target_for_event(
				$this->events->get_event( $post->ID, '' ),
				$this->lead_override( (int) $post->ID )
			);

			if ( null === $target ) {
				// Proběhlá akce. Pozvánka na loňský pochod je horší než
				// žádný příspěvek.
				return;
			}

			if ( $target > time() + self::SCHEDULE_TOLERANCE ) {
				wp_schedule_single_event( $target, self::CRON_HOOK, array( (int) $post->ID ) );

				return;
			}
		}
```

- [ ] **Step 4: Oprav druhé volání should_share()**

Ve stejném souboru v metodě `skip_reason()` najdi:

```php
		if ( ! $this->state->should_share( $post_id, $this->credentials->share_by_default() ) ) {
```

a nahraď:

```php
		if ( ! $this->state->should_share( $post_id, $this->credentials->share_default_for( (string) get_post_type( $post_id ) ) ) ) {
```

- [ ] **Step 5: Přidej přeplánování a čtení přepsání**

Na konec třídy, před uzavírací závorku:

```php
	/**
	 * Přeplánuje odeslání akce, které se posunul termín.
	 *
	 * Neplatí pro akci, která už odeslaná je — poslat pozvánku podruhé kvůli
	 * opravě překlepu v místě startu by bylo horší než tu opravu neudělat.
	 *
	 * @param int     $post_id ID příspěvku.
	 * @param WP_Post $post    Příspěvek.
	 */
	public function reschedule( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->credentials->is_configured() || $this->state->is_shared( (int) $post_id ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $post_id ) );
		wp_schedule_single_event( time() + self::DELAY, self::CRON_HOOK, array( (int) $post_id ) );
	}

	/**
	 * Přepsání počtu dní u konkrétní akce, nebo null.
	 *
	 * Prázdné pole znamená „použij nastavení webu", ne „nula dní" — proto se
	 * prázdná hodnota rozlišuje od vyplněné nuly.
	 */
	private function lead_override( int $post_id ): ?int {
		$value = get_post_meta( $post_id, ShareState::META_LEAD_DAYS, true );

		return is_numeric( $value ) ? (int) $value : null;
	}

	/**
	 * Řádek do metaboxu: kdy se akce odešle.
	 *
	 * Prázdný řetězec u aktuality a u akce, která je už odeslaná — tam by to
	 * bylo jen šum.
	 */
	public function schedule_note( WP_Post $post ): string {
		if ( EventPostType::KEY !== $post->post_type || $this->state->is_shared( (int) $post->ID ) ) {
			return '';
		}

		$target = $this->schedule->target_for_event(
			$this->events->get_event( $post->ID, '' ),
			$this->lead_override( (int) $post->ID )
		);

		if ( null === $target ) {
			return __( 'Akce už proběhla, na Facebook se neodešle.', 'kct' );
		}

		if ( $target <= time() + self::SCHEDULE_TOLERANCE ) {
			return __( 'Odešle se během několika minut.', 'kct' );
		}

		/* translators: %s: datum a čas odeslání. */
		return sprintf( __( 'Odešle se %s.', 'kct' ), wp_date( 'j. n. Y \v H:i', $target ) );
	}
```

- [ ] **Step 6: Rozděl metabox**

Nahraď metodu `register_metabox()`:

```php
	private function register_metabox(): void {
		// Dva metaboxy místo jednoho: liší se výchozí hodnota přepínače
		// (jiné nastavení pro aktuality a pro akce) i skladba polí (počet dní
		// jen u akcí), a to jedním voláním create_metabox() nejde.
		foreach ( $this->post_types() as $post_type ) {
			$this->wcf->create_metabox( array(
				'id'         => 'kct_facebook_' . $post_type,
				'title'      => __( 'Facebook', 'kct' ),
				'post_types' => array( $post_type ),
				'context'    => 'side',
				'priority'   => 'default',
				'items'      => $this->metabox_items( $post_type ),
			) );
		}
	}

	/**
	 * Pole metaboxu pro daný typ obsahu.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function metabox_items( string $post_type ): array {
		$items = array(
			array(
				'type'    => 'toggle',
				'id'      => ShareState::META_SHARE,
				'label'   => __( 'Sdílet na Facebook', 'kct' ),
				'default' => $this->credentials->share_default_for( $post_type ),
			),
			array(
				'type'  => 'textarea',
				'id'    => ShareState::META_MESSAGE,
				'label' => __( 'Text příspěvku', 'kct' ),
				'desc'  => __( 'Necháte-li prázdné, použije se automaticky složený text.', 'kct' ),
			),
		);

		if ( EventPostType::KEY === $post_type ) {
			$items[] = array(
				'type'  => 'number',
				'id'    => ShareState::META_LEAD_DAYS,
				'label' => __( 'Kolik dní předem', 'kct' ),
				'desc'  => sprintf(
					/* translators: %d: výchozí počet dní z nastavení webu. */
					__( 'Necháte-li prázdné, použije se nastavení webu (%d dní).', 'kct' ),
					$this->schedule->lead_days()
				),
				'min'   => 0,
				'max'   => 365,
			);
		}

		return $items;
	}
```

- [ ] **Step 7: Zaregistruj novou meta a předej řádek do metaboxu**

V metodě `state_meta_keys()` přidej na konec pole:

```php
			ShareState::META_LEAD_DAYS => 'integer',
```

A najdi řádek, kde vzniká `ShareMetabox`:

```php
		$this->metabox = new ShareMetabox( $this->state, $this->post_types() );
```

nahraď:

```php
		$this->metabox = new ShareMetabox(
			$this->state,
			$this->post_types(),
			fn( WP_Post $post ): string => $this->schedule_note( $post )
		);
```

- [ ] **Step 8: Ověř syntaxi a sestavení kontejneru**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Features/FacebookShare.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: `No syntax errors detected`, pak `KČT Středočeská oblast`.
Kdyby PHP-DI hlásilo chybu u `ShareMetabox`, chybí Task 6.

---

## Task 6: ShareMetabox — řádek s časem odeslání

**Files:**
- Modify: `src/Facebook/ShareMetabox.php`

- [ ] **Step 1: Přijmi a vypiš řádek**

Nahraď konstruktor:

```php
	/**
	 * @param ShareState                   $state      Stav odeslání.
	 * @param array                        $post_types Typy obsahu, u kterých se metabox zobrazí.
	 * @param callable(\WP_Post): string   $note       Vrátí řádek o plánovaném odeslání, nebo prázdný řetězec.
	 */
	public function __construct(
		private ShareState $state,
		private array $post_types,
		private $note
	) {
```

Poznámka: `ShareSchedule` ani `Events` se sem záměrně nepředávají. Metabox jen
vypisuje stav; co se má vypsat, rozhoduje `FacebookShare`, která obojí zná.

- [ ] **Step 2: Zaregistruj box i kvůli plánovanému odeslání**

`register()` dnes box přidá jen u odeslaného nebo chybného příspěvku. U čerstvé
akce — tedy v tom nejběžnějším případě — by se řádek s časem odeslání nikdy
neukázal. Nahraď podmínku:

```php
		// Box se ukáže i u příspěvku, který se teprve chystá odejít — u akce je
		// to právě ta informace, kvůli které vznikl: kdy se odešle. Dřív se
		// registroval jen u odeslaného nebo chybného, takže u čerstvé akce
		// (tedy v tom nejběžnějším případě) se neukázal vůbec.
		if (
			! $this->state->is_shared( $post->ID )
			&& ! $this->state->error( $post->ID )
			&& '' === ( $this->note )( $post )
		) {
			return;
		}
```

- [ ] **Step 3: Nahraď render()**

`render_error()` počítá s tím, že chyba existuje — vypsalo by „Odeslání
selhalo:" s prázdným důvodem u příspěvku, který žádnou chybu nemá. Od chvíle,
kdy se box registruje i kvůli plánovanému odeslání, se sem takový příspěvek
dostane, takže se volání musí podmínit.

Nahraď celou metodu `render()`:

```php
	public function render( WP_Post $post ): void {
		if ( $this->state->is_shared( $post->ID ) ) {
			$this->render_shared( $post );

			return;
		}

		// Podmíněně: render_error() počítá s tím, že chyba existuje, a bez ní
		// by vypsal „Odeslání selhalo:" s prázdným důvodem.
		if ( $this->state->error( $post->ID ) ) {
			$this->render_error( $post );
		}

		// Řádek o plánovaném odeslání se vypisuje jen u neodeslaného
		// příspěvku — u odeslaného už je nahoře datum odeslání a odkaz.
		$note = ( $this->note )( $post );

		if ( '' !== $note ) {
			printf( '<p class="description">%s</p>', esc_html( $note ) );
		}
	}
```

`render_shared()` a `render_error()` se nemění.

- [ ] **Step 4: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/ShareMetabox.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/ShareMetabox.php`

---

## Task 7: OpenGraph — konec zálohy výchozím obrázkem

**Files:**
- Modify: `src/Features/OpenGraph.php`

- [ ] **Step 1: Odstraň zálohu**

V metodě `image_url()` najdi:

```php
		$attachment_id = $post instanceof WP_Post ? get_post_thumbnail_id( $post ) : 0;

		if ( ! $attachment_id ) {
			$attachment_id = $this->credentials->default_image_id();
		}

		if ( ! $attachment_id ) {
			return null;
		}
```

a nahraď:

```php
		$attachment_id = $post instanceof WP_Post ? get_post_thumbnail_id( $post ) : 0;

		if ( ! $attachment_id ) {
			return null;
		}
```

- [ ] **Step 2: Srovnej docblock nad metodou**

Docblock metody `image_url()` popisuje chování, které po téhle změně
neexistuje. Uprav v něm dvě věci — nahraď první řádek:

```php
	 * Náhledový obrázek: vlastní sdílecí karta, jinak featured image příspěvku.
```

a **smaž celý tenhle odstavec**:

```php
	 * Bez SEO pluginu se default_image_id() čte z nastavení na každém
	 * requestu bez cache — transient s invalidací by se hodil, až provoz webu
	 * tenhle dotaz navíc skutečně zatíží.
	 *
```

Odstavec o velikosti `'full'` nech být — ten pořád platí.

- [ ] **Step 3: Ověř syntaxi a že po výchozím obrázku nezbylo nic**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Features/OpenGraph.php
grep -rn 'default_image_id\|fb_default_image\|share_by_default' src/ || echo "žádné zbylé odkazy"
```

Očekávaný výstup: `No syntax errors detected`, pak `žádné zbylé odkazy`.

---

## Task 8: Ověření celku

**Files:** žádné změny, jen kontrola.

- [ ] **Step 1: Ověř výchozí stav podle typu obsahu**

Zapiš do `/Users/martin/Sites/sokct/defaultstest.php`:

```php
<?php
$c = kct_container()->get( \Kct\Facebook\Credentials::class );

foreach ( array( 'post', 'akce' ) as $type ) {
	printf( "%-6s výchozí sdílení: %s\n", $type, $c->share_default_for( $type ) ? 'zapnuto' : 'vypnuto' );
}

// should_share() musí u příspěvku bez vlastního přepínače vrátit výchozí
// hodnotu podle typu, a u příspěvku s přepínačem jeho hodnotu.
$state = kct_container()->get( \Kct\Facebook\ShareState::class );

foreach ( array( 'post', 'akce' ) as $type ) {
	$posts = get_posts( array( 'post_type' => $type, 'numberposts' => 1, 'post_status' => 'publish' ) );

	if ( ! $posts ) {
		printf( "%-6s žádný publikovaný příspěvek k ověření\n", $type );
		continue;
	}

	$id  = $posts[0]->ID;
	$has = metadata_exists( 'post', $id, \Kct\Facebook\ShareState::META_SHARE );

	printf(
		"%-6s #%d  vlastní přepínač: %-3s  should_share: %s\n",
		$type,
		$id,
		$has ? 'ano' : 'ne',
		$state->should_share( $id, $c->share_default_for( $type ) ) ? 'ano' : 'ne'
	);
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test eval-file defaultstest.php && rm defaultstest.php
```

Očekávaný výstup: u obou typů `vypnuto` (nastavení není vyplněné) a u
příspěvků bez vlastního přepínače `should_share: ne`.

- [ ] **Step 2: Ověř řádek do metaboxu na skutečných akcích**

Zapiš do `/Users/martin/Sites/sokct/notetest.php`:

```php
<?php
$share = kct_container()->get( \Kct\Features\FacebookShare::class );

foreach ( get_posts( array( 'post_type' => 'akce', 'numberposts' => 5, 'post_status' => 'publish' ) ) as $post ) {
	printf( "%-42s %s\n", mb_substr( $post->post_title, 0, 40 ), $share->schedule_note( $post ) ?: '(bez řádku)' );
}

$news = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
if ( $news ) {
	printf( "%-42s %s\n", 'AKTUALITA — nesmí mít řádek', $share->schedule_note( $news[0] ) ?: '(bez řádku, správně)' );
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file notetest.php && rm notetest.php
```

Očekávaný výstup: u akcí buď `Odešle se …`, nebo `Akce už proběhla, na
Facebook se neodešle.` podle jejich data; u aktuality `(bez řádku, správně)`.

- [ ] **Step 3: Ověř, že se nerozbil Open Graph**

```bash
cd /Users/martin/Sites/sokct
A=$(ddev wp --url=https://sokct.test post list --post_type=akce --post_status=publish --posts_per_page=1 --field=url)
P=$(ddev wp --url=https://sokct.test post list --post_type=post --post_status=publish --posts_per_page=1 --field=url)
for u in "$A" "$P"; do curl -sk "$u" | grep -oE '<meta property="og:image(:width|:height)?"[^>]*>' | sed 's/^/  /'; done
```

Očekávaný výstup: u obou `og:image` mířící na `kct-og/…png`, plus width 1200
a height 630 — tedy stejně jako před tímhle úkolem.

- [ ] **Step 4: Ověř, že nastavení jde uložit**

Otevři v prohlížeči Nastavení → KČT a zkontroluj, že sekce Facebook obsahuje:
dvě samostatná zaškrtávátka („Sdílet automaticky — aktuality" a „— akce"),
pole „Kolik dní před akcí odeslat" a „V kolik hodin odeslat", a **že tam už
není** „Výchozí náhledový obrázek".

Nastavení neukládej — zápis do databáze je na Martinovi. Stačí, že se stránka
vykreslí bez chyby a pole tam jsou.

---

## Poznámky k údržbě

**Odstup 12 dní není kulaté číslo, ale volba podle dne v týdnu.** Sobotní akce
tím vyjde na pondělí, nedělní na úterý. Kdo ho mění, mění den v týdnu, na
který odeslání padne — zdůvodnění je u konstanty `ShareSchedule::DEFAULT_LEAD_DAYS`.

**Naplánované odeslání může viset měsíce.** Akce publikovaná půl roku dopředu
má cron událost naplánovanou na dobu o pět a půl měsíce později. Je to
v pořádku: `share()` si při spuštění všechno znovu ověří, takže smazaný,
odpublikovaný nebo mezitím odeslaný příspěvek se neodešle.

**Přeplánování se pouští při každém uložení akce**, dokud není odeslaná.
Naplánuje běh za minutu, který rozhodne znovu. Nekonečné smyčce to nehrozí —
jakmile cílový čas nastane, není už v budoucnu a odešle se.
