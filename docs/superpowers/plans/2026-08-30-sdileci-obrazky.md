# Sdílecí obrázky příspěvků a akcí — implementační plán

> **Pro agenty:** POVINNÁ SUB-SKILL: použij `superpowers:subagent-driven-development`
> (doporučeno) nebo `superpowers:executing-plans` a odpracuj plán úkol po úkolu.
> Kroky používají checkbox (`- [ ]`) syntaxi.

**Spec:** [`docs/superpowers/specs/2026-08-30-sdileci-obrazky-design.md`](../specs/2026-08-30-sdileci-obrazky-design.md)

**Cíl:** Generovat vlastní sdílecí obrázek 1200×630 pro příspěvky (fotografická
karta podle hero hlavičky) a pro akce (datová karta s datem, titulkem, startem,
cílem a pořadatelem), aby odkaz na Facebooku nesl informaci místo holé fotky
nebo loga.

**Architektura:** Nový namespace `Kct\Og` — `OgImageRenderer` drží Imagick
primitiva a nezná WordPress, `PostCard` a `EventCard` skládají rozvržení,
`OgImageStore` obsluhuje disk a `OgImageService` to spojuje dohromady. Jediné
místo, které zná WordPress, je feature `Kct\Features\OgImages`. Obrázky leží
jako statické PNG ve `wp-content/uploads/kct-og/`, název nese hash obsahu,
takže `og:image` míří rovnou na soubor a servíruje ho nginx.

**Tech stack:** PHP 8.0+, WordPress multisite, Imagick (ImageMagick 6.9.11-60),
PHP-DI (`config.php`), WP-CLI, wpify/model, wpify/plugin-utils.

---

## Než začneš

**Necommituj.** Commity a správu větví si dělá Martin sám. Každý úkol proto
končí ověřením, ne commitem. Platí to i tam, kde to skill říká jinak.

**V projektu není PHPUnit ani jiná testovací infrastruktura.** Ověřování stojí
na `php -l`, `ddev wp eval-file` a `curl`. Nezaváděj kvůli tomuto plánu
testovací framework — to je samostatné rozhodnutí.

**Nikdy nezapisuj do databáze kvůli ověření.** Ověřuj výhradně čtením a
renderem do souborů. Zápis testovacích dat do `sidebars_widgets` už jednou
v tomhle projektu smazal uživateli widgety.

**Příkazy** se pouští z kořene projektu `/Users/martin/Sites/sokct`. Cesty
v plánu jsou relativní ke kořeni pluginu
`/Users/martin/Sites/sokct/wp-content/plugins/kct`.

Víceřádkový PHP kód nedávej do `ddev wp eval` — shell escapování ho rozbije.
Zapiš ho do souboru v kořeni projektu a pusť `ddev wp eval-file <soubor>`,
pak soubor smaž.

**Prostředí je ověřené**, na tohle nemusíš znovu:

```
DDEV      ImageMagick 6.9.11-60 Q16 aarch64   kresba textu OK   SVG ano
produkce  ImageMagick 6.9.11-60 Q16 x86_64    kresba textu OK   SVG NE
```

Z toho plyne pravidlo, které v tomhle plánu nesmíš porušit: **žádné SVG.**
Produkce nemá SVG delegáta, takže by se cokoli ze SVG vykreslilo lokálně a na
produkci zmizelo. Všechno se kreslí přes `ImagickDraw` nebo z PNG.

**Po dokončení pluginu vždy smaž zkompilovaný kontejner**, jinak PHP-DI
nenajde nové třídy:

```bash
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
```

---

## Struktura souborů

| Soubor | Odpovědnost |
|---|---|
| `resources/fonts/*.ttf` | Statické řezy Oswald a Plus Jakarta Sans pro Imagick. |
| `src/Og/OgImageRenderer.php` | Imagick primitiva: plátno, přechod, zaoblený obdélník, text, zalomení, ořez, trikolóra, logo, fotka. Nezná WordPress ani typy obsahu. |
| `src/Og/OgImageStore.php` | Disk: cesta, URL, atomický zápis, úklid starších verzí. |
| `src/Og/PostCard.php` | Rozvržení karty příspěvku. Dostane pole dat, vrátí PNG blob. |
| `src/Og/EventCard.php` | Rozvržení karty akce. Totéž. |
| `src/Og/OgImageService.php` | Z dat spočítá klíč, zeptá se úložiště, jinak nechá vyrenderovat a uloží. Vrací pole `url`/`width`/`height`. |
| `src/Features/OgImages.php` | Háky WordPressu: `save_post`, filtry Rank Mathu. Skládá data pro karty. |
| `src/Managers/FeaturesManager.php` | Registrace nové feature. |
| `src/Seo/EventSeoData.php` | `image()` se nejdřív zeptá služby. |
| `src/Features/OpenGraph.php` | `image_url()` se nejdřív zeptá služby. |
| `src/CLI.php` | Příkaz `wp kct og_images`. |

Hranice: renderer neví, co kreslí. Karty nevědí, kam se to ukládá. Úložiště
neví, jak obrázek vznikl. Feature je jediné místo, kde se sahá na WordPress.

---

## Task 1: Fonty do repozitáře

**Files:**
- Create: `resources/fonts/Oswald-Medium.ttf`
- Create: `resources/fonts/Oswald-Bold.ttf`
- Create: `resources/fonts/PlusJakartaSans-Regular.ttf`
- Create: `resources/fonts/PlusJakartaSans-Medium.ttf`
- Create: `resources/fonts/PlusJakartaSans-SemiBold.ttf`
- Create: `resources/fonts/PlusJakartaSans-Bold.ttf`
- Create: `resources/fonts/OFL-Oswald.txt`
- Create: `resources/fonts/OFL-PlusJakartaSans.txt`

Imagick umí kreslit text jen z fontu na disku. Téma bere oba fonty z Google
Fonts CDN a v repozitáři žádný TTF není.

Zdroj **není** repozitář `google/fonts` — ten u obou rodin vede jen variabilní
font a ImageMagick 6.9 neumí nastavit osu váhy, takže by vykreslil výchozí
instanci (u Plus Jakarta Sans ExtraLight 200). Statické řezy se berou
z původních repozitářů, na které Google Fonts odkazuje ve svém
`upstream_info.md`.

- [ ] **Step 1: Stáhni statické řezy**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
mkdir -p resources/fonts

OSW=https://raw.githubusercontent.com/googlefonts/OswaldFont/main/fonts/ttf
PJS=https://raw.githubusercontent.com/tokotype/PlusJakartaSans/master/fonts/ttf

curl -sL -o resources/fonts/Oswald-Medium.ttf            "$OSW/Oswald-Medium.ttf"
curl -sL -o resources/fonts/Oswald-Bold.ttf              "$OSW/Oswald-Bold.ttf"
curl -sL -o resources/fonts/PlusJakartaSans-Regular.ttf  "$PJS/PlusJakartaSans-Regular.ttf"
curl -sL -o resources/fonts/PlusJakartaSans-Medium.ttf   "$PJS/PlusJakartaSans-Medium.ttf"
curl -sL -o resources/fonts/PlusJakartaSans-SemiBold.ttf "$PJS/PlusJakartaSans-SemiBold.ttf"
curl -sL -o resources/fonts/PlusJakartaSans-Bold.ttf     "$PJS/PlusJakartaSans-Bold.ttf"

curl -sL -o resources/fonts/OFL-Oswald.txt \
  https://raw.githubusercontent.com/google/fonts/main/ofl/oswald/OFL.txt
curl -sL -o resources/fonts/OFL-PlusJakartaSans.txt \
  https://raw.githubusercontent.com/google/fonts/main/ofl/plusjakartasans/OFL.txt
```

- [ ] **Step 2: Ověř, že to jsou opravdu fonty a ne chybové stránky**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
file resources/fonts/*.ttf
du -sh resources/fonts
```

Očekávaný výstup: u každého `.ttf` řádek obsahující `TrueType`, celkem `728K`.
Kdyby soubor měl 14 bajtů, je to `404: Not Found` — stažení selhalo.

- [ ] **Step 3: Ověř, že Imagick všechny řezy vykreslí včetně diakritiky**

Zapiš do `/Users/martin/Sites/sokct/fonttest.php`:

```php
<?php
$dir = '/var/www/html/wp-content/plugins/kct/resources/fonts';
$txt = 'Příkrý žluťoučký kůň — 38. ročník';
foreach ( glob( $dir . '/*.ttf' ) as $f ) {
	$img = new Imagick();
	$img->newImage( 900, 80, new ImagickPixel( '#0d1926' ) );
	$d = new ImagickDraw();
	$d->setFont( $f );
	$d->setFontSize( 34 );
	$d->setFillColor( new ImagickPixel( 'white' ) );
	$img->annotateImage( $d, 15, 52, 0, $txt );
	$m = $img->queryFontMetrics( $d, $txt );
	printf( "%-32s OK  šířka %4d px\n", basename( $f ), (int) $m['textWidth'] );
	$img->clear();
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct && ddev exec php fonttest.php && rm fonttest.php
```

