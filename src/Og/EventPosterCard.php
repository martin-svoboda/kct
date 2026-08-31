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

	/**
	 * Svislá hranice mezi grafickou plochou a světlým panelem.
	 *
	 * Výš než u karty příspěvku (810): panel nese při plných datech tři řádky
	 * s poznámkami a ještě tři řádky s délkami tras, a po zvětšení písma se to
	 * do 540 px nevešlo — poslední poznámka narážela do linky nad trasami.
	 */
	private const PANEL_TOP = 700;

	private const QUALITY = 88;

	private const TITLE_SIZE    = 62;
	private const TITLE_LEADING = 72;
	private const TITLE_LINES   = 2;
	private const TITLE_DESCENT = 16;

	private const EYEBROW_SIZE = 27;
	private const EYEBROW_GAP  = 70;

	/**
	 * Mezera mezi spodní hranou bloku s titulkem a horní hranou panelu.
	 *
	 * Blok sahá do spodní části mapy — záměrně. Dřív visel v pruhu mezi mapou
	 * a panelem, kde nepatřil ani k jednomu.
	 */
	private const BLOCK_GAP = 46;

	private const BADGE_W       = 128;
	private const BADGE_W_RANGE = 190;
	private const BADGE_H       = 156;
	private const BADGE_HEAD_H  = 46;
	private const BADGE_RADIUS  = 14;
	private const BADGE_GAP     = 30;

	/** Výška pásu s mapou; 800×400 roztažené na šířku plátna. */
	private const MAP_HEIGHT = 540;

	/** Přes kolik pixelů se spodní okraj mapy vytrácí do pozadí. */
	private const MAP_FADE = 170;

	private const ICON_SIZE = 36;
	private const ICON_PAD  = 8;
	private const ICON_GAP  = 6;
	private const ICON_MAX  = 4;

	private const LABEL_SIZE = 26;
	private const VALUE_SIZE = 40;
	private const NOTE_SIZE  = 32;

	/** Šířka sloupce s popiskem v panelu; hodnota začíná za ním. */
	private const LABEL_COLUMN = 250;

	private const ROUTE_SIZE = 28;
	private const ROUTE_ICON = 40;
	private const ROUTE_STEP = 52;
	private const ROUTE_MAX  = 3;

	public function __construct( private OgImageRenderer $renderer ) {
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
	 *     map: string,
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
		$this->draw_background( $canvas, $data['map'] );

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
	 * Pozadí horní plochy — mapa akce, když ji máme.
	 *
	 * Mapy se generují z mapy.cz při zobrazení detailu akce a leží
	 * v uploads/maps. Souřadnice má 279 akcí z 319 a všechny svoji mapu na
	 * disku mají, takže se nic nestahuje — co tam není, prostě není.
	 *
	 * Mapa je 800×400. Na plnou výšku plochy by se musela zvětšit
	 * dvojnásobně a popisky by se rozmazaly, proto se roztáhne jen na šířku
	 * (1,35×) a zbytek plochy pod ní dokreslí přechod. Spodní okraj mapy se
	 * do něj vytratí, aby tam nebyl viditelný šev.
	 *
	 * Ztmavení je kvůli tomu, že na mapě leží ikony, logo i titulek —
	 * na světlé mapě s popisky by bílý text nebyl čitelný.
	 */
	private function draw_background( Imagick $canvas, string $map ): void {
		$r     = $this->renderer;
		$image = '' !== $map ? $r->photo( $map, self::WIDTH, self::MAP_HEIGHT ) : null;

		if ( null === $image ) {
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::PANEL_TOP, 0 );

			return;
		}

		$canvas->compositeImage( $image, Imagick::COMPOSITE_OVER, 0, 0 );
		$image->clear();

		$r->rect( $canvas, 0, 0, self::WIDTH, self::MAP_HEIGHT, 'rgba(13,25,38,0.66)' );
		$r->gradient( $canvas, 'transparent', OgImageRenderer::INK, self::MAP_FADE, self::MAP_HEIGHT - self::MAP_FADE );
		$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::PANEL_TOP - self::MAP_HEIGHT, self::MAP_HEIGHT );
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

			$y += 46;

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
				$y += 44;
			}

			$y += 16;
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
