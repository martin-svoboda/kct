# Sdílení na Facebook fotkou + karta 4:5 — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-31-sdileni-fotkou-design.md`](../specs/2026-08-31-sdileni-fotkou-design.md)

**Cíl:** Automatické sdílení na Facebook posílat jako fotku místo odkazu a
vyrobit pro to samostatnou kartu 1080×1350 (JPEG) — u příspěvku fotografickou,
u akce čistě grafickou s délkami tras.

**Architektura:** `OgImageRenderer` se zbaví pevných rozměrů, takže z něj mohou
kreslit karty obou formátů. Karty dostanou společné rozhraní `Card`, díky
kterému `OgImageService` neví, kterou zrovna kreslí. Přibývají dvě karty na
výšku a třída `Waymark` na kresbu turistické značky. Na straně Facebooku
přibývá druhá publikační metoda a `FacebookShare` si vybírá mezi fotkou a
odkazem.

**Tech stack:** PHP 8.0+, WordPress multisite, Imagick (ImageMagick 6.9.11-60),
PHP-DI (`config.php`), Facebook Graph API v21.0, WP-CLI.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám. Každý úkol proto
končí ověřením, ne commitem. Platí to i tam, kde to skill říká jinak.

**V projektu není PHPUnit** a nezavádí se kvůli tomuto plánu. Ověřuje se přes
`php -l`, `ddev wp eval-file` a prohlížení vyrenderovaných obrázků.

**Nikdy nezapisuj do databáze kvůli ověření.** Renderovat obrázky na disk je
v pořádku; `update_option`, `wp_insert_post` a editace příspěvků ne.

**Nikdy neodesílej nic na Facebook.** Je to zápis na veřejný profil. Ruční
odeslání spouští jen Martin, viz Task 10.

**Příkazy** se pouští z kořene projektu `/Users/martin/Sites/sokct`. Cesty
v plánu jsou relativní ke kořeni pluginu
`/Users/martin/Sites/sokct/wp-content/plugins/kct`.

Víceřádkový PHP kód nedávej do `ddev wp eval` — shell escapování ho rozbije.
Zapiš ho do souboru v kořeni **projektu** (ne pluginu) a pusť
`ddev wp --url=https://sokct.test eval-file <soubor>`, pak soubor smaž.

**Po každé změně tříd smaž zkompilovaný kontejner**, jinak PHP-DI nové ani
změněné třídy nenajde:

```bash
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

**Po změně vzhledu zvyš `OgImageRenderer::RENDER_VERSION`**, jinak se budou dál
servírovat staré soubory — název souboru nese hash, do kterého ta konstanta
vstupuje. Aktuální hodnota je 8.

**Žádné SVG.** Produkce nemá v Imagicku SVG delegáta (ověřeno: DDEV `SVG: ano`,
produkce `SVG: NE`). Vše se kreslí přes `ImagickDraw` nebo z PNG.

---

## Struktura souborů

| Soubor | Odpovědnost |
|---|---|
| `src/Og/Card.php` | **nový** — rozhraní karty: vykreslení, rozměry, přípona. |
| `src/Og/Waymark.php` | **nový** — kresba turistické značky jako motivu pozadí. |
| `src/Og/PostPosterCard.php` | **nový** — karta příspěvku 1080×1350. |
| `src/Og/EventPosterCard.php` | **nový** — karta akce 1080×1350, bez fotky. |
| `src/Og/OgImageRenderer.php` | zbavit pevných rozměrů, přidat výstup do JPEG. |
| `src/Og/OgImageStore.php` | přípona souboru jako parametr. |
| `src/Og/OgImageService.php` | pracovat přes rozhraní `Card`, přidat metody pro plakáty. |
| `src/Og/PostCard.php` | vlastní konstanty rozměrů, implementovat `Card`. |
| `src/Og/EventCard.php` | totéž. |
| `src/Features/OgImages.php` | data pro plakáty, řádky s délkami tras. |
| `src/Facebook/GraphClient.php` | `publish_photo()`. |
| `src/Facebook/MessageComposer.php` | odkaz na konec textu. |
| `src/Features/FacebookShare.php` | vybrat fotku, jinak spadnout na odkaz. |
| `src/CLI.php` | totéž v příkazu `fb_share`. |

---

## Task 1: Rozhraní Card a rozměry v kartách

**Files:**
- Create: `src/Og/Card.php`
- Modify: `src/Og/PostCard.php`
- Modify: `src/Og/EventCard.php`

Dnes drží rozměry `OgImageRenderer` v konstantách `WIDTH` a `HEIGHT`. Karty na
výšku je mít nemohou stejné, takže se rozměry stěhují do karet. Rozhraní zajistí,
že se `OgImageService` nemusí ptát, kterou kartu kreslí.

- [ ] **Step 1: Vytvoř rozhraní**

Soubor `src/Og/Card.php`:

```php
<?php

namespace Kct\Og;

/**
 * Jedno rozvržení sdílecího obrázku.
 *
 * Karta zná svoje rozměry a formát, ale neví, kam se výsledek uloží ani jak
 * se klíčuje — to je věc OgImageService a OgImageStore.
 */
interface Card {

	/**
	 * Vykreslí kartu a vrátí binární obsah obrázku.
	 *
	 * @param array $data Data karty; tvar popisuje docblock konkrétní karty.
	 */
	public function render( array $data ): string;

	public function width(): int;

	public function height(): int;

