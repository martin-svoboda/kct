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
	public const RENDER_VERSION = 13;

	public const PAD    = 48;
	public const STRIP  = 8;

	/** Výška loga v pravém horním rohu. */
	public const LOGO_HEIGHT = 112;

	/** Svislá souřadnice horní hrany loga i bloku s ikonami. */
	public const TOP = 40;

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

	/**
	 * Malá ikona načtená ze souboru a zmenšená do čtverce, nebo null.
	 *
	 * Ikony typů akcí jsou PNG bez alfa kanálu, kreslené černě na bílou —
	 * proto se srovnávají na bílé pozadí, ne na tmu karty.
	 */
	public function icon( string $path, int $size ): ?Imagick {
		try {
			$icon = new Imagick( $path );
			$icon->setImageBackgroundColor( new ImagickPixel( self::WHITE ) );
			$icon = $icon->flattenImages();
			$icon->thumbnailImage( $size, $size, true );
			$icon->setImageFormat( 'png' );

			return $icon;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/** Logo vpravo nahoře. Bez loga karta funguje dál, proto se chyba polyká. */
	public function logo( Imagick $canvas, string $path, int $height = self::LOGO_HEIGHT ): void {
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

			$canvas->compositeImage( $logo, Imagick::COMPOSITE_OVER, $canvas->getImageWidth() - self::PAD - $w, self::TOP );
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
}