Očekávaný výstup: šest řádků `OK`, každý s jinou šířkou. Kdyby byly šířky
u všech řezů stejné, načítá se jeden font a stažení vzalo variabilní verzi.

Referenční hodnoty naměřené při přípravě plánu:

```
Oswald-Bold.ttf                  486 px
Oswald-Medium.ttf                455 px
PlusJakartaSans-Bold.ttf         543 px
PlusJakartaSans-Medium.ttf       525 px
PlusJakartaSans-Regular.ttf      509 px
PlusJakartaSans-SemiBold.ttf     528 px
```

- [ ] **Step 4: Ověř, že fonty projdou do balíčku i na produkci**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
grep -n 'exclude' .github/workflows/deploy.yml | grep -i 'resources'
```

Očekávaný výstup: prázdný. `resources/` není mezi výjimkami ani u rsyncu, ani
u kroku „Build plugin ZIP", takže se fonty dostanou na produkci i do ZIPu bez
další změny.

---

## Task 2: OgImageRenderer — Imagick primitiva

**Files:**
- Create: `src/Og/OgImageRenderer.php`

Třída drží všechno, co se týká kreslení, a nic, co se týká obsahu. Karty ji
dostanou v konstruktoru.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use KctDeps\Wpify\PluginUtils\PluginUtils;
use Throwable;

/**
 * Kreslicí primitiva sdílecích obrázků.
 *
 * Nezná WordPress ani typy obsahu — dostane rozměry, barvy a texty a vrátí
 * pixely. Rozvržení skládají PostCard a EventCard.
 *
 * ŽÁDNÉ SVG. Produkce nemá v Imagicku SVG delegáta (ověřeno: DDEV `SVG: ano`,
 * produkce `SVG: NE`), takže cokoli ze SVG by se vykreslilo lokálně a na
 * produkci beze slova zmizelo. Kreslí se přes ImagickDraw nebo z PNG.
 *
 * Kompozice výhradně přes COMPOSITE_OVER.
 */
class OgImageRenderer {

	/**
	 * Verze vykreslování. Vstupuje do hashe v názvu souboru, takže zvýšení
	 * čísla přegeneruje všechny obrázky. Bumpni při každé změně rozvržení
	 * nebo barev, jinak se bude dál servírovat stará podoba.
	 */
	public const RENDER_VERSION = 1;

	public const WIDTH  = 1200;
	public const HEIGHT = 630;
	public const PAD    = 48;
	public const STRIP  = 8;

	// Barvy z core/_variables.scss, ať karta a web nedrží každý svoje.
	public const INK       = '#0d1926'; // --surface-invert
	public const INK_LIGHT = '#16304a'; // tmavý odstín --kct-blue, konec přechodu
	public const BLUE      = '#1466B0'; // --kct-blue
	public const RED       = '#E4032E'; // --kct-red
	public const GREEN     = '#009640'; // --kct-green
	public const YELLOW    = '#FFCC00'; // --kct-yellow
	public const TEXT      = '#16202b'; // --text
	public const MUTED     = '#7b8492'; // --text-muted
	public const LINE      = '#eef1f5'; // --line
	public const EYEBROW   = '#8fd0ff'; // --hero-eyebrow
	public const WHITE     = '#ffffff';

	public const HEAD_MEDIUM = 'Oswald-Medium';
	public const HEAD_BOLD   = 'Oswald-Bold';
	public const BODY        = 'PlusJakartaSans-Regular';
	public const BODY_MEDIUM = 'PlusJakartaSans-Medium';
	public const BODY_SEMI   = 'PlusJakartaSans-SemiBold';
	public const BODY_BOLD   = 'PlusJakartaSans-Bold';

	private const FONTS = array(
		self::HEAD_MEDIUM,
		self::HEAD_BOLD,
		self::BODY,
		self::BODY_MEDIUM,
		self::BODY_SEMI,
		self::BODY_BOLD,
	);

	public function __construct( private PluginUtils $utils ) {
	}

	/**
	 * Je čím kreslit? Zjišťuje se jednou za request.
	 *
	 * Sdílecí obrázek je ozdoba, ne funkce — když chybí Imagick nebo font,
	 * volající spadne na dnešní chování a nic se nerozbije.
	 */
	public function available(): bool {
		static $ok = null;

		if ( null !== $ok ) {
			return $ok;
		}

		if ( ! class_exists( '\Imagick' ) || ! class_exists( '\ImagickDraw' ) ) {
			$ok = false;

			return $ok;
		}

		foreach ( self::FONTS as $font ) {
			if ( ! is_readable( $this->font( $font ) ) ) {
				$ok = false;

				return $ok;
			}
		}

		$ok = true;

		return $ok;
	}

	public function font( string $name ): string {
		return $this->utils->get_plugin_path( 'resources/fonts/' . $name . '.ttf' );
	}

	public function canvas( string $color = self::INK ): Imagick {
		$canvas = new Imagick();
		$canvas->newImage( self::WIDTH, self::HEIGHT, new ImagickPixel( $color ) );
		$canvas->setImageFormat( 'png' );

		return $canvas;
	}

	/**
	 * Svislý přechod složený přes plátno.
	 *
	 * Barvy se předávají jako řetězce pro ImageMagick, takže jde použít
	 * i `transparent` nebo `rgba(13,25,38,0.92)`.
	 */
	public function gradient( Imagick $canvas, string $from, string $to, int $height, int $top ): void {
		$gradient = new Imagick();
		$gradient->newPseudoImage( self::WIDTH, $height, sprintf( 'gradient:%s-%s', $from, $to ) );
		$canvas->compositeImage( $gradient, Imagick::COMPOSITE_OVER, 0, $top );
		$gradient->clear();
	}

	/**
	 * Fotka načtená a oříznutá na rozměr plátna, nebo null.
	 *
	 * flattenImages() srovná průhlednost na tmavé pozadí, ať se z PNG s alfa
	 * kanálem nestane bílá díra. cropThumbnailImage() zachová poměr a ořízne
	 * přetékající stranu, tedy chová se jako `object-fit: cover`.
	 */
	public function photo( string $path ): ?Imagick {
		try {
			$image = new Imagick( $path );
			$image->setImageBackgroundColor( new ImagickPixel( self::INK ) );
			$image = $image->flattenImages();
			$image->cropThumbnailImage( self::WIDTH, self::HEIGHT );
			$image->setImageFormat( 'png' );

			return $image;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Zaoblený obdélník jako samostatná vrstva.
	 *
	 * Kreslí se přímo, ne maskou — maska dělá kolem hran tmavý lem.
	 */
	public function rounded_rect( int $w, int $h, int $radius, string $fill, ?string $stroke = null, int $stroke_width = 0 ): Imagick {
		$tile = new Imagick();
		$tile->newImage( $w, $h, new ImagickPixel( 'transparent' ) );
		$tile->setImageFormat( 'png' );

		$draw = new ImagickDraw();
		$draw->setFillColor( new ImagickPixel( $fill ) );

		if ( null !== $stroke ) {
			$draw->setStrokeColor( new ImagickPixel( $stroke ) );
			$draw->setStrokeWidth( $stroke_width );
		}

		$inset = max( 1, $stroke_width ) / 2;
		$draw->roundRectangle( $inset, $inset, $w - $inset, $h - $inset, $radius, $radius );
		$tile->drawImage( $draw );

		return $tile;
	}

	/** Plný obdélník jako vrstva — pro linky, pásy a ztmavení. */
	public function rect( Imagick $canvas, int $x, int $y, int $w, int $h, string $color ): void {
		if ( $w < 1 || $h < 1 ) {
			return;
		}

		$tile = new Imagick();
		$tile->newImage( $w, $h, new ImagickPixel( $color ) );
		$canvas->compositeImage( $tile, Imagick::COMPOSITE_OVER, $x, $y );
		$tile->clear();
	}

	private function pen( string $font, int $size, string $color, int $align = Imagick::ALIGN_LEFT ): ImagickDraw {
		$draw = new ImagickDraw();
		$draw->setFont( $this->font( $font ) );
		$draw->setFontSize( $size );
		$draw->setFillColor( new ImagickPixel( $color ) );
		$draw->setTextAlignment( $align );

		return $draw;
	}

	/** Vykreslí text; $y je účaří, ne horní hrana. */
	public function text( Imagick $canvas, string $font, int $size, string $color, int $x, int $y, string $text, int $align = Imagick::ALIGN_LEFT ): void {
		if ( '' === $text ) {
			return;
		}

		$canvas->annotateImage( $this->pen( $font, $size, $color, $align ), $x, $y, 0, $text );
	}

	public function width( Imagick $canvas, string $font, int $size, string $text ): int {
		if ( '' === $text ) {
			return 0;
		}

		$metrics = $canvas->queryFontMetrics( $this->pen( $font, $size, '#000000' ), $text );

		return (int) $metrics['textWidth'];
	}

	/**
	 * Rozláme text na řádky, které se vejdou do dané šířky.
	 *
	 * Šířka se měří přes queryFontMetrics() nad kandidátním řetězcem — odhad
	 * z počtu znaků u proporcionálního písma nefunguje. Co se nevejde do
	 * $max_lines řádků, se zahodí a poslední řádek dostane výpustku.
	 *
	 * @return string[]
	 */
	public function wrap( Imagick $canvas, string $font, int $size, string $text, int $max_width, int $max_lines ): array {
		$words   = preg_split( '/\s+/u', trim( $text ) ) ?: array();
		$lines   = array();
		$current = '';
		$dropped = false;

		foreach ( $words as $word ) {
			$candidate = '' === $current ? $word : $current . ' ' . $word;

			if ( $this->width( $canvas, $font, $size, $candidate ) <= $max_width ) {
				$current = $candidate;
				continue;
			}

			if ( '' === $current ) {
				// Jediné slovo delší než celý řádek — nechá se přetéct a ořízne
				// se níž, rozdělovat slovo uprostřed by bylo horší.
				$current = $word;
			} elseif ( count( $lines ) + 1 < $max_lines ) {
				$lines[] = $current;
				$current = $word;
			} else {
				$dropped = true;
				break;
			}
		}

		$lines[] = $current;

		if ( $dropped ) {
			$last           = count( $lines ) - 1;
			$lines[ $last ] = $this->truncate( $canvas, $font, $size, $lines[ $last ] . ' …', $max_width );
		}

		return $lines;
	}

	/**
	 * Zkrátí text na danou šířku a doplní výpustku.
	 *
	 * Ubírá po znaku odzadu. Texty na kartě jsou krátké (titulek, místo,
	 * pořadatel), takže se to nevyplatí dělat půlením intervalu.
	 */
	public function truncate( Imagick $canvas, string $font, int $size, string $text, int $max_width ): string {
		if ( $this->width( $canvas, $font, $size, $text ) <= $max_width ) {
			return $text;
		}

		$cut = $text;

		while ( mb_strlen( $cut ) > 1 ) {
			$cut       = mb_substr( $cut, 0, mb_strlen( $cut ) - 1 );
			$candidate = rtrim( $cut ) . '…';

			if ( $this->width( $canvas, $font, $size, $candidate ) <= $max_width ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Trikolóra u dolní hrany.
	 *
	 * Mixin kct-strip kreslí modrý pás přes celou šířku a přes něj trikolóru
	 * širokou min(100%, 1440px). Při šířce 1200 px trikolóra zabere celou
	 * šířku a modrý pás pod ní není vidět, takže se kreslí rovnou jen ona.
	 */
	public function strip( Imagick $canvas ): void {
		$y     = self::HEIGHT - self::STRIP;
		$third = (int) round( self::WIDTH / 3 );

		$this->rect( $canvas, 0, $y, $third, self::STRIP, self::RED );
		$this->rect( $canvas, $third, $y, $third, self::STRIP, self::GREEN );
		$this->rect( $canvas, 2 * $third, $y, self::WIDTH - 2 * $third, self::STRIP, self::YELLOW );
	}

	/** Logo vpravo nahoře. Bez loga karta funguje dál, proto se chyba polyká. */
	public function logo( Imagick $canvas, string $path, int $height = 56 ): void {
		if ( '' === $path || ! is_readable( $path ) ) {
			return;
		}

		try {
			$logo = new Imagick();
			$logo->setBackgroundColor( new ImagickPixel( 'transparent' ) );
			$logo->readImage( $path );
			$logo->setImageFormat( 'png' );

			$w = (int) round( $height * $logo->getImageWidth() / max( 1, $logo->getImageHeight() ) );
			$logo->resizeImage( $w, $height, Imagick::FILTER_LANCZOS, 1 );

			$canvas->compositeImage( $logo, Imagick::COMPOSITE_OVER, self::WIDTH - self::PAD - $w, 36 );
			$logo->clear();
		} catch ( Throwable $e ) {
			return;
		}
	}

	/** Vrátí PNG blob a plátno uvolní. */
	public function png( Imagick $canvas ): string {
		$canvas->setImageFormat( 'png' );
		$blob = $canvas->getImageBlob();
		$canvas->clear();

		return $blob;
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/OgImageRenderer.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/OgImageRenderer.php`