	/** Přípona souboru bez tečky, tedy `png` nebo `jpg`. */
	public function extension(): string;
}
```

- [ ] **Step 2: Přidej rozměry a rozhraní do PostCard**

V `src/Og/PostCard.php` změň hlavičku třídy a přidej metody. Nahraď řádek
`class PostCard {` a bezprostředně následující konstanty tímto:

```php
class PostCard implements Card {

	public const WIDTH  = 1200;
	public const HEIGHT = 630;

	private const TITLE_SIZE   = 62;
```

(zbytek konstant nech beze změny) a na konec třídy, před uzavírací závorku,
přidej:

```php
	public function width(): int {
		return self::WIDTH;
	}

	public function height(): int {
		return self::HEIGHT;
	}

	public function extension(): string {
		return 'png';
	}
```

- [ ] **Step 3: Nahraď v PostCard odkazy na rozměry rendereru**

V `src/Og/PostCard.php` nahraď `OgImageRenderer::WIDTH` za `self::WIDTH`
a `OgImageRenderer::HEIGHT` za `self::HEIGHT`. Jde o 5 výskytů:

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
sed -i '' -e 's/OgImageRenderer::WIDTH/self::WIDTH/g' -e 's/OgImageRenderer::HEIGHT/self::HEIGHT/g' src/Og/PostCard.php
grep -c 'OgImageRenderer::WIDTH\|OgImageRenderer::HEIGHT' src/Og/PostCard.php
```

Očekávaný výstup: `0`

- [ ] **Step 4: Totéž pro EventCard**

V `src/Og/EventCard.php` změň `class EventCard {` na `class EventCard implements Card {`
a hned pod to přidej:

```php
	public const WIDTH  = 1200;
	public const HEIGHT = 630;
```

Na konec třídy přidej:

```php
	public function width(): int {
		return self::WIDTH;
	}

	public function height(): int {
		return self::HEIGHT;
	}

	public function extension(): string {
		return 'png';
	}
```

A nahraď odkazy:

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
sed -i '' -e 's/OgImageRenderer::WIDTH/self::WIDTH/g' -e 's/OgImageRenderer::HEIGHT/self::HEIGHT/g' src/Og/EventCard.php
grep -c 'OgImageRenderer::WIDTH\|OgImageRenderer::HEIGHT' src/Og/EventCard.php
```

Očekávaný výstup: `0`

- [ ] **Step 5: Oprav volání canvas() a photo() v obou kartách**

Task 2 mění signatury `canvas()` a `photo()` tak, že berou rozměry. Volání
v obou stávajících kartách se musí opravit, jinak spadnou na
`ArgumentCountError` při prvním vykreslení — `php -l` to nezachytí, protože
je to chyba za běhu, ne syntaktická.

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
sed -i '' \
 -e "s/\$r->photo( \$data\['photo'\] )/\$r->photo( \$data['photo'], self::WIDTH, self::HEIGHT )/" \
 -e "s/\$r->canvas( OgImageRenderer::INK )/\$r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK )/" \
 src/Og/PostCard.php
sed -i '' \
 -e "s/\$r->photo( \$photo )/\$r->photo( \$photo, self::WIDTH, self::HEIGHT )/" \
 -e "s/\$r->canvas( OgImageRenderer::INK )/\$r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK )/" \
 src/Og/EventCard.php
grep -n 'r->canvas(\|r->photo(' src/Og/PostCard.php src/Og/EventCard.php
```

Očekávaný výstup: čtyři řádky, v každém `self::WIDTH, self::HEIGHT`.

- [ ] **Step 6: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Og/Card.php && php -l src/Og/PostCard.php && php -l src/Og/EventCard.php
```

Očekávaný výstup: třikrát `No syntax errors detected`

---

## Task 2: Renderer nezávislý na rozměru

**Files:**
- Modify: `src/Og/OgImageRenderer.php`

Metody, které dnes počítají s 1200×630, musí rozměry buď dostat, nebo si je
přečíst z plátna. Přibývá výstup do JPEG.

- [ ] **Step 1: Odstraň konstanty rozměrů**

V `src/Og/OgImageRenderer.php` smaž tyto dva řádky:

```php
	public const WIDTH  = 1200;
	public const HEIGHT = 630;
```

Zbylé konstanty (`PAD`, `STRIP`, `LOGO_HEIGHT`, `TOP`, barvy, písma) nech být —
platí pro oba formáty. `PAD` 48 px odpovídá 4 % šířky u 1200 i u 1080.

- [ ] **Step 2: Nahraď canvas()**

```php
	/**
	 * Prázdné plátno dané velikosti.
	 *
	 * Rozměry se předávají, protože každá karta má jiné; renderer je nezná.
	 */
	public function canvas( int $width, int $height, string $color = self::INK ): Imagick {
		$canvas = new Imagick();
		$canvas->newImage( $width, $height, new ImagickPixel( $color ) );
		$canvas->setImageFormat( 'png' );

		return $canvas;
	}
```

- [ ] **Step 3: Nahraď gradient()**

```php
	/**
	 * Svislý přechod složený přes plátno.
	 *
	 * Šířku si bere z plátna — přechod se vždycky táhne přes celou kartu,
	 * takže není důvod ji předávat zvlášť.
	 *
	 * Barvy se předávají jako řetězce pro ImageMagick, takže jde použít
	 * i `transparent` nebo `rgba(13,25,38,0.92)`.
	 */
	public function gradient( Imagick $canvas, string $from, string $to, int $height, int $top ): void {
		$gradient = new Imagick();
		$gradient->newPseudoImage( $canvas->getImageWidth(), $height, sprintf( 'gradient:%s-%s', $from, $to ) );
		$canvas->compositeImage( $gradient, Imagick::COMPOSITE_OVER, 0, $top );
		$gradient->clear();
	}
```

- [ ] **Step 4: Nahraď photo()**

```php
	/**
	 * Fotka načtená a oříznutá na daný rozměr, nebo null.
	 *
	 * flattenImages() srovná průhlednost na tmavé pozadí, ať se z PNG s alfa
	 * kanálem nestane bílá díra. cropThumbnailImage() zachová poměr a ořízne
	 * přetékající stranu, tedy chová se jako `object-fit: cover`.
	 */
	public function photo( string $path, int $width, int $height ): ?Imagick {
		try {
			$image = new Imagick( $path );
			$image->setImageBackgroundColor( new ImagickPixel( self::INK ) );
			$image = $image->flattenImages();
			$image->cropThumbnailImage( $width, $height );
			$image->setImageFormat( 'png' );

			return $image;
		} catch ( Throwable $e ) {
			return null;
		}
	}
```

- [ ] **Step 5: Nahraď strip()**

```php
	/**
	 * Trikolóra u dolní hrany.
	 *
	 * Mixin kct-strip kreslí modrý pás přes celou šířku a přes něj trikolóru
	 * širokou min(100%, 1440px). Ani u jednoho našeho formátu trikolóra tu
	 * mez nepřekročí, takže zabere celou šířku a modrý pás pod ní není vidět;
	 * kreslí se proto rovnou jen ona.
	 */
	public function strip( Imagick $canvas ): void {
		$width = $canvas->getImageWidth();
		$y     = $canvas->getImageHeight() - self::STRIP;
		$third = (int) round( $width / 3 );

		$this->rect( $canvas, 0, $y, $third, self::STRIP, self::RED );
		$this->rect( $canvas, $third, $y, $third, self::STRIP, self::GREEN );
		$this->rect( $canvas, 2 * $third, $y, $width - 2 * $third, self::STRIP, self::YELLOW );
	}
```

- [ ] **Step 6: Oprav umístění loga**

V metodě `logo()` nahraď řádek s `compositeImage`:

```php
			$canvas->compositeImage( $logo, Imagick::COMPOSITE_OVER, $canvas->getImageWidth() - self::PAD - $w, self::TOP );
```

- [ ] **Step 7: Přidej výstup do JPEG**

Za metodu `png()` přidej:

```php
	/**
	 * Vrátí JPEG blob a plátno uvolní.
	 *
	 * JPEG nezná průhlednost, takže se plátno nejdřív srovná na bílou. Naše
	 * plátna jsou neprůhledná (vznikají z canvas() s plnou barvou nebo
	 * z photo()), ale spolehnout se na to by znamenalo, že první karta
	 * s průhledným místem vyjde s černým flekem.
	 *
	 * @param int $quality Kvalita 1–100.
	 */
	public function jpeg( Imagick $canvas, int $quality ): string {
		$canvas->setImageBackgroundColor( new ImagickPixel( self::WHITE ) );
		$flat = $canvas->flattenImages();
		$flat->setImageFormat( 'jpeg' );
		$flat->setImageCompressionQuality( $quality );

		$blob = $flat->getImageBlob();

		$flat->clear();
		$canvas->clear();

		return $blob;
	}
```

- [ ] **Step 8: Ověř syntaxi a že po rendereru nezůstaly odkazy na rozměry**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Og/OgImageRenderer.php
grep -rn 'OgImageRenderer::WIDTH\|OgImageRenderer::HEIGHT' src/ || echo "žádné zbylé odkazy"
```

Očekávaný výstup: `No syntax errors detected`, pak výpis z `OgImageService.php`
(ten se opraví v Tasku 3) — jinde nic.

---

## Task 3: Store s příponou a služba přes rozhraní

**Files:**
- Modify: `src/Og/OgImageStore.php`
- Modify: `src/Og/OgImageService.php`

- [ ] **Step 1: Přidej příponu do OgImageStore**

Nahraď metody `filename()`, `url()`, `save()` a `prune()`:

```php
	public function filename( string $prefix, string $hash, string $ext = 'png' ): string {
		return $prefix . '-' . $hash . '.' . $ext;
	}

	/** URL hotového obrázku, nebo null když soubor neexistuje. */
	public function url( string $prefix, string $hash, string $ext = 'png' ): ?string {
		$name = $this->filename( $prefix, $hash, $ext );

		return file_exists( $this->dir() . '/' . $name ) ? $this->base_url() . '/' . $name : null;
	}

	/**
	 * Uloží obrázek a vrátí jeho URL, nebo null když se zapsat nepovedlo.
	 *
	 * Zapisuje se do dočasného souboru a přejmenovává až po úspěchu. Přímý
	 * zápis by při přerušení nechal na disku useknutý soubor, který by se
	 * tvářil jako hotový — a protože se existence souboru bere jako „hotovo",
	 * už by se nikdy nepřegeneroval.
	 */
	public function save( string $blob, string $prefix, string $hash, string $ext = 'png' ): ?string {
		$dir = $this->dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$name  = $this->filename( $prefix, $hash, $ext );
		$final = $dir . '/' . $name;
		$tmp   = $final . '.tmp';

		if ( false === file_put_contents( $tmp, $blob ) ) {
			return null;
		}

		if ( ! rename( $tmp, $final ) ) {
			wp_delete_file( $tmp );

			return null;
		}

		$this->prune( $prefix, $name, $ext );

		return $this->base_url() . '/' . $name;
	}

	/**
	 * Smaže starší verze téhož objektu v témž formátu.
	 *
	 * Předpony jsou `post-12`, `akce-12`, `akce-db-345` a u plakátů
	 * `social-post-12`; maska za nimi má pomlčku, takže `post-1-*` nechytne
	 * `post-12-*` a `akce-12-*` nechytne `akce-db-12-*`. Přípona v masce navíc
	 * zajistí, že úklid plakátu nesmaže kartu na šířku a naopak.
	 */
	private function prune( string $prefix, string $keep, string $ext ): void {
		foreach ( (array) glob( $this->dir() . '/' . $prefix . '-*.' . $ext ) as $file ) {
			if ( basename( $file ) !== $keep ) {
				wp_delete_file( $file );
			}
		}
	}
```

- [ ] **Step 2: Přepiš OgImageService**

Celý soubor `src/Og/OgImageService.php`:

```php
<?php

namespace Kct\Og;

use Throwable;

/**
 * Spojuje dohromady klíčování, úložiště a kreslení.
 *
 * Volající předá hotová data karty a předponu názvu souboru; služba spočítá
 * hash, a když soubor s tím hashem existuje, vrátí ho bez kreslení. Jinak
 * kartu vyrenderuje a uloží.
 *
 * Kterou kartu kreslí, řeší jen výběr v public metodách — zbytek jede přes
 * rozhraní Card, takže přidání dalšího formátu se téhle třídy skoro nedotkne.
 *
 * Vrací null, kdykoli se cokoli nepovede — volající pak spadne na dnešní
 * chování. Sdílecí obrázek je ozdoba, ne funkce, a nesmí nic shodit.
 */
class OgImageService {

	public function __construct(
		private OgImageRenderer $renderer,
		private OgImageStore $store,
		private PostCard $post_card,
		private EventCard $event_card,
		private PostPosterCard $post_poster,
		private EventPosterCard $event_poster
	) {
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function post( array $data, string $prefix ): ?array {
		return $this->image( $this->post_card, $data, $prefix );
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function event( array $data, string $prefix ): ?array {
		return $this->image( $this->event_card, $data, $prefix );
	}

	/**
	 * Karta 4:5 příspěvku pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_post( array $data, string $prefix ): ?array {
		return $this->image( $this->post_poster, $data, 'social-' . $prefix );
	}

	/**
	 * Karta 4:5 akce pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_event( array $data, string $prefix ): ?array {
		return $this->image( $this->event_poster, $data, 'social-' . $prefix );
	}

	/**
	 * @return array{url: string, width: int, height: int}|null
	 */
	private function image( Card $card, array $data, string $prefix ): ?array {
		if ( ! $this->renderer->available() ) {
			return null;
		}

		$ext  = $card->extension();
		$hash = $this->hash( $data, $card );
		$url  = $this->store->url( $prefix, $hash, $ext );

		if ( null === $url ) {
			try {
				$url = $this->store->save( $card->render( $data ), $prefix, $hash, $ext );
			} catch ( Throwable $e ) {
				// Chyba kreslení nesmí shodit stránku. Zaloguje se a volající
				// spadne na dnešní obrázek.
				error_log( sprintf( 'kct: sdílecí obrázek %s selhal: %s', $prefix, $e->getMessage() ) );

				return null;
			}
		}

		if ( null === $url ) {
			return null;
		}

		return array(
			'url'    => $url,
			'width'  => $card->width(),
			'height' => $card->height(),
		);
	}

	/**
	 * Otisk vstupů karty.
	 *
	 * Do hashe jde všechno, co se kreslí, plus rozměry karty a verze
	 * vykreslování. Změna obsahu i změna designu tak vyrobí jiný název
	 * souboru — starý se smaže a Facebook si sáhne pro nový, protože se změní
	 * i URL v og:image.
	 */
	private function hash( array $data, Card $card ): string {
		$key = wp_json_encode( $data ) . '|' . $card->width() . 'x' . $card->height()
			. '|v' . OgImageRenderer::RENDER_VERSION;

		return substr( sha1( $key ), 0, 12 );
	}
}
```

- [ ] **Step 3: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Og/OgImageStore.php && php -l src/Og/OgImageService.php
```

Očekávaný výstup: dvakrát `No syntax errors detected`

Kontejner teď ještě nepůjde sestavit — `PostPosterCard` a `EventPosterCard`
zatím neexistují. Vzniknou v Tasku 5 a 6.

---

## Task 4: Waymark — turistická značka jako motiv

**Files:**
- Create: `src/Og/Waymark.php`

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;

/**
 * Turistická značka jako grafický motiv pozadí.
 *
 * Značka KČT je čtverec se třemi vodorovnými pruhy: bílý, barevný, bílý.
 * Kreslí se tedy třemi obdélníky a nepotřebuje ani SVG, ani přiložený soubor —
 * což je zásadní, protože Imagick na produkci SVG delegáta nemá.
 *
 * Barva se odvozuje od měsíce, takže se karty mezi sebou liší, aniž by se
 * cokoli nastavovalo, a přitom je výsledek pro danou akci vždycky stejný.
 */
class Waymark {

	/** Barvy značených tras KČT v pořadí, ve kterém se střídají. */
	private const COLORS = array(
		OgImageRenderer::RED,
		OgImageRenderer::BLUE,
		OgImageRenderer::GREEN,
		OgImageRenderer::YELLOW,
	);

	public function __construct( private OgImageRenderer $renderer ) {
	}

	/**
	 * Barva značky pro daný měsíc (1–12).
	 *
	 * Mimo rozsah spadne na červenou — data z importu občas mají prázdné
	 * datum a lepší je nakreslit značku špatnou barvou než žádnou.
	 */
	public function color( int $month ): string {
		if ( $month < 1 || $month > 12 ) {
			return self::COLORS[0];
		}

		return self::COLORS[ ( $month - 1 ) % count( self::COLORS ) ];
	}

	/**
	 * Nakreslí jednu značku.
	 *
	 * Pruhy jsou třetinové, jako na skutečné značce. Průhlednost se řeší tím,
	 * že se předají rgba barvy — plátno se skládá přes COMPOSITE_OVER, takže
	 * není potřeba sahat na alfa kanál zvlášť.
	 *
	 * Souřadnice smí být i záporné nebo za hranou plátna; obdélníky se pak
	 * jen ořežou a značka vykoukne z okraje, což je u motivu žádoucí.
	 *
	 * @param int    $size    Strana čtverce.
	 * @param string $band    Barva prostředního pruhu jako rgba řetězec.
	 * @param string $outer   Barva krajních pruhů jako rgba řetězec.
	 */
	public function draw( Imagick $canvas, int $x, int $y, int $size, string $band, string $outer ): void {
		$third = (int) round( $size / 3 );

		$this->renderer->rect( $canvas, $x, $y, $size, $third, $outer );
		$this->renderer->rect( $canvas, $x, $y + $third, $size, $third, $band );
		$this->renderer->rect( $canvas, $x, $y + 2 * $third, $size, $size - 2 * $third, $outer );
	}

	/**
	 * Rozloží značky po ploše jako motiv pozadí.
	 *
	 * Dvě značky, obě částečně za hranou plátna — vlevo nahoře a vpravo níž.
	 * Nízký kontrast, aby nekonkurovaly textu.
	 *
	 * @param int    $month Měsíc konání pro odvození barvy.
	 * @param int    $area  Výška plochy, po které se motiv rozkládá.
	 */
	public function motif( Imagick $canvas, int $month, int $area ): void {
		$color = $this->color( $month );
		$band  = $this->rgba( $color, 0.16 );
		$outer = 'rgba(255,255,255,0.05)';
		$size  = (int) round( $canvas->getImageWidth() * 0.30 );

		$this->draw( $canvas, -(int) round( $size * 0.35 ), (int) round( $area * 0.16 ), $size, $band, $outer );
		$this->draw( $canvas, $canvas->getImageWidth() - (int) round( $size * 0.55 ), (int) round( $area * 0.55 ), $size, $band, $outer );
	}

	/** Převede #rrggbb na rgba() s danou průhledností. */
	private function rgba( string $hex, float $alpha ): string {
		$hex = ltrim( $hex, '#' );

		return sprintf(
			'rgba(%d,%d,%d,%.2f)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			$alpha
		);
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/Waymark.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/Waymark.php`

---

## Task 5: PostPosterCard — karta příspěvku 4:5

**Files:**
- Create: `src/Og/PostPosterCard.php`

Fotka v horních dvou třetinách, světlý panel dole s kategorií, titulkem a
datem.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;

/**
 * Sdílecí karta příspěvku 1080×1350 pro sdílení fotkou.
 *
 * Fotka nahoře, světlý panel dole. Na rozdíl od karty 1200×630, kde text leží
 * v gradientu přímo na fotce, tady má panel vlastní plochu — na výšku je na to
 * místo a text je na světlém pozadí čitelnější.
 */
class PostPosterCard implements Card {

	public const WIDTH  = 1080;
	public const HEIGHT = 1350;

	/** Svislá hranice mezi fotkou a panelem. */
	private const PANEL_TOP = 810;

	private const QUALITY = 88;

	private const CHIP_SIZE   = 26;
	private const CHIP_HEIGHT = 46;
	private const CHIP_PAD    = 16;

	private const TITLE_SIZE    = 60;
	private const TITLE_LEADING = 70;
	private const TITLE_LINES   = 3;

	private const META_SIZE = 28;

	public function __construct(
		private OgImageRenderer $renderer,
		private Waymark $waymark
	) {
	}

	public function width(): int {
		return self::WIDTH;
	}

	public function height(): int {
		return self::HEIGHT;
	}

	public function extension(): string {
		return 'jpg';
	}

	/**
	 * @param array{title: string, category: string, meta: string, photo: string, logo: string, month: int} $data
	 *
	 * @return string JPEG blob.
	 */
	public function render( array $data ): string {
		$r = $this->renderer;

		$canvas = $r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK );

		$photo = '' !== $data['photo'] ? $r->photo( $data['photo'], self::WIDTH, self::PANEL_TOP ) : null;

		if ( $photo ) {
			$canvas->compositeImage( $photo, Imagick::COMPOSITE_OVER, 0, 0 );
			$photo->clear();
		} else {
			// Bez náhledu stejné grafické pozadí jako u akce, ať karta nikdy
			// nevyjde jako prázdná plocha.
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::PANEL_TOP, 0 );
			$this->waymark->motif( $canvas, $data['month'], self::PANEL_TOP );
		}

		// Ztmavení pod logem, aby drželo i na světlé fotce.
		$r->gradient( $canvas, 'rgba(13,25,38,0.72)', 'transparent', 200, 0 );
		$r->logo( $canvas, $data['logo'] );

		$r->rect( $canvas, 0, self::PANEL_TOP, self::WIDTH, self::HEIGHT - self::PANEL_TOP, OgImageRenderer::WHITE );

		$x   = OgImageRenderer::PAD;
		$max = self::WIDTH - 2 * OgImageRenderer::PAD;

		$top = self::PANEL_TOP + 56;

		if ( '' !== $data['category'] ) {
			$label = $r->truncate( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $data['category'], 460 );
			$width = $r->width( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $label );

			$chip = $r->rounded_rect( $width + 2 * self::CHIP_PAD, self::CHIP_HEIGHT, 6, OgImageRenderer::BLUE );
			$canvas->compositeImage( $chip, Imagick::COMPOSITE_OVER, $x, $top );
			$chip->clear();

			$r->text( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, OgImageRenderer::WHITE, $x + self::CHIP_PAD, $top + 32, $label );

			$top += self::CHIP_HEIGHT + 34;
		}

		foreach ( $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES ) as $i => $line ) {
			$r->text( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, OgImageRenderer::TEXT, $x, $top + 52 + self::TITLE_LEADING * $i, $line );
		}

		// Meta řádek se kotví ke spodní hraně, ne pod titulek — jinak by
		// u jednořádkového titulku zůstal viset uprostřed panelu.
		$r->text(
			$canvas,
			OgImageRenderer::BODY_MEDIUM,
			self::META_SIZE,
			OgImageRenderer::MUTED,
			$x,
			self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP,
			$r->truncate( $canvas, OgImageRenderer::BODY_MEDIUM, self::META_SIZE, $data['meta'], $max )
		);

		$r->strip( $canvas );

		return $r->jpeg( $canvas, self::QUALITY );
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/PostPosterCard.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/PostPosterCard.php`

---

## Task 6: EventPosterCard — karta akce 4:5

**Files:**
- Create: `src/Og/EventPosterCard.php`

Grafická karta bez fotky. Nahoře ikony všech typů a logo, uprostřed motiv
značky, nad panelem datumová kartička s titulkem, v panelu údaje a délky tras.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;

/**
 * Sdílecí karta akce 1080×1350 pro sdílení fotkou.
 *
 * Fotku nepoužívá vůbec, ani když ji akce má. Z 318 importovaných akcí má
 * obrázek 6 a ani u těch není jisté, že se k akci vztahuje — bývá to plakát
 * nebo loňský snímek. Bez fotky vypadá každá akce stejně a vždycky správně.
 */
class EventPosterCard implements Card {

	public const WIDTH  = 1080;
	public const HEIGHT = 1350;

	/** Svislá hranice mezi grafickou plochou a světlým panelem. */
	private const PANEL_TOP = 810;

	private const QUALITY = 88;

	private const TITLE_SIZE    = 62;
	private const TITLE_LEADING = 72;
	private const TITLE_LINES   = 2;
	private const TITLE_DESCENT = 16;

	private const EYEBROW_SIZE = 27;
	private const EYEBROW_GAP  = 70;

	/** Mezera mezi spodní hranou bloku s titulkem a horní hranou panelu. */
	private const BLOCK_GAP = 46;

	private const BADGE_W       = 128;
	private const BADGE_W_RANGE = 190;
	private const BADGE_H       = 156;
	private const BADGE_HEAD_H  = 46;
	private const BADGE_RADIUS  = 14;
	private const BADGE_GAP     = 30;

	private const ICON_SIZE = 36;
	private const ICON_PAD  = 8;
	private const ICON_GAP  = 6;
	private const ICON_MAX  = 4;

	private const LABEL_SIZE = 22;
	private const VALUE_SIZE = 32;
	private const NOTE_SIZE  = 26;

	/** Šířka sloupce s popiskem v panelu; hodnota začíná za ním. */
	private const LABEL_COLUMN = 230;

	private const ROUTE_SIZE = 28;
	private const ROUTE_ICON = 40;
	private const ROUTE_STEP = 52;
	private const ROUTE_MAX  = 3;

	public function __construct(
		private OgImageRenderer $renderer,
		private Waymark $waymark
	) {
	}

	public function width(): int {
		return self::WIDTH;
	}

	public function height(): int {
		return self::HEIGHT;
	}

	public function extension(): string {
		return 'jpg';
	}

	/**
	 * @param array{
	 *     title: string,
	 *     eyebrow: string,
	 *     month: int,
	 *     date: array{head: string, day: string, month: string, end_day: string, end_month: string, is_range: bool},
	 *     rows: array<int, array{label: string, value: string, note: string}>,
	 *     routes: array<int, array{icon: string, text: string}>,
	 *     icons: string[],
	 *     logo: string
	 * } $data
	 *
	 * @return string JPEG blob.
	 */
	public function render( array $data ): string {
		$r = $this->renderer;

		$canvas = $r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK );
		$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::PANEL_TOP, 0 );
		$this->waymark->motif( $canvas, $data['month'], self::PANEL_TOP );

		$r->logo( $canvas, $data['logo'] );
		$this->draw_icons( $canvas, $data['icons'] );

		$badge_w = ! empty( $data['date']['is_range'] ) ? self::BADGE_W_RANGE : self::BADGE_W;
		$x       = OgImageRenderer::PAD + $badge_w + self::BADGE_GAP;
		$max     = self::WIDTH - $x - OgImageRenderer::PAD;

		$lines   = $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES );
		$eyebrow = $r->truncate( $canvas, OgImageRenderer::BODY_SEMI, self::EYEBROW_SIZE, $data['eyebrow'], $max );

		// Blok s datem a titulkem se kotví zespodu, těsně nad panel — stejně
		// jako na kartě 1200×630 nad linkou datového pásu.
		$bottom = self::PANEL_TOP - self::BLOCK_GAP;

		$this->draw_badge( $canvas, $data['date'], $badge_w, $bottom - self::BADGE_H );
		$this->draw_title( $canvas, $eyebrow, $lines, $x, $bottom );

		$r->rect( $canvas, 0, self::PANEL_TOP, self::WIDTH, self::HEIGHT - self::PANEL_TOP, OgImageRenderer::WHITE );

		$this->draw_rows( $canvas, $data['rows'] );
		$this->draw_routes( $canvas, $data['routes'] );

		$r->strip( $canvas );

		return $r->jpeg( $canvas, self::QUALITY );
	}

	/**
	 * Ikony typů akce v bílém zaobleném bloku vlevo nahoře.
	 *
	 * Piktogramy z centrální databáze jsou černá kresba na bílé bez alfa
	 * kanálu, takže bílý blok pod nimi není jen ozdoba — bez něj by na tmavém
	 * pozadí zůstal kolem každé ikony bílý čtverec.
	 *
	 * @param string[] $icons Cesty k místním kopiím ikon (viz IconCache).
	 */
	private function draw_icons( Imagick $canvas, array $icons ): void {
		$r      = $this->renderer;
		$loaded = array();

		foreach ( array_slice( $icons, 0, self::ICON_MAX ) as $path ) {
			$icon = $r->icon( $path, self::ICON_SIZE );

			if ( $icon ) {
				$loaded[] = $icon;
			}
		}

		if ( empty( $loaded ) ) {
			return;
		}

		$count = count( $loaded );
		$w     = self::ICON_PAD * 2 + $count * self::ICON_SIZE + ( $count - 1 ) * self::ICON_GAP;
		$h     = self::ICON_PAD * 2 + self::ICON_SIZE;

		$block = $r->rounded_rect( $w, $h, 8, 'rgba(255,255,255,0.96)' );
		$x     = self::ICON_PAD;

		foreach ( $loaded as $icon ) {
			$block->compositeImage(
				$icon,
				Imagick::COMPOSITE_OVER,
				$x + (int) ( ( self::ICON_SIZE - $icon->getImageWidth() ) / 2 ),
				self::ICON_PAD + (int) ( ( self::ICON_SIZE - $icon->getImageHeight() ) / 2 )
			);
			$icon->clear();
			$x += self::ICON_SIZE + self::ICON_GAP;
		}

		$canvas->compositeImage( $block, Imagick::COMPOSITE_OVER, OgImageRenderer::PAD, OgImageRenderer::TOP );
		$block->clear();
	}

	/**
	 * Datumová kartička podle komponenty .event-date z core/blocks/events.scss.
	 *
	 * @param array $date Části data z kct_format_event_date().
	 * @param int   $w    Šířka kartičky; spočítal ji volající, protože z ní
	 *                    vychází i levá hrana titulku.
	 * @param int   $top  Horní hrana — blok se kotví zespodu, viz render().
	 */
	private function draw_badge( Imagick $canvas, array $date, int $w, int $top ): void {
		$r     = $this->renderer;
		$range = ! empty( $date['is_range'] );

		$badge = $r->rounded_rect( $w, self::BADGE_H, self::BADGE_RADIUS, OgImageRenderer::WHITE, OgImageRenderer::LINE, 2 );

		// Modrá hlavička. Kreslí se jako zaoblený obdélník posunutý nahoru
		// a přiříznutý, takže zaoblené jsou jen horní rohy — stejně jako
		// .event-date__head s overflow: hidden na rodiči.
		$head = $r->rounded_rect( $w - 4, self::BADGE_HEAD_H + self::BADGE_RADIUS, self::BADGE_RADIUS, OgImageRenderer::BLUE );
		$head->cropImage( $w - 4, self::BADGE_HEAD_H - 2, 0, 0 );
		$badge->compositeImage( $head, Imagick::COMPOSITE_OVER, 2, 2 );
		$head->clear();

		$r->text( $badge, OgImageRenderer::HEAD_BOLD, 24, OgImageRenderer::WHITE, (int) ( $w / 2 ), 33, $date['head'], Imagick::ALIGN_CENTER );

		if ( $range ) {
			$left  = (int) ( $w / 4 );
			$right = (int) ( 3 * $w / 4 );

			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 50, OgImageRenderer::TEXT, $left, self::BADGE_HEAD_H + 56, $date['day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 24, OgImageRenderer::MUTED, $left, self::BADGE_H - 20, $date['month'], Imagick::ALIGN_CENTER );

			$r->text( $badge, OgImageRenderer::BODY, 34, OgImageRenderer::MUTED, (int) ( $w / 2 ), self::BADGE_HEAD_H + 56, '–', Imagick::ALIGN_CENTER );

			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 50, OgImageRenderer::TEXT, $right, self::BADGE_HEAD_H + 56, $date['end_day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 24, OgImageRenderer::MUTED, $right, self::BADGE_H - 20, $date['end_month'], Imagick::ALIGN_CENTER );
		} else {
			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 58, OgImageRenderer::TEXT, (int) ( $w / 2 ), self::BADGE_HEAD_H + 62, $date['day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 27, OgImageRenderer::MUTED, (int) ( $w / 2 ), self::BADGE_H - 20, $date['month'], Imagick::ALIGN_CENTER );
		}

		$canvas->compositeImage( $badge, Imagick::COMPOSITE_OVER, OgImageRenderer::PAD, $top );
		$badge->clear();
	}

	/**
	 * Ročník a titulek ve sloupci vpravo od kartičky.
	 *
	 * @param string   $eyebrow Ročník, už zkrácený na šířku; prázdný = vynechat.
	 * @param string[] $lines   Řádky titulku, už zalomené na šířku.
	 * @param int      $x       Levá hrana sloupce.
	 * @param int      $bottom  Spodní hrana bloku.
	 */
	private function draw_title( Imagick $canvas, string $eyebrow, array $lines, int $x, int $bottom ): void {
		$r    = $this->renderer;
		$last = $bottom - self::TITLE_DESCENT;

		foreach ( $lines as $i => $line ) {
			$r->text(
				$canvas,
				OgImageRenderer::HEAD_BOLD,
				self::TITLE_SIZE,
				OgImageRenderer::WHITE,
				$x,
				$last - self::TITLE_LEADING * ( count( $lines ) - 1 - $i ),
				$line
			);
		}

		if ( '' !== $eyebrow ) {
			$first = $last - self::TITLE_LEADING * ( count( $lines ) - 1 );

			$r->text( $canvas, OgImageRenderer::BODY_SEMI, self::EYEBROW_SIZE, OgImageRenderer::EYEBROW, $x, $first - self::EYEBROW_GAP, $eyebrow );
		}
	}

	/**
	 * Start, cíl a pořadatel jako řádky v panelu.
	 *
	 * Na kartě 1200×630 jsou to tři úzké sloupce a dlouhé názvy míst se v nich
	 * ořezávají; na výšku je místo psát je pod sebe a vejdou se celé.
	 *
	 * @param array<int, array{label: string, value: string, note: string}> $rows
	 */
	private function draw_rows( Imagick $canvas, array $rows ): void {
		$r    = $this->renderer;
		$rows = array_values( array_filter( $rows, static fn( $row ) => '' !== $row['value'] ) );

		if ( empty( $rows ) ) {
			return;
		}

		$x     = OgImageRenderer::PAD;
		$value = $x + self::LABEL_COLUMN;
		$max   = self::WIDTH - $value - OgImageRenderer::PAD;
		$y     = self::PANEL_TOP + 70;

		foreach ( $rows as $row ) {
			$r->text( $canvas, OgImageRenderer::BODY_BOLD, self::LABEL_SIZE, OgImageRenderer::MUTED, $x, $y, $row['label'] );
			$r->text(
				$canvas,
				OgImageRenderer::HEAD_MEDIUM,
				self::VALUE_SIZE,
				OgImageRenderer::TEXT,
				$value,
				$y,
				$r->truncate( $canvas, OgImageRenderer::HEAD_MEDIUM, self::VALUE_SIZE, $row['value'], $max )
			);

			$y += 40;

			if ( '' !== $row['note'] ) {
				$r->text(
					$canvas,
					OgImageRenderer::BODY,
					self::NOTE_SIZE,
					OgImageRenderer::MUTED,
					$value,
					$y,
					$r->truncate( $canvas, OgImageRenderer::BODY, self::NOTE_SIZE, $row['note'], $max )
				);
				$y += 40;
			}

			$y += 18;
		}
	}

	/**
	 * Délky tras — ikona typu a text `Pěší turistika: 12, 25 km`.
	 *
	 * Kotví se ke spodní hraně, ne pod předchozí řádky: počet řádků nahoře
	 * kolísá podle toho, jestli akce má cíl a místa, a bez kotvení zespodu by
	 * se spodní hrana panelu s každou akcí posouvala.
	 *
	 * Ikony jsou tady bez bílého podkladu — panel je světlý a piktogramy jsou
	 * černá kresba na bílé, takže na něm sedí samy od sebe.
	 *
	 * @param array<int, array{icon: string, text: string}> $routes
	 */
	private function draw_routes( Imagick $canvas, array $routes ): void {
		$r      = $this->renderer;
		$routes = array_slice( $routes, 0, self::ROUTE_MAX );

		if ( empty( $routes ) ) {
			return;
		}

		$x    = OgImageRenderer::PAD;
		$text = $x + self::ROUTE_ICON + 16;
		$max  = self::WIDTH - $text - OgImageRenderer::PAD;
		$last = self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP;
		$top  = $last - self::ROUTE_STEP * ( count( $routes ) - 1 );

		$r->rect( $canvas, $x, $top - self::ROUTE_STEP, self::WIDTH - 2 * OgImageRenderer::PAD, 1, 'rgba(22,32,43,0.12)' );

		foreach ( $routes as $i => $route ) {
			$y = $top + self::ROUTE_STEP * $i;

			if ( '' !== $route['icon'] ) {
				$icon = $r->icon( $route['icon'], self::ROUTE_ICON );

				if ( $icon ) {
					$canvas->compositeImage( $icon, Imagick::COMPOSITE_OVER, $x, $y - self::ROUTE_ICON + 8 );
					$icon->clear();
				}
			}

			$r->text(
				$canvas,
				OgImageRenderer::BODY_MEDIUM,
				self::ROUTE_SIZE,
				OgImageRenderer::TEXT,
				$text,
				$y,
				$r->truncate( $canvas, OgImageRenderer::BODY_MEDIUM, self::ROUTE_SIZE, $route['text'], $max )
			);
		}
	}
}
```

- [ ] **Step 2: Ověř syntaxi a sestavení kontejneru**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/EventPosterCard.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: `No syntax errors detected`, pak `KČT Středočeská oblast`.

---

## Task 7: Data pro plakáty ve feature OgImages

**Files:**
- Modify: `src/Features/OgImages.php`

- [ ] **Step 1: Přidej měsíc do dat obou stávajících karet**

Karty na výšku potřebují měsíc pro barvu značky. Aby se data neskládala
dvakrát, přidá se do stávajících polí — karty 1200×630 ho ignorují, ale
změní se tím hash, takže se přegenerují (což je stejně potřeba kvůli
Tasku 2).

V metodě `build_post()` doplň do pole `$data` za klíč `'photo'`:

```php
			'month'    => (int) get_the_date( 'n', $post ),
```

V metodě `for_event()` doplň do pole `$data` za klíč `'icons'`:

```php
			'month'   => $this->event_month( $event ),
```

- [ ] **Step 2: Přidej pomocné metody**

Na konec třídy `OgImages`, před uzavírací závorku:

```php
	/**
	 * Měsíc konání akce (1–12), nebo 0 když se nedá zjistit.
	 *
	 * Slouží k odvození barvy turistické značky na kartě 4:5.
	 */
	private function event_month( array $event ): int {
		$date = $this->text( $event['start']['date'] ?? '' );

		if ( '' === $date ) {
			$date = $this->text( $event['date'] ?? '' );
		}

		$timestamp = '' !== $date ? strtotime( $date ) : false;

		return $timestamp ? (int) date( 'n', $timestamp ) : 0;
	}

	/**
	 * Sdílecí obrázek 4:5 příspěvku pro sdílení fotkou.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_post( int $post_id ): ?array {
		$data = $this->post_data( $post_id );

		return $data ? $this->service->social_post( $data, 'post-' . $post_id ) : null;
	}

	/**
	 * Sdílecí obrázek 4:5 akce.
	 *
	 * Bere pole akce, ne ID příspěvku — sdílení sice obsluhuje jen akce
	 * s příspěvkem, ale vyrenderovat kartu jde pro kteroukoli akci a bez toho
	 * by se okrajové případy (tři trasy, dlouhé km, vícedenní) nedaly ověřit:
	 * z 318 akcí má vlastní příspěvek 12.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_event( array $event ): ?array {
		$data = $this->event_data( $event );

		return $data ? $this->service->social_event( $data, $this->event_prefix( $event ) ) : null;
	}

	/**
	 * Sdílecí obrázek 4:5 akce, která má vlastní CPT příspěvek.
	 *
	 * Tuhle cestu používá sdílení na Facebook — obsluhuje jen skutečné
	 * příspěvky.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function social_for_event_post( int $post_id ): ?array {
		if ( get_post_type( $post_id ) !== $this->event_repository->post_type() ) {
			return null;
		}

		$event = $this->events->get_event( $post_id, '' );

		return $event ? $this->social_for_event( $event ) : null;
	}

	/**
	 * Délky tras — jeden řádek na typ akce, který má vyplněné km.
	 *
	 * Hodnota `km` je v datech volný, už naformátovaný text (`12 km`,
	 * `14, 17 km`, `9–35 km`, ale i `individuální trasy` nebo `dle propozic`),
	 * takže se nic nepřepočítává ani nedoplňuje — jen se spojí s názvem typu.
	 *
	 * Typy bez vyplněného km řádek nedostanou; zůstanou jen jako ikona nahoře.
	 *
	 * @return array<int, array{icon: string, text: string}>
	 */
	private function event_routes( array $event ): array {
		$details = $event['details'] ?? array();

		if ( ! is_array( $details ) ) {
			return array();
		}

		$routes = array();

		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) ) {
				continue;
			}

			$km   = $this->text( $detail['km'] ?? '' );
			$name = $this->text( $detail['name'] ?? '' );

			if ( '' === $km || '' === $name ) {
				continue;
			}

			$icon = $this->text( $detail['icon'] ?? '' );

			$routes[] = array(
				'icon' => '' !== $icon ? (string) ( $this->icons->path( $icon ) ?? '' ) : '',
				/* translators: 1: název typu akce, 2: délky tras. */
				'text' => sprintf( __( '%1$s: %2$s', 'kct' ), $name, $km ),
			);
		}

		return $routes;
	}
```

Poznámka: `text()` vrací prázdný řetězec pro cokoli, co není skalár, takže
prázdné pole v `km` (v datech je jich 750) projde jako `''` a řádek se
přeskočí. Ošetřovat to zvlášť není potřeba.

- [ ] **Step 3: Vytáhni skládání dat do sdílených metod**

Dnes se data skládají přímo v `build_post()` a `for_event()`. Karty na výšku
potřebují stejná data, takže se skládání vyjme.

V `build_post()` nahraď tělo za kontrolou stavu příspěvku voláním nové metody:

```php
	private function build_post( int $post_id ): ?array {
		$data = $this->post_data( $post_id );

		return $data ? $this->service->post( $data, 'post-' . $post_id ) : null;
	}

	/**
	 * Data karty příspěvku, nebo null když příspěvek není publikovaný.
	 *
	 * Skládá se na jednom místě, protože je potřebují obě karty — 1200×630
	 * i 4:5.
	 */
	private function post_data( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		$categories = get_the_category( $post_id );
		$category   = ! empty( $categories ) ? $categories[0]->name : '';

		// Doba čtení stejně jako v template-parts/post-hero.php, ať karta
		// a hero hlavička neukazují každá jiné číslo.
		$words   = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$minutes = max( 1, (int) round( $words / 200 ) );

		return array(
			'title'    => get_the_title( $post ),
			'category' => $category,
			'meta'     => sprintf(
				/* translators: 1: datum vydání, 2: doba čtení v minutách. */
				__( '%1$s   •   %2$d min čtení', 'kct' ),
				get_the_date( 'j. n. Y', $post ),
				$minutes
			),
			'photo'    => $this->thumbnail_path( $post_id ),
			'logo'     => $this->logo_path(),
			'month'    => (int) get_the_date( 'n', $post ),
		);
	}
```

Obdobně u akce — v `for_event()` nahraď skládání voláním `event_data()`
a přidej novou metodu:

```php
	public function for_event( array $event ): ?array {
		$data = $this->event_data( $event );

		return $data ? $this->service->event( $data, $this->event_prefix( $event ) ) : null;
	}

	/**
	 * Data karty akce, nebo null když akce nemá titulek nebo datum.
	 *
	 * Nese klíče pro obě karty: `columns` čte karta 1200×630, `rows`
	 * a `routes` karta 4:5. Zdvojení je záměrné — na šířku se údaje vejdou
	 * jen do tří úzkých sloupců, na výšku se píšou pod sebe a s délkami tras.
	 */
	private function event_data( array $event ): ?array {
		$title = $this->text( $event['title'] ?? '' );

		if ( '' === $title ) {
			return null;
		}

		$formatted = $event['formated_date'] ?? null;

		if ( ! is_array( $formatted ) || '' === $this->text( $formatted['day'] ?? '' ) ) {
			// Bez data by karta neměla o čem být — datumová kartička je její
			// nosný prvek. Volající spadne na dnešní obrázek.
			return null;
		}

		$range   = ! empty( $formatted['is_range'] );
		$columns = $this->event_columns( $event );

		return array(
			'title'   => $title,
			'eyebrow' => $this->year_label( $event ),
			'date'    => array(
				'is_range'  => $range,
				// Velikost písmen dělá na webu CSS (.event-date__head má
				// text-transform: uppercase, .event-date__mon lowercase),
				// Imagick nic takového nemá — tak se to udělá tady, ať karta
				// a výpis akcí ukazují totéž. date_i18n('M') vrací „Led“.
				'head'      => mb_strtoupper( $this->text( $range ? ( $formatted['days_label'] ?? '' ) : ( $formatted['day_abbr'] ?? '' ) ), 'UTF-8' ),
				'day'       => $this->text( $formatted['day'] ?? '' ),
				'month'     => mb_strtolower( $this->text( $formatted['month'] ?? '' ), 'UTF-8' ),
				'end_day'   => $this->text( $formatted['end_day'] ?? '' ),
				'end_month' => mb_strtolower( $this->text( $formatted['end_month'] ?? '' ), 'UTF-8' ),
			),
			'columns' => $columns,
			'rows'    => $columns,
			'routes'  => $this->event_routes( $event ),
			'icons'   => $this->event_icons( $event ),
			'photo'   => $this->event_photo_path( $event ),
			'logo'    => $this->logo_path(),
			'month'   => $this->event_month( $event ),
		);
	}
```

- [ ] **Step 4: Vyrob plakát i při uložení příspěvku**

V metodě `on_save()` nahraď blok na konci:

```php
		if ( 'post' === $post->post_type ) {
			$this->for_post( (int) $post_id );
			$this->social_for_post( (int) $post_id );

			return;
		}

		if ( $post->post_type === $this->event_repository->post_type() ) {
			$this->social_for_event_post( (int) $post_id );
		}
```

- [ ] **Step 5: Ověř syntaxi a vyrob vzorky**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Features/OgImages.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

Zapiš do `/Users/martin/Sites/sokct/postertest.php`:

```php
<?php
$og = kct_container()->get( \Kct\Features\OgImages::class );

foreach ( get_posts( array( 'numberposts' => 2, 'post_status' => 'publish' ) ) as $p ) {
	$r = $og->social_for_post( $p->ID );
	printf( "%-14s %s\n", 'příspěvek', $r ? basename( $r['url'] ) . "  {$r['width']}×{$r['height']}" : 'NEVYROBENO' );
}

foreach ( get_posts( array( 'post_type' => 'akce', 'numberposts' => 3, 'post_status' => 'publish' ) ) as $p ) {
	$r = $og->social_for_event_post( $p->ID );
	printf( "%-14s %s\n", 'akce', $r ? basename( $r['url'] ) . "  {$r['width']}×{$r['height']}" : 'NEVYROBENO' );
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file postertest.php && rm postertest.php
ls -la wp-content/uploads/kct-og/social-*.jpg
```

Očekávaný výstup: pět řádků s názvy souborů `social-…jpg` a rozměry
`1080×1350`, a v adresáři odpovídající soubory.

---

## Task 8: GraphClient — publikace fotky

**Files:**
- Modify: `src/Facebook/GraphClient.php`

- [ ] **Step 1: Přidej metodu**

Za metodu `publish()` přidej:

```php
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
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Facebook/GraphClient.php
```

Očekávaný výstup: `No syntax errors detected in src/Facebook/GraphClient.php`

---

## Task 9: Odkaz do textu a výběr fotky

**Files:**
- Modify: `src/Facebook/MessageComposer.php`
- Modify: `src/Features/FacebookShare.php`
- Modify: `src/CLI.php`

- [ ] **Step 1: Připoj odkaz na konec textu**

V `src/Facebook/MessageComposer.php` nahraď metodu `compose()`:

```php
	/**
	 * Text příspěvku. Vlastní text redaktora má vždy přednost.
	 *
	 * Odkaz se připojuje na konec, protože se sdílí fotkou — u fotopříspěvku
	 * není klikací náhledová karta a adresa v textu je jediné místo, odkud se
	 * dá na web dostat. Krátké aktuality odkaz nemají (nemají detail, na který
	 * by vedl), takže u nich text zůstává, jak byl.
	 */
	public function compose( WP_Post $post ): string {
		$body = $this->body( $post );
		$link = $this->link( $post );

		return null === $link ? $body : $body . "\n\n" . $link;
	}

	/** Samotný text bez odkazu. */
	private function body( WP_Post $post ): string {
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
```

- [ ] **Step 2: Vyber fotku ve FacebookShare**

V `src/Features/FacebookShare.php` nahraď blok, který volá `publish()`
(uvnitř `try`, kolem řádku 207):

```php
			$image  = $this->social_image( $post );
			$result = null === $image
				? $this->client->publish(
					$this->credentials->page_id(),
					$this->credentials->token(),
					$this->composer->compose( $post ),
					$this->composer->link( $post )
				)
				: $this->client->publish_photo(
					$this->credentials->page_id(),
					$this->credentials->token(),
					$this->composer->compose( $post ),
					$image
				);
```

Přidej `OgImages` do konstruktoru — jako poslední parametr, ostatní nech beze
změny:

```php
	public function __construct(
		private CustomFields $wcf,
		private Credentials $credentials,
		private GraphClient $client,
		private MessageComposer $composer,
		private ShareState $state,
		private OgImages $og_images
	) {
```

Import se nepřidává: `FacebookShare` i `OgImages` jsou oba v namespace
`Kct\Features`, takže holý název stačí. `PostPostType` je v souboru
naimportovaný už dnes.

A na konec třídy přidej:

```php
	/**
	 * Adresa sdílecího obrázku 4:5, nebo null.
	 *
	 * Null znamená „pošli to odkazem jako dřív" — sdílení se kvůli obrázku
	 * nesmí neuskutečnit.
	 */
	private function social_image( WP_Post $post ): ?string {
		$result = PostPostType::KEY === $post->post_type
			? $this->og_images->social_for_post( (int) $post->ID )
			: $this->og_images->social_for_event_post( (int) $post->ID );

		return $result['url'] ?? null;
	}
```

- [ ] **Step 3: Totéž v příkazu fb_share**

V `src/CLI.php` nahraď volání `publish()` v metodě `fb_share` (kolem
řádku 194):

```php
			$images = kct_container()->get( \Kct\Features\OgImages::class );
			$image  = \Kct\PostTypes\PostPostType::KEY === get_post_type( $post_id )
				? $images->social_for_post( $post_id )
				: $images->social_for_event_post( $post_id );

			$client = kct_container()->get( GraphClient::class );

			$result = empty( $image['url'] )
				? $client->publish( $credentials->page_id(), $credentials->token(), $message, $link )
				: $client->publish_photo( $credentials->page_id(), $credentials->token(), $message, $image['url'] );
```

- [ ] **Step 4: Ověř syntaxi a složení textu**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Facebook/MessageComposer.php && php -l src/Features/FacebookShare.php && php -l src/CLI.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

Zapiš do `/Users/martin/Sites/sokct/composetest.php`:

```php
<?php
$composer = kct_container()->get( \Kct\Facebook\MessageComposer::class );

foreach ( get_posts( array( 'numberposts' => 2, 'post_status' => 'publish' ) ) as $p ) {
	$text = $composer->compose( $p );
	printf( "— %s\n", mb_substr( $p->post_title, 0, 50 ) );
	printf( "  poslední řádek: %s\n", trim( (string) strrchr( $text, "\n" ) ) );
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file composetest.php && rm composetest.php
```

Očekávaný výstup: u obou příspěvků je posledním řádkem adresa začínající
`https://`. Kdyby tam adresa nebyla, jde o krátkou aktualitu (`short_news`),
což je správně — zkontroluj to `wp post meta get <id> short_news`.

---

## Task 10: Vizuální kontrola a ověření na produkci

**Files:**
- Modify: `src/Og/PostPosterCard.php` (velikosti, pokud je potřeba)
- Modify: `src/Og/EventPosterCard.php` (velikosti, pokud je potřeba)
- Modify: `src/Og/OgImageRenderer.php` (`RENDER_VERSION`, pokud se něco změní)

- [ ] **Step 1: Vyrob okrajové případy**

Zapiš do `/Users/martin/Sites/sokct/posteredge.php`:

```php
<?php
$og     = kct_container()->get( \Kct\Features\OgImages::class );
$events = kct_container()->get( \Kct\Features\Events::class )->get_events();

$cases = array(
	'tři trasy'      => static fn( $e ) => 3 <= count( array_filter( (array) ( $e['details'] ?? array() ), static fn( $d ) => ! empty( $d['km'] ) && is_string( $d['km'] ) ) ),
	'jedna trasa'    => static fn( $e ) => 1 === count( array_filter( (array) ( $e['details'] ?? array() ), static fn( $d ) => ! empty( $d['km'] ) && is_string( $d['km'] ) ) ),
	'bez tras'       => static fn( $e ) => 0 === count( array_filter( (array) ( $e['details'] ?? array() ), static fn( $d ) => ! empty( $d['km'] ) && is_string( $d['km'] ) ) ),
	'dlouhé km'      => static fn( $e ) => (bool) array_filter( (array) ( $e['details'] ?? array() ), static fn( $d ) => is_string( $d['km'] ?? null ) && mb_strlen( $d['km'] ) > 20 ),
	'vícedenní'      => static fn( $e ) => ! empty( $e['formated_date']['is_range'] ),
	'bez cíle'       => static fn( $e ) => empty( $e['finish']['date'] ),
	'dlouhý titulek' => static fn( $e ) => mb_strlen( $e['title'] ?? '' ) > 45,
);

foreach ( $cases as $name => $matches ) {
	$found = null;

	foreach ( $events as $event ) {
		if ( $matches( $event ) ) {
			$found = $event;
			break;
		}
	}

	if ( ! $found ) {
		printf( "%-16s — žádná taková akce\n", $name );
		continue;
	}

	$result = $og->social_for_event( $found );

	printf(
		"%-16s %-44s %s\n",
		$name,
		mb_substr( $found['title'], 0, 42 ),
		$result ? basename( $result['url'] ) : 'NEVYROBENO'
	);
}

// A jeden plakát příspěvku pro srovnání.
$post = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) )[0];
$r    = $og->social_for_post( $post->ID );
printf( "%-16s %-44s %s\n", 'příspěvek', mb_substr( $post->post_title, 0, 42 ), $r ? basename( $r['url'] ) : 'NEVYROBENO' );
```

Spusť:

```bash
cd /Users/martin/Sites/sokct
ddev wp --url=https://sokct.test eval-file posteredge.php && rm posteredge.php
```

Očekávaný výstup: u každého případu název akce a název vyrobeného souboru,
nebo poznámka, že taková akce v datech není. `NEVYROBENO` znamená chybu —
podívej se do `wp-content/debug.log`.

Používá se `social_for_event()`, ne `social_for_event_post()`: renderovat jde
kterákoli akce, ale sdílení obsluhuje jen těch 12, které mají příspěvek —
a na těch by se většina okrajových případů nedala vyzkoušet.

- [ ] **Step 2: Zmenši vzorky na šířku, v jaké je ukazuje Facebook**

```bash
S=/private/tmp/claude-501/-Users-martin-Sites-sokct/569d1201-a09e-499b-b4ed-2439fb20d975/scratchpad
mkdir -p $S
cd /Users/martin/Sites/sokct
for f in wp-content/uploads/kct-og/social-*.jpg; do
  ddev exec convert "/var/www/html/$f" -resize 500x "/var/www/html/p500-$(basename $f .jpg).png"
done
mv p500-*.png $S/
ls $S/p500-*.png
```

- [ ] **Step 3: Posuď a dolaď**

Prohlédni obrázky. Rozhodovací otázky:

1. Vejdou se údaje v panelu, aniž by přetekly přes trikolóru nebo se překryly
   s řádky délek tras?
2. Jsou popisky (`START`, `CÍL`, `POŘADATEL`) a hodnoty čitelné ve zmenšenině?
3. Nezasahuje motiv značky do textu?
4. Nepřekrývá blok ikon vlevo nahoře logo vpravo?

Když něco nesedí, uprav konstanty v příslušné kartě a **zvyš
`OgImageRenderer::RENDER_VERSION` o jedna**, jinak se stará podoba bude dál
servírovat z disku. Pak smaž staré plakáty a vyrob je znovu:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
find wp-content/uploads/kct-og -maxdepth 1 -name 'social-*.jpg' -delete
```

a zopakuj Step 1 a 2.

- [ ] **Step 4: Zkontroluj velikost souborů**

```bash
cd /Users/martin/Sites/sokct
ls -la wp-content/uploads/kct-og/social-*.jpg | awk '{s+=$5; n++} END {printf "souborů: %d, průměr: %d kB\n", n, s/n/1024}'
```

Očekávaný výstup: průměr řádově 200–400 kB. Kdyby to bylo přes 1 MB, sniž
`QUALITY` v obou kartách z 88 na 82.

- [ ] **Step 5: Ověř, že karty 1200×630 pořád fungují**

Task 1 a 2 sáhly na renderer i na obě stávající karty, takže se musí ověřit,
že se nerozbily.

```bash
cd /Users/martin/Sites/sokct
A=$(ddev wp --url=https://sokct.test post list --post_type=akce --post_status=publish --posts_per_page=1 --field=url)
P=$(ddev wp --url=https://sokct.test post list --post_type=post --post_status=publish --posts_per_page=1 --field=url)
for u in "$A" "$P"; do curl -sk "$u" | grep -oE '<meta property="og:image(:width|:height)?"[^>]*>' | sed 's/^/  /'; done
```

Očekávaný výstup: u obou `og:image` mířící na `kct-og/…png` (ne `.jpg`), plus
`og:image:width` 1200 a `og:image:height` 630.

- [ ] **Step 6: Ověření na produkci**

**Nasazení a odeslání spouští Martin, ne agent.** Odeslání na Facebook je
zápis na veřejný profil.

Po nasazení Martin pustí na jednom konkrétním příspěvku:

```bash
wp kct fb_share <id>
```

a zkontroluje na stránce KČT, že:

- příspěvek je fotka na výšku, ne odkazová karta,
- v textu je na konci adresa a vede na správný detail,
- odkaz „Zobrazit na Facebooku" v metaboxu příspěvku funguje (to ověřuje, že
  se uložilo `post_id`, ne ID fotky).

Kdyby Graph API vrátilo chybu, je v ní důvod — nejčastěji nedosažitelná adresa
obrázku nebo chybějící oprávnění stránky.

---

## Poznámky k údržbě

**Změna vzhledu jakékoli karty** znamená zvýšit `OgImageRenderer::RENDER_VERSION`.
Je to jediná věc, na kterou se dá v tomhle kódu zapomenout, a projeví se to
tím, že „se změna neprojevila".

**Instagram** je samostatná integrace, ne parametr navíc: potřebuje Instagram
Business účet propojený se stránkou, oprávnění `instagram_content_publish`
a publikuje se nadvakrát. Formát 4:5 a výstup v JPEG jsou zvolené tak, aby jí
nic nebránilo, ale kód pro ni tady není.

**Sdílení fotkou stojí prokliky.** Kdyby se ukázalo, že návštěvnost z Facebooku
spadla víc, než za to stojí, návrat je snadný — `FacebookShare::social_image()`
stačí nechat vracet `null` a všechno se vrátí k odkazům. Karty 4:5 se tím
nezahodí, jen se přestanou používat.