---

## Task 3: OgImageStore — disk

**Files:**
- Create: `src/Og/OgImageStore.php`

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

/**
 * Úložiště vygenerovaných sdílecích obrázků.
 *
 * Cesta se bere z wp_get_upload_dir(), takže v multisite dostane každý web
 * vlastní adresář pod uploads/sites/{N}/ bez jediného řádku navíc.
 *
 * Na disku leží vždy nejvýš jeden obrázek na objekt: po zápisu nové verze se
 * starší soubory se stejnou předponou smažou.
 */
class OgImageStore {

	private const DIR = 'kct-og';

	public function dir(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	public function base_url(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['baseurl'] ) . self::DIR;
	}

	public function filename( string $prefix, string $hash ): string {
		return $prefix . '-' . $hash . '.png';
	}

	/** URL hotového obrázku, nebo null když soubor neexistuje. */
	public function url( string $prefix, string $hash ): ?string {
		$name = $this->filename( $prefix, $hash );

		return file_exists( $this->dir() . '/' . $name ) ? $this->base_url() . '/' . $name : null;
	}

	/**
	 * Uloží PNG a vrátí jeho URL, nebo null když se zapsat nepovedlo.
	 *
	 * Zapisuje se do dočasného souboru a přejmenovává až po úspěchu. Přímý
	 * zápis by při přerušení nechal na disku useknuté PNG, které by se tvářilo
	 * jako hotové — a protože se existence souboru bere jako „hotovo", už by
	 * se nikdy nepřegenerovalo.
	 */
	public function save( string $blob, string $prefix, string $hash ): ?string {
		$dir = $this->dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$name  = $this->filename( $prefix, $hash );
		$final = $dir . '/' . $name;
		$tmp   = $final . '.tmp';

		if ( false === file_put_contents( $tmp, $blob ) ) {
			return null;
		}

		if ( ! rename( $tmp, $final ) ) {
			wp_delete_file( $tmp );

			return null;
		}

		$this->prune( $prefix, $name );

		return $this->base_url() . '/' . $name;
	}

	/**
	 * Smaže starší verze téhož objektu.
	 *
	 * Předpony jsou `post-12`, `akce-12`, `akce-db-345` a maska za nimi má
	 * pomlčku, takže `post-1-*` nechytne `post-12-*` a `akce-12-*` nechytne
	 * `akce-db-12-*`.
	 */
	private function prune( string $prefix, string $keep ): void {
		foreach ( (array) glob( $this->dir() . '/' . $prefix . '-*.png' ) as $file ) {
			if ( basename( $file ) !== $keep ) {
				wp_delete_file( $file );
			}
		}
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/OgImageStore.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/OgImageStore.php`

---

## Task 4: PostCard — fotografická karta příspěvku

**Files:**
- Create: `src/Og/PostCard.php`

Rozvržení se skládá odspoda: meta řádek u dolní hrany, nad ním titulek, nad
ním chip kategorie. Díky tomu drží dolní hrana na místě bez ohledu na to,
kolik řádků má titulek.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;

/**
 * Sdílecí karta příspěvku — fotografická, odvozená z hero hlavičky detailu
 * (template-parts/post-hero.php).
 *
 * Skládá se odspoda: meta řádek sedí u dolní hrany, titulek nad ním, chip
 * kategorie nad titulkem. Dolní hrana tak drží na místě i u trojřádkového
 * titulku.
 */
class PostCard {

	private const TITLE_SIZE   = 62;
	private const TITLE_LEADING = 70;
	private const TITLE_LINES  = 3;
	private const META_SIZE    = 26;
	private const CHIP_SIZE    = 22;
	private const CHIP_HEIGHT  = 40;
	private const CHIP_PAD     = 14;

	public function __construct( private OgImageRenderer $renderer ) {
	}

	/**
	 * @param array{title: string, category: string, meta: string, photo: string, logo: string} $data
	 *
	 * @return string PNG blob.
	 */
	public function render( array $data ): string {
		$r = $this->renderer;

		$canvas = '' !== $data['photo'] ? $r->photo( $data['photo'] ) : null;

		if ( null === $canvas ) {
			// Bez fotky (nebo když se nedá načíst) stejné grafické pozadí
			// jako u akcí, ať karta nikdy nevyjde jako prázdná plocha.
			$canvas = $r->canvas( OgImageRenderer::INK );
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, OgImageRenderer::HEIGHT, 0 );
		}

		// Spodní ztmavení přes 60 % výšky, jako .post-hero__overlay.
		$shade = (int) round( OgImageRenderer::HEIGHT * 0.6 );
		$r->gradient( $canvas, 'transparent', 'rgba(13,25,38,0.92)', $shade, OgImageRenderer::HEIGHT - $shade );

		// Horní ztmavení, aby logo drželo i na světlé fotce.
		$r->gradient( $canvas, 'rgba(13,25,38,0.72)', 'transparent', 150, 0 );

		$r->logo( $canvas, $data['logo'] );

		$x   = OgImageRenderer::PAD;
		$max = OgImageRenderer::WIDTH - 2 * OgImageRenderer::PAD;

		$meta_baseline = OgImageRenderer::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP;
		$r->text(
			$canvas,
			OgImageRenderer::BODY_MEDIUM,
			self::META_SIZE,
			'rgba(255,255,255,0.86)',
			$x,
			$meta_baseline,
			$r->truncate( $canvas, OgImageRenderer::BODY_MEDIUM, self::META_SIZE, $data['meta'], $max )
		);

		$lines        = $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES );
		$title_bottom = $meta_baseline - 52;

		foreach ( $lines as $i => $line ) {
			$r->text(
				$canvas,
				OgImageRenderer::HEAD_BOLD,
				self::TITLE_SIZE,
				OgImageRenderer::WHITE,
				$x,
				$title_bottom - self::TITLE_LEADING * ( count( $lines ) - 1 - $i ),
				$line
			);
		}

		if ( '' !== $data['category'] ) {
			$label = $r->truncate( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $data['category'], 420 );
			$width = $r->width( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $label );
			$top   = $title_bottom - self::TITLE_LEADING * count( $lines ) - 22;

			$chip = $r->rounded_rect( $width + 2 * self::CHIP_PAD, self::CHIP_HEIGHT, 6, OgImageRenderer::BLUE );
			$canvas->compositeImage( $chip, Imagick::COMPOSITE_OVER, $x, $top );
			$chip->clear();

			$r->text(
				$canvas,
				OgImageRenderer::BODY_BOLD,
				self::CHIP_SIZE,
				OgImageRenderer::WHITE,
				$x + self::CHIP_PAD,
				$top + 27,
				$label
			);
		}

		$r->strip( $canvas );

		return $r->png( $canvas );
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/PostCard.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/PostCard.php`

---

## Task 5: EventCard — datová karta akce

**Files:**
- Create: `src/Og/EventCard.php`

Karta akce je primárně grafická a datová. Fotka, když vůbec je, slouží jen
jako potlačená textura — 312 z 318 importovaných akcí žádnou nemá.

- [ ] **Step 1: Vytvoř třídu**

```php
<?php

namespace Kct\Og;

use Imagick;

/**
 * Sdílecí karta akce — datová.
 *
 * Nahoře datumová kartička s ročníkem a titulkem, dole pás se startem, cílem
 * a pořadatelem, tedy totéž, co na detailu akce nesou infoboxy pod hlavičkou.
 *
 * Fotka je jen textura: odbarví se a překryje tmou. Většina importovaných akcí
 * žádnou nemá, takže výchozím stavem je grafické pozadí, ne fotka.
 */
class EventCard {

	private const TITLE_SIZE    = 54;
	private const TITLE_LEADING = 62;
	private const TITLE_LINES   = 2;
	private const EYEBROW_SIZE  = 24;

	private const BADGE_W       = 100;
	private const BADGE_W_RANGE = 150;
	private const BADGE_H       = 122;
	private const BADGE_HEAD_H  = 36;
	private const BADGE_RADIUS  = 12;
	private const BADGE_GAP     = 26;

	private const BLOCK_TOP     = 128;

	private const LABEL_SIZE    = 20;
	private const VALUE_SIZE    = 30;
	private const NOTE_SIZE     = 24;
	private const COLUMN_GAP    = 32;

	public function __construct( private OgImageRenderer $renderer ) {
	}

	/**
	 * @param array{
	 *     title: string,
	 *     eyebrow: string,
	 *     date: array{head: string, day: string, month: string, end_day: string, end_month: string, is_range: bool},
	 *     columns: array<int, array{label: string, value: string, note: string}>,
	 *     photo: string,
	 *     logo: string
	 * } $data
	 *
	 * @return string PNG blob.
	 */
	public function render( array $data ): string {
		$r = $this->renderer;

		$canvas = $this->background( $data['photo'] );
		$r->logo( $canvas, $data['logo'] );

		$badge_w = $this->draw_badge( $canvas, $data['date'] );
		$this->draw_title( $canvas, $data, OgImageRenderer::PAD + $badge_w + self::BADGE_GAP );
		$this->draw_columns( $canvas, $data['columns'] );

		$r->strip( $canvas );

		return $r->png( $canvas );
	}

	private function background( string $photo ): Imagick {
		$r = $this->renderer;

		$canvas = '' !== $photo ? $r->photo( $photo ) : null;

		if ( null === $canvas ) {
			$canvas = $r->canvas( OgImageRenderer::INK );
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, OgImageRenderer::HEIGHT, 0 );

			return $canvas;
		}

		// Fotka jen jako textura: sytost na 35 % a tma přes celou plochu.
		// Zůstane z ní tvar a nálada, ne detail, který by konkuroval datům.
		$canvas->modulateImage( 100, 35, 100 );
		$r->rect( $canvas, 0, 0, OgImageRenderer::WIDTH, OgImageRenderer::HEIGHT, 'rgba(13,25,38,0.82)' );

		return $canvas;
	}

	/**
	 * Datumová kartička podle komponenty .event-date z core/blocks/events.scss.
	 * Vrací svoji šířku, aby titulek věděl, kde začít.
	 */
	private function draw_badge( Imagick $canvas, array $date ): int {
		$r     = $this->renderer;
		$range = ! empty( $date['is_range'] );
		$w     = $range ? self::BADGE_W_RANGE : self::BADGE_W;

		$badge = $r->rounded_rect( $w, self::BADGE_H, self::BADGE_RADIUS, OgImageRenderer::WHITE, OgImageRenderer::LINE, 2 );

		// Modrá hlavička. Kreslí se jako zaoblený obdélník posunutý nahoru
		// a přiříznutý plným obdélníkem, takže zaoblené jsou jen horní rohy —
		// stejně jako .event-date__head s overflow: hidden na rodiči.
		$head = $r->rounded_rect( $w - 4, self::BADGE_HEAD_H + self::BADGE_RADIUS, self::BADGE_RADIUS, OgImageRenderer::BLUE );
		$head->cropImage( $w - 4, self::BADGE_HEAD_H - 2, 0, 0 );
		$badge->compositeImage( $head, Imagick::COMPOSITE_OVER, 2, 2 );
		$head->clear();

		$r->text( $badge, OgImageRenderer::HEAD_BOLD, 19, OgImageRenderer::WHITE, (int) ( $w / 2 ), 26, $date['head'], Imagick::ALIGN_CENTER );

		if ( $range ) {
			$left  = (int) ( $w / 4 );
			$right = (int) ( 3 * $w / 4 );

			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 40, OgImageRenderer::TEXT, $left, self::BADGE_HEAD_H + 44, $date['day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 19, OgImageRenderer::MUTED, $left, self::BADGE_H - 16, $date['month'], Imagick::ALIGN_CENTER );

			$r->text( $badge, OgImageRenderer::BODY, 28, OgImageRenderer::MUTED, (int) ( $w / 2 ), self::BADGE_HEAD_H + 44, '–', Imagick::ALIGN_CENTER );

			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 40, OgImageRenderer::TEXT, $right, self::BADGE_HEAD_H + 44, $date['end_day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 19, OgImageRenderer::MUTED, $right, self::BADGE_H - 16, $date['end_month'], Imagick::ALIGN_CENTER );
		} else {
			$r->text( $badge, OgImageRenderer::HEAD_BOLD, 46, OgImageRenderer::TEXT, (int) ( $w / 2 ), self::BADGE_HEAD_H + 48, $date['day'], Imagick::ALIGN_CENTER );
			$r->text( $badge, OgImageRenderer::BODY_MEDIUM, 22, OgImageRenderer::MUTED, (int) ( $w / 2 ), self::BADGE_H - 16, $date['month'], Imagick::ALIGN_CENTER );
		}

		$canvas->compositeImage( $badge, Imagick::COMPOSITE_OVER, OgImageRenderer::PAD, self::BLOCK_TOP );
		$badge->clear();

		return $w;
	}

	/**
	 * Ročník a titulek ve sloupci vpravo od kartičky.
	 *
	 * Ročník je první řádek toho sloupce, tedy přímo nad titulkem a na stejné
	 * levé hraně. Bez ročníku řádek odpadá a titulek se posune nahoru na jeho
	 * místo.
	 */
	private function draw_title( Imagick $canvas, array $data, int $x ): void {
		$r   = $this->renderer;
		$max = OgImageRenderer::WIDTH - $x - OgImageRenderer::PAD;
		$top = self::BLOCK_TOP;

		if ( '' !== $data['eyebrow'] ) {
			$r->text(
				$canvas,
				OgImageRenderer::BODY_SEMI,
				self::EYEBROW_SIZE,
				OgImageRenderer::EYEBROW,
				$x,
				$top + 22,
				$r->truncate( $canvas, OgImageRenderer::BODY_SEMI, self::EYEBROW_SIZE, $data['eyebrow'], $max )
			);
			$top += 40;
		}

		$lines = $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES );

		foreach ( $lines as $i => $line ) {
			$r->text(
				$canvas,
				OgImageRenderer::HEAD_BOLD,
				self::TITLE_SIZE,
				OgImageRenderer::WHITE,
				$x,
				$top + 46 + self::TITLE_LEADING * $i,
				$line
			);
		}
	}

	/**
	 * Datový pás u dolní hrany — start, cíl, pořadatel.
	 *
	 * Sloupce bez dat se vynechají a zbylé se roztáhnou rovnoměrně. U části
	 * importovaných akcí chybí cíl a u 42 záznamů je prázdný čas startu,
	 * takže je to běžný stav, ne výjimka.
	 */
	private function draw_columns( Imagick $canvas, array $columns ): void {
		$r       = $this->renderer;
		$columns = array_values( array_filter( $columns, static fn( $c ) => '' !== $c['value'] ) );

		if ( empty( $columns ) ) {
			return;
		}

		$inner    = OgImageRenderer::WIDTH - 2 * OgImageRenderer::PAD;
		$count    = count( $columns );
		$width    = (int) ( ( $inner - self::COLUMN_GAP * ( $count - 1 ) ) / $count );
		$baseline = OgImageRenderer::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP;

		$r->rect( $canvas, OgImageRenderer::PAD, $baseline - 116, $inner, 1, 'rgba(255,255,255,0.18)' );

		foreach ( $columns as $i => $column ) {
			$x = OgImageRenderer::PAD + $i * ( $width + self::COLUMN_GAP );

			$r->text(
				$canvas,
				OgImageRenderer::BODY_BOLD,
				self::LABEL_SIZE,
				'rgba(255,255,255,0.62)',
				$x,
				$baseline - 76,
				$r->truncate( $canvas, OgImageRenderer::BODY_BOLD, self::LABEL_SIZE, $column['label'], $width )
			);

			$r->text(
				$canvas,
				OgImageRenderer::HEAD_MEDIUM,
				self::VALUE_SIZE,
				OgImageRenderer::WHITE,
				$x,
				$baseline - 36,
				$r->truncate( $canvas, OgImageRenderer::HEAD_MEDIUM, self::VALUE_SIZE, $column['value'], $width )
			);

			if ( '' !== $column['note'] ) {
				$r->text(
					$canvas,
					OgImageRenderer::BODY,
					self::NOTE_SIZE,
					'rgba(255,255,255,0.78)',
					$x,
					$baseline,
					$r->truncate( $canvas, OgImageRenderer::BODY, self::NOTE_SIZE, $column['note'], $width )
				);
			}
		}
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/EventCard.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/EventCard.php`

---

## Task 6: OgImageService — orchestrace

**Files:**
- Create: `src/Og/OgImageService.php`

- [ ] **Step 1: Vytvoř třídu**

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
 * Vrací null, kdykoli se cokoli nepovede — volající pak spadne na dnešní
 * chování. Sdílecí obrázek je ozdoba, ne funkce, a nesmí nic shodit.
 */
class OgImageService {

	public function __construct(
		private OgImageRenderer $renderer,
		private OgImageStore $store,
		private PostCard $post_card,
		private EventCard $event_card
	) {
	}

	/**
	 * @param array  $data   Data karty příspěvku (viz PostCard::render()).
	 * @param string $prefix Předpona názvu souboru, např. `post-12`.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function post( array $data, string $prefix ): ?array {
		return $this->image( $prefix, $data, fn() => $this->post_card->render( $data ) );
	}

	/**
	 * @param array  $data   Data karty akce (viz EventCard::render()).
	 * @param string $prefix Předpona názvu souboru, např. `akce-db-345`.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function event( array $data, string $prefix ): ?array {
		return $this->image( $prefix, $data, fn() => $this->event_card->render( $data ) );
	}

	/**
	 * @param callable():string $render Vrátí PNG blob.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	private function image( string $prefix, array $data, callable $render ): ?array {
		if ( ! $this->renderer->available() ) {
			return null;
		}

		$hash = $this->hash( $data );
		$url  = $this->store->url( $prefix, $hash );

		if ( null === $url ) {
			try {
				$url = $this->store->save( $render(), $prefix, $hash );
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
			'width'  => OgImageRenderer::WIDTH,
			'height' => OgImageRenderer::HEIGHT,
		);
	}

	/**
	 * Otisk vstupů karty.
	 *
	 * Do hashe jde všechno, co se kreslí, plus verze vykreslování. Změna
	 * obsahu i změna designu tak vyrobí jiný název souboru — starý se smaže
	 * a Facebook si sáhne pro nový, protože se změní i URL v og:image.
	 */
	private function hash( array $data ): string {
		return substr( sha1( wp_json_encode( $data ) . '|v' . OgImageRenderer::RENDER_VERSION ), 0, 12 );
	}
}
```

- [ ] **Step 2: Ověř syntaxi**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/Og/OgImageService.php
```

Očekávaný výstup: `No syntax errors detected in src/Og/OgImageService.php`

---

## Task 7: Feature OgImages — data a háky

**Files:**
- Create: `src/Features/OgImages.php`
- Modify: `src/Managers/FeaturesManager.php`

Jediné místo v celé věci, které zná WordPress. Skládá data pro karty a věší
se na háky.

- [ ] **Step 1: Vytvoř feature**

```php
<?php

namespace Kct\Features;

use Kct\Og\OgImageRenderer;
use Kct\Og\OgImageService;
use WP_Post;

/**
 * Sdílecí obrázky příspěvků a akcí.
 *
 * Skládá data pro karty z WordPressu a z pole akce, věší se na uložení
 * příspěvku a na filtry Rank Mathu. Samotné kreslení a ukládání je v Kct\Og.
 */
class OgImages {

	public function __construct( private OgImageService $service ) {
		add_action( 'save_post', array( $this, 'on_save' ), 20, 2 );

		// Filtry Rank Mathu jen pro příspěvky. `image`, ne `og_image`:
		// og_image filtruje až finální hodnotu tagu a ten se vypíše, jen když
		// Rank Math nějaký obrázek už našel — bez nalezeného obrázku by se
		// pozdní filtr nezavolal vůbec. `image` je dřívější filtr uvnitř
		// Image::add_image() a proběhne vždycky. Totéž zjištění má u sebe
		// Seo\RankMathOutput pro akce.
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'filter_rank_math_image' ) );
		add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'filter_rank_math_image' ) );
	}

	/**
	 * Vyrobí obrázek hned při uložení, ať na něj nečeká první návštěvník.
	 *
	 * @param int     $post_id ID příspěvku.
	 * @param WP_Post $post    Příspěvek.
	 */
	public function on_save( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'post' === $post->post_type ) {
			$this->for_post( (int) $post_id );
		}
	}

	public function filter_rank_math_image( $image ) {
		if ( ! is_singular( 'post' ) ) {
			return $image;
		}

		$own = $this->for_post( (int) get_the_ID() );

		return $own ? $own['url'] : $image;
	}

	/**
	 * Sdílecí obrázek příspěvku.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function for_post( int $post_id ): ?array {
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

		$data = array(
			'title'    => get_the_title( $post ),
			'category' => $category,
			'meta'     => sprintf(
				/* translators: 1: datum vydání, 2: doba čtení v minutách. */
				__( '%1$s   •   %2$d min čtení', 'kct' ),
				get_the_date( 'j. n. Y', $post ),
				$minutes
			),
			'photo'    => $this->thumbnail_path( $post_id ),
			'logo'     => $this->logo_path( true ),
		);

		return $this->service->post( $data, 'post-' . $post_id );
	}

	/**
	 * Sdílecí obrázek akce.
	 *
	 * @param array $event Pole akce z Features\Events::get_event().
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	public function for_event( array $event ): ?array {
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

		$range = ! empty( $formatted['is_range'] );

		$data = array(
			'title'   => $title,
			'eyebrow' => $this->year_label( $event ),
			'date'    => array(
				'is_range'  => $range,
				// Verzálky dělá na webu text-transform: uppercase, Imagick
				// nic takového nemá, takže se udělají tady.
				'head'      => mb_strtoupper( $this->text( $range ? ( $formatted['days_label'] ?? '' ) : ( $formatted['day_abbr'] ?? '' ) ), 'UTF-8' ),
				'day'       => $this->text( $formatted['day'] ?? '' ),
				'month'     => $this->text( $formatted['month'] ?? '' ),
				'end_day'   => $this->text( $formatted['end_day'] ?? '' ),
				'end_month' => $this->text( $formatted['end_month'] ?? '' ),
			),
			'columns' => $this->event_columns( $event ),
			'photo'   => $this->event_photo_path( $event ),
			// Vždy obecné logo KČT, nikdy custom_logo webu. Na oblastním webu
			// se vypisují akce odborů z celé oblasti, takže logo oblasti by
			// u cizího odboru tvrdilo něco, co není pravda. Stejné rozhodnutí
			// a stejné zdůvodnění má Seo\EventSeoData::fallback_image().
			'logo'    => $this->logo_path( false ),
		);

		return $this->service->event( $data, $this->event_prefix( $event ) );
	}

	/**
	 * Předpona názvu souboru akce.
	 *
	 * Akce z centrální databáze se klíčuje jejím db_id, i když k ní existuje
	 * příspěvek — je to jedna akce a má mít jeden obrázek. Bez db_id (ručně
	 * založená akce) se klíčuje ID příspěvku.
	 */
	private function event_prefix( array $event ): string {
		$db_id = (int) ( $event['db_id'] ?? 0 );

		return $db_id ? 'akce-db-' . $db_id : 'akce-' . (int) ( $event['id'] ?? 0 );
	}

	private function year_label( array $event ): string {
		$year = $event['year'] ?? '';

		if ( '' === $year || null === $year || 0 === (int) $year ) {
			return '';
		}

		/* translators: %d: pořadové číslo ročníku akce. */
		return sprintf( __( '%d. ročník', 'kct' ), (int) $year );
	}

	/**
	 * Start, cíl a pořadatel — totéž, co na detailu nesou infoboxy pod
	 * hlavičkou (template-parts/content-akce.php).
	 *
	 * @return array<int, array{label: string, value: string, note: string}>
	 */
	private function event_columns( array $event ): array {
		return array(
			$this->point_column( __( 'START', 'kct' ), $event['start'] ?? array() ),
			$this->point_column( __( 'CÍL', 'kct' ), $event['finish'] ?? array() ),
			array(
				'label' => __( 'POŘADATEL', 'kct' ),
				'value' => $this->text( $event['organiser']['name'] ?? '' ),
				'note'  => $this->place_note( $event ),
			),
		);
	}

	/**
	 * @param array $point Pole `start` nebo `finish` z akce.
	 *
	 * @return array{label: string, value: string, note: string}
	 */
	private function point_column( string $label, $point ): array {
		if ( ! is_array( $point ) ) {
			$point = array();
		}

		$date = $this->text( $point['date'] ?? '' );
		$time = $this->text( $point['time'] ?? '' );

		$value = '';

		if ( '' !== $date ) {
			$timestamp = strtotime( $date );
			$value     = $timestamp ? date_i18n( 'j. n.', $timestamp ) : '';

			if ( '' !== $value && '' !== $time ) {
				$value .= ' ' . $time;
			}
		}

		return array(
			'label' => $label,
			'value' => $value,
			'note'  => $this->text( $point['place'] ?? '' ),
		);
	}

	private function place_note( array $event ): string {
		$place    = $this->text( $event['place'] ?? '' );
		$district = $this->text( $event['district'] ?? '' );

		if ( '' !== $place && '' !== $district ) {
			/* translators: 1: místo konání, 2: okres. */
			return sprintf( __( '%1$s, okr. %2$s', 'kct' ), $place, $district );
		}

		if ( '' !== $district ) {
			/* translators: %s: okres. */
			return sprintf( __( 'okr. %s', 'kct' ), $district );
		}

		return $place;
	}

	/** Cesta k souboru náhledového obrázku příspěvku, nebo prázdný řetězec. */
	private function thumbnail_path( int $post_id ): string {
		$attachment_id = get_post_thumbnail_id( $post_id );

		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );

		return ( $path && is_readable( $path ) ) ? $path : '';
	}

	/**
	 * Cesta k fotce akce, nebo prázdný řetězec.
	 *
	 * Bere se jen soubor, který leží na disku téhle instalace. Obrázky
	 * z importu KČT jsou vzdálené URL a stahovat je kvůli textuře, která je
	 * stejně ztmavená na 18 % viditelnosti, nestojí za složitost — týká se to
	 * 6 akcí z 318 a bez fotky karta vypadá dobře.
	 */
	private function event_photo_path( array $event ): string {
		$url = $this->text( $event['image']['url'] ?? '' );

		if ( '' === $url ) {
			return '';
		}

		$attachment_id = attachment_url_to_postid( $url );

		if ( ! $attachment_id ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );

		return ( $path && is_readable( $path ) ) ? $path : '';
	}

	/**
	 * Cesta k logu.
	 *
	 * @param bool $site_logo Smí se použít custom_logo webu?
	 */
	private function logo_path( bool $site_logo ): string {
		if ( $site_logo ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );

			if ( $logo_id ) {
				$path = get_attached_file( $logo_id );

				// SVG se přeskočí — Imagick na produkci SVG delegáta nemá,
				// takže by se logo vykreslilo lokálně a na produkci ne.
				if ( $path && is_readable( $path ) && ! preg_match( '/\.svgz?$/i', $path ) ) {
					return $path;
				}
			}
		}

		$fallback = get_theme_file_path( 'images/kct_barva.png' );

		return is_readable( $fallback ) ? $fallback : '';
	}

	private function text( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
```

- [ ] **Step 2: Zaregistruj feature**

V `src/Managers/FeaturesManager.php` přidej import a parametr konstruktoru.
Výsledný soubor:

```php
<?php

namespace Kct\Managers;

use Kct\Features\DepartmentSeo;
use Kct\Features\Events;
use Kct\Features\EventSeo;
use Kct\Features\FacebookShare;
use Kct\Features\ImageMetadata;
use Kct\Features\ImageUploads;
use Kct\Features\Lightbox;
use Kct\Features\OgImages;
use Kct\Features\OpenGraph;

final class FeaturesManager {
	public function __construct(
		Events $events,
		// Roads $roads,   trasy vypnuté, viz PostTypesManager. Jediné, co dělal,
		//                 bylo povolení nahrávání GPX — to má i Frontend.
		FacebookShare $facebook_share,
		OpenGraph $open_graph,
		Lightbox $lightbox,
		EventSeo $event_seo,
		ImageMetadata $image_metadata,
		ImageUploads $image_uploads,
		DepartmentSeo $department_seo,
		OgImages $og_images
	) {
	}
}
```

- [ ] **Step 3: Ověř syntaxi a sestavení kontejneru**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Features/OgImages.php && php -l src/Managers/FeaturesManager.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: dvakrát `No syntax errors detected`, pak název webu.
Kdyby PHP-DI hlásilo, že nemůže sestavit `OgImages`, chybí smazání
`cache/kct`.

- [ ] **Step 4: Vyrenderuj první vzorek příspěvku**

Zapiš do `/Users/martin/Sites/sokct/ogtest.php`:

```php
<?php
$feature = kct_container()->get( \Kct\Features\OgImages::class );
$posts   = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish' ) );

foreach ( $posts as $post ) {
	$result = $feature->for_post( $post->ID );
	printf(
		"%-60s %s\n",
		mb_substr( $post->post_title, 0, 58 ),
		$result ? $result['url'] : 'NEVYROBENO'
	);
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test eval-file ogtest.php && rm ogtest.php
ls -la wp-content/uploads/kct-og/
```

Očekávaný výstup: tři řádky s URL končící `.png`, a v adresáři tři soubory.
Kdyby vypsalo `NEVYROBENO` u všech, chybí fonty (Task 1) nebo Imagick.

- [ ] **Step 5: Prohlédni vzorek**

```bash
cp /Users/martin/Sites/sokct/wp-content/uploads/kct-og/post-*.png \
   /private/tmp/claude-501/-Users-martin-Sites-sokct/569d1201-a09e-499b-b4ed-2439fb20d975/scratchpad/
```

Otevři soubory a zkontroluj: fotka vyplňuje celou plochu, dole je čitelný
titulek, nad ním modrý chip s kategorií, pod titulkem datum a doba čtení,
u dolní hrany trikolóra, vpravo nahoře logo. Nic nesmí přetékat mimo plochu.

---

## Task 8: Napojení na výstup

**Files:**
- Modify: `src/Seo/EventSeoData.php` (metoda `image()`)
- Modify: `src/Features/OpenGraph.php` (metoda `image_url()`)

Akce mají v kódu jediný průchod — `EventSeoData::image()` obsluhuje
`RankMathOutput::image_url()`, `StandaloneOutput::head()` i `event_schema()`.
Stačí do ní přidat dotaz na službu a `StandaloneOutput` ani `RankMathOutput`
se nemusí měnit vůbec.

- [ ] **Step 1: Přidej službu do EventSeoData**

`EventSeoData` dnes konstruktor nemá (ověřeno), takže se přidává celý. Vlož ho
do `src/Seo/EventSeoData.php` hned za deklaraci konstant, před první metodu:

```php
	public function __construct( private \Kct\Features\OgImages $og_images ) {
	}
```

Import se nepřidává — plně kvalifikovaný název v signatuře stačí a nemíchá se
tím `Kct\Seo` s `Kct\Features`.

PHP-DI autowiring si třídu sestaví sám a drží sdílené instance, takže
`OgImages` vznikne jednou a háky se zaregistrují jednou, i když si o něj řekne
`FeaturesManager` i `EventSeoData`.

- [ ] **Step 2: Uprav EventSeoData::image()**

Nahraď tělo metody `image()`:

```php
	public function image( array $event ): array {
		// Vlastní sdílecí obrázek má přednost před fotkou z importu i před
		// logem — nese titulek, datum a data akce, takže odkaz na sociální
		// síti vypadá jako akce, ne jako obrázek.
		//
		// Navíc u něj známe rozměry. Fotka z importu je vzdálená URL a
		// width/height jsou nula, takže StandaloneOutput og:image:width vůbec
		// nevypíše a Facebook náhled při prvním sdílení nevykreslí, dokud si
		// obrázek sám nestáhne.
		$own = $this->og_images->for_event( $event );

		if ( $own ) {
			return $own;
		}

		$url = $this->text( $event['image']['url'] ?? '' );

		if ( $url ) {
			return array(
				'url'    => $url,
				'width'  => 0,
				'height' => 0,
			);
		}

		return $this->fallback_image();
	}
```

- [ ] **Step 3: Uprav OpenGraph::image_url()**

V `src/Features/OpenGraph.php` přidej službu do konstruktoru:

```php
	/**
	 * @param Credentials $credentials Konfigurace sdílení, odsud se čte výchozí obrázek.
	 * @param OgImages    $og_images   Vlastní sdílecí obrázky.
	 */
	public function __construct(
		private Credentials $credentials,
		private OgImages $og_images
	) {
		add_action( 'wp_head', array( $this, 'render' ), 5 );
	}
```

`use` se nepřidává: `OpenGraph` i `OgImages` jsou oba v namespace
`Kct\Features`, takže holý název stačí.

Metodu `image_url()` nahraď:

```php
	private function image_url( ?WP_Post $post ): ?array {
		// Vlastní sdílecí obrázek příspěvku má přednost — nese titulek,
		// kategorii a datum, na rozdíl od holého náhledu.
		if ( $post instanceof WP_Post && 'post' === $post->post_type ) {
			$own = $this->og_images->for_post( (int) $post->ID );

			if ( $own ) {
				return array( $own['url'], $own['width'], $own['height'] );
			}
		}

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
```

- [ ] **Step 4: Ověř syntaxi a sestavení**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct
php -l src/Seo/EventSeoData.php && php -l src/Features/OpenGraph.php
rm -rf /Users/martin/Sites/sokct/wp-content/cache/kct
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test option get blogname
```

Očekávaný výstup: dvakrát `No syntax errors detected`, pak název webu.

- [ ] **Step 5: Ověř og:image na živé stránce**

```bash
cd /Users/martin/Sites/sokct
SLUG=$(ddev wp --url=https://sokct.test post list --post_type=post --post_status=publish --posts_per_page=1 --field=url)
echo "$SLUG"
curl -sk "$SLUG" | grep -oE '<meta property="og:image[^>]*>'
```

Očekávaný výstup: `og:image` míří na `/wp-content/uploads/kct-og/post-<ID>-<hash>.png`
a jsou tam i `og:image:width` `1200` a `og:image:height` `630`.

- [ ] **Step 6: Ověř og:image u akce z databáze**

```bash
cd /Users/martin/Sites/sokct
DB_ID=$(ddev wp --url=https://sokct.test db query \
  "SELECT db_id FROM wp_db_events ORDER BY date DESC LIMIT 1" --skip-column-names)
echo "db_id: $DB_ID"
curl -sk "https://sokct.test/akce-db/$DB_ID" | grep -oE '<meta property="og:image[^>]*>'
```

Očekávaný výstup: `og:image` míří na `/wp-content/uploads/kct-og/akce-db-<db_id>-<hash>.png`,
plus width 1200 a height 630. Tím se zároveň ověřuje, že se obrázek vyrobil
při zobrazení detailu — virtuální akce nemá příspěvek a žádný hák na uložení.

- [ ] **Step 7: Prohlédni vzorek akce**

```bash
cp /Users/martin/Sites/sokct/wp-content/uploads/kct-og/akce-*.png \
   /private/tmp/claude-501/-Users-martin-Sites-sokct/569d1201-a09e-499b-b4ed-2439fb20d975/scratchpad/
```

Zkontroluj: datumová kartička vlevo nahoře, vedle ní ročník a pod ním titulek
na stejné levé hraně, dole tři sloupce start / cíl / pořadatel oddělené linkou,
u dolní hrany trikolóra.

---

## Task 9: WP-CLI příkaz

**Files:**
- Modify: `src/CLI.php`

- [ ] **Step 1: Přidej příkaz**

Na konec třídy `Kct\CLI` (před uzavírací závorku) přidej:

```php
	/**
	 * Vyrobí sdílecí obrázky příspěvků a akcí.
	 *
	 * Ve výchozím stavu jen vypíše, co by udělal. Prochází všechny weby v síti.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Opravdu obrázky vyrobit a uložit.
	 *
	 * [--site=<id>]
	 * : Jen jeden web sítě.
	 *
	 * ## EXAMPLES
	 *
	 *     wp kct og_images
	 *     wp kct og_images --write
	 *     wp kct og_images --write --site=1
	 */
	public function og_images( $args, $assoc_args ) {
		$write = ! empty( $assoc_args['write'] );
		$only  = isset( $assoc_args['site'] ) ? (int) $assoc_args['site'] : 0;
		$sites = $only ? array( $only ) : get_sites( array( 'number' => 0, 'fields' => 'ids' ) );

		WP_CLI::log( $write
			? __( 'Vyrábím sdílecí obrázky …', 'kct' )
			: __( 'Nanečisto (vyrobení zapneš přepínačem --write) …', 'kct' ) );

		$total = 0;
		$failed = 0;

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			$og     = kct_container()->get( \Kct\Features\OgImages::class );
			$events = kct_container()->get( Events::class );
			$made   = 0;

			$post_ids = get_posts( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			foreach ( $post_ids as $post_id ) {
				if ( ! $write ) {
					$made++;
					continue;
				}

				if ( $og->for_post( (int) $post_id ) ) {
					$made++;
				} else {
					$failed++;
				}
			}

			foreach ( $events->get_events() as $event ) {
				if ( ! $write ) {
					$made++;
					continue;
				}

				if ( $og->for_event( $event ) ) {
					$made++;
				} else {
					$failed++;
				}
			}

			if ( $made ) {
				WP_CLI::log( sprintf(
					'  %-30s %5d',
					parse_url( get_home_url( $site_id ), PHP_URL_HOST ),
					$made
				) );
			}

			$total += $made;

			restore_current_blog();
		}

		if ( $failed ) {
			WP_CLI::log( sprintf(
				/* translators: %d: počet objektů. */
				__( 'U %d objektů se obrázek nevyrobil (chybí datum nebo fotka se nedá načíst).', 'kct' ),
				$failed
			) );
		}

		$message = sprintf(
			/* translators: %d: počet obrázků. */
			__( 'Obrázků: %d.', 'kct' ),
			$total
		);

		if ( $write ) {
			WP_CLI::success( $message );
		} else {
			WP_CLI::log( $message . ' ' . __( 'Nic se nezapsalo.', 'kct' ) );
		}
	}
```

- [ ] **Step 2: Ověř syntaxi a suchý běh**

```bash
cd /Users/martin/Sites/sokct/wp-content/plugins/kct && php -l src/CLI.php
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test kct og_images
```

Očekávaný výstup: hlášku o suchém běhu, řádky s doménami a počty, a na konci
`Nic se nezapsalo.` V adresáři `wp-content/uploads/kct-og/` nesmí přibýt
jediný soubor.

- [ ] **Step 3: Pusť naostro a změř**

```bash
cd /Users/martin/Sites/sokct
time ddev wp --url=https://sokct.test kct og_images --write
du -sh wp-content/uploads/kct-og
ls wp-content/uploads/kct-og | wc -l
```

Očekávaný výstup: `Success: Obrázků: …`, adresář o velikosti řádově jednotek
až desítek MB. Zapiš si čas — u 318 akcí a 48 příspěvků na síti je to první
skutečné měření, kolik render stojí.

Poznámka k počtu: `Events::get_events()` bez argumentů vrací akce podle
nastavení webu (filtr `id_code` / `code_type`), tedy přesně to, co web sám
vypisuje. Příkaz proto pokrývá akce, které web opravdu publikuje — ne nutně
všech 318 řádků tabulky. To je záměr: obrázek pro akci, kterou web nevypisuje,
by nikdo nesdílel.

---

## Task 10: Vizuální doladění a ověření na produkci

**Files:**
- Modify: `src/Og/PostCard.php` (velikosti písma, pokud je potřeba)
- Modify: `src/Og/EventCard.php` (velikosti písma, pokud je potřeba)
- Modify: `src/Og/OgImageRenderer.php` (`RENDER_VERSION`, pokud se něco změní)

Velikosti písma v plánu jsou první odhad. Facebook zobrazuje náhled ve feedu
kolem 500 px širokého, takže se obrázek zmenšuje 2,4× a text pod zhruba 28 px
ve zdroji je na hraně čitelnosti.

- [ ] **Step 1: Vyrob okrajové případy**

Zapiš do `/Users/martin/Sites/sokct/ogedge.php`:

```php
<?php
$og     = kct_container()->get( \Kct\Features\OgImages::class );
$events = kct_container()->get( \Kct\Features\Events::class )->get_events();

$cases = array(
	'bez fotky'        => static fn( $e ) => empty( $e['image']['url'] ),
	's fotkou'         => static fn( $e ) => ! empty( $e['image']['url'] ),
	'bez cíle'         => static fn( $e ) => empty( $e['finish']['date'] ),
	'bez času startu'  => static fn( $e ) => ! empty( $e['start']['date'] ) && empty( $e['start']['time'] ),
	'vícedenní'        => static fn( $e ) => ! empty( $e['formated_date']['is_range'] ),
	'dlouhý titulek'   => static fn( $e ) => mb_strlen( $e['title'] ?? '' ) > 55,
	'bez ročníku'      => static fn( $e ) => empty( $e['year'] ),
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
		printf( "%-18s — žádná taková akce\n", $name );
		continue;
	}

	$result = $og->for_event( $found );
	printf( "%-18s %s\n", $name, $result ? basename( $result['url'] ) : 'NEVYROBENO' );
}
```

Spusť:

```bash
cd /Users/martin/Sites/sokct && ddev wp --url=https://sokct.test eval-file ogedge.php && rm ogedge.php
```

Očekávaný výstup: u každého případu název souboru, nebo poznámka, že taková
akce v datech není. `NEVYROBENO` znamená chybu — podívej se do
`wp-content/debug.log`.

- [ ] **Step 2: Zmenši vzorky na šířku, v jaké je ukazuje Facebook**

```bash
S=/private/tmp/claude-501/-Users-martin-Sites-sokct/569d1201-a09e-499b-b4ed-2439fb20d975/scratchpad
mkdir -p $S/og-500
cd /Users/martin/Sites/sokct
for f in wp-content/uploads/kct-og/*.png; do
  ddev exec convert "/var/www/html/$f" -resize 500x "/var/www/html/wp-content/uploads/kct-og-500-$(basename $f)"
done
mv wp-content/uploads/kct-og-500-*.png $S/og-500/
ls $S/og-500 | head
```

- [ ] **Step 3: Posuď čitelnost a dolaď**

Prohlédni obrázky ve složce `og-500`. Rozhodovací otázka je jediná: **dá se
přečíst datový pás akce (popisky, hodnoty, doplňky) a meta řádek příspěvku?**

Když ne, zvětši v `EventCard` konstanty `LABEL_SIZE`, `VALUE_SIZE`, `NOTE_SIZE`
a v `PostCard` konstantu `META_SIZE`, a **zvyš `OgImageRenderer::RENDER_VERSION`
o jedna**, jinak se stará podoba bude dál servírovat z disku. Pak znovu:

```bash
cd /Users/martin/Sites/sokct
rm -rf wp-content/cache/kct
ddev wp --url=https://sokct.test kct og_images --write
```

Opakuj, dokud to nesedí. Tohle je jediný krok plánu, který se dělá okem —
naměřit se nedá.

- [ ] **Step 4: Ukliď staré soubory po změnách verze**

```bash
cd /Users/martin/Sites/sokct && ls wp-content/uploads/kct-og | wc -l
```

Počet souborů musí odpovídat počtu příspěvků a akcí, ne jejich násobku.
Kdyby jich bylo víc, `OgImageStore::prune()` nemaže — zkontroluj, že předpona
v `event_prefix()` a `for_post()` sedí s maskou v `prune()`.

- [ ] **Step 5: Zkontroluj oprávnění**

```bash
cd /Users/martin/Sites/sokct && ls -la wp-content/uploads/kct-og | head -3
```

Soubory musí patřit stejnému uživateli jako zbytek `uploads` a být čitelné
(644). Vygenerovaly se z PHP, takže by to mělo sedět samo — kontrola je tu
proto, že se v tomhle projektu už jednou stalo, že root proces nechal
v `uploads` soubory, do kterých WordPress nemohl.

- [ ] **Step 6: Ověř na produkci po nasazení**

Nasazení dělá Martin. Po něm:

```bash
curl -s "https://sokct.cz/?nocache=$RANDOM" -o /dev/null
POST=$(curl -s "https://sokct.cz/?nocache=$RANDOM" | grep -oE 'https://sokct\.cz/[a-z0-9-]+/' | head -1)
curl -s "$POST?nocache=$RANDOM" | grep -oE '<meta property="og:image[^>]*>'
```

Očekávaný výstup: `og:image` míří na `kct-og/post-…png`, width 1200,
height 630. Pak si ověř, že soubor opravdu existuje:

```bash
IMG=$(curl -s "$POST?nocache=$RANDOM" | grep -oE 'https://[^"]*kct-og/[^"]*\.png' | head -1)
curl -s -o /dev/null -w "%{http_code}  %{size_download} B\n" "$IMG"
```

Očekávaný výstup: `200` a velikost řádově stovky kB.

- [ ] **Step 7: Facebook Sharing Debugger**

Vlož adresu příspěvku do https://developers.facebook.com/tools/debug/ a dej
„Scrape Again". Náhled musí ukázat vygenerovaný obrázek a v seznamu vlastností
`og:image:width` 1200 a `og:image:height` 630. Tohle je poslední kontrola —
Facebook má vlastní pravidla a co projde v `curl`, nemusí projít u něj.

---

## Poznámky k údržbě

**Změna vzhledu karty** znamená zvýšit `OgImageRenderer::RENDER_VERSION`.
Bez toho se dál servírují staré soubory, protože se existence souboru bere
jako „hotovo". Je to jediná věc, na kterou se dá v tomhle kódu zapomenout
a projeví se to tím, že „se změna neprojevila".

**Nový typ obsahu** (třeba odbory) znamená novou kartu v `Kct\Og` a novou
metodu ve `Features\OgImages`. Renderer, úložiště ani služba se nemění.

**Když se zapne SEO plugin na dalším webu**, `OpenGraph::render()` se sám
vypne (`has_seo_plugin()`) a obrázek začnou vypisovat filtry Rank Mathu
registrované ve `Features\OgImages`. Není co nastavovat.
