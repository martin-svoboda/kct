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
class EventCard implements Card {

	public const WIDTH  = 1200;
	public const HEIGHT = 630;

	private const TITLE_SIZE    = 68;
	private const TITLE_LEADING = 78;
	private const TITLE_LINES   = 2;

	/** Kolik zbývá pod účařím posledního řádku titulku na dotažnice. */
	private const TITLE_DESCENT = 16;

	private const EYEBROW_SIZE  = 27;

	/** Vzdálenost účaří ročníku nad účaří prvního řádku titulku. */
	private const EYEBROW_GAP   = 76;

	private const BADGE_W       = 128;
	private const BADGE_W_RANGE = 190;
	private const BADGE_H       = 156;
	private const BADGE_HEAD_H  = 46;
	private const BADGE_RADIUS  = 14;
	private const BADGE_GAP     = 30;

	/** Mezera mezi spodní hranou bloku s titulkem a linkou datového pásu. */
	private const BLOCK_GAP     = 34;

	private const LABEL_SIZE    = 20;
	private const VALUE_SIZE    = 30;
	private const NOTE_SIZE     = 24;
	private const COLUMN_GAP    = 32;

	/** Odstup linky datového pásu od účaří posledního řádku sloupců. */
	private const DIVIDER_OFFSET = 116;

	private const ICON_SIZE = 36;
	private const ICON_PAD  = 8;
	private const ICON_GAP  = 6;

	/**
	 * Nejvíc ikon v bloku.
	 *
	 * Akce jich má obvykle jednu až dvě, ale data z centrální databáze nic
	 * neomezují — bez stropu by dlouhá řada přerostla přes logo.
	 */
	private const ICON_MAX = 4;

	public function __construct( private OgImageRenderer $renderer ) {
	}

	/**
	 * @param array{
	 *     title: string,
	 *     eyebrow: string,
	 *     date: array{head: string, day: string, month: string, end_day: string, end_month: string, is_range: bool},
	 *     columns: array<int, array{label: string, value: string, note: string}>,
	 *     icons: string[],
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
		$this->draw_icons( $canvas, $data['icons'] );

		$badge_w = ! empty( $data['date']['is_range'] ) ? self::BADGE_W_RANGE : self::BADGE_W;
		$x       = OgImageRenderer::PAD + $badge_w + self::BADGE_GAP;
		$max     = self::WIDTH - $x - OgImageRenderer::PAD;

		$lines   = $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES );
		$eyebrow = $this->renderer->truncate( $canvas, OgImageRenderer::BODY_SEMI, self::EYEBROW_SIZE, $data['eyebrow'], $max );

		// Blok s datem, ročníkem a titulkem se kotví ZESPODU — sedí těsně nad
		// linkou datového pásu, kartička i text na téže spodní hraně. Kdyby se
		// kotvil shora pevnou souřadnicí, měnil by se odstup od linky podle
		// toho, jestli má akce ročník a jestli se titulek vejde na jeden řádek
		// nebo na dva.
		$bottom = $this->divider_y() - self::BLOCK_GAP;

		$this->draw_badge( $canvas, $data['date'], $badge_w, $bottom - self::BADGE_H );
		$this->draw_title( $canvas, $eyebrow, $lines, $x, $bottom );
		$this->draw_columns( $canvas, $data['columns'] );

		$r->strip( $canvas );

		return $r->png( $canvas );
	}

	/**
	 * Ikony typů akce v bílém zaobleném bloku vlevo nahoře.
	 *
	 * Piktogramy z centrální databáze jsou černá kresba na bílé bez alfa
	 * kanálu, takže bílý blok pod nimi není jen ozdoba — bez něj by na tmavém
	 * pozadí karty zůstal kolem každé ikony bílý čtverec.
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
			// Ikony nejsou přesně čtvercové (29×30), po zmenšení se tedy
			// doprostřed políčka dorovnají, ať řada nepoletuje.
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

	/** Svislá souřadnice linky nad datovým pásem. */
	private function divider_y(): int {
		return self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP - self::DIVIDER_OFFSET;
	}

	private function background( string $photo ): Imagick {
		$r = $this->renderer;

		$canvas = '' !== $photo ? $r->photo( $photo, self::WIDTH, self::HEIGHT ) : null;

		if ( null === $canvas ) {
			$canvas = $r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK );
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::HEIGHT, 0 );

			return $canvas;
		}

		// Fotka jen jako textura: sytost na 35 % a tma přes celou plochu.
		// Zůstane z ní tvar a nálada, ne detail, který by konkuroval datům.
		//
		// Tma není plochá, ale přechod — nahoře 80 %, dole 100 %. Dole leží
		// datový pás a titulek, tam musí být podklad úplně klidný; nahoře
		// naopak nic není, tak se fotka nechá prosvítat.
		$canvas->modulateImage( 100, 35, 100 );
		$r->gradient( $canvas, 'rgba(13,25,38,0.80)', 'rgba(13,25,38,1.0)', self::HEIGHT, 0 );

		return $canvas;
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
		// a přiříznutý plným obdélníkem, takže zaoblené jsou jen horní rohy —
		// stejně jako .event-date__head s overflow: hidden na rodiči.
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
	 * Ročník je první řádek toho sloupce, tedy přímo nad titulkem a na stejné
	 * levé hraně. Bez ročníku řádek odpadá a titulek klesne na jeho místo —
	 * spodní hrana zůstává, protože blok se kotví zespodu.
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

			$r->text(
				$canvas,
				OgImageRenderer::BODY_SEMI,
				self::EYEBROW_SIZE,
				OgImageRenderer::EYEBROW,
				$x,
				$first - self::EYEBROW_GAP,
				$eyebrow
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

		$inner    = self::WIDTH - 2 * OgImageRenderer::PAD;
		$count    = count( $columns );
		$width    = (int) ( ( $inner - self::COLUMN_GAP * ( $count - 1 ) ) / $count );
		$baseline = self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP;

		$r->rect( $canvas, OgImageRenderer::PAD, $this->divider_y(), $inner, 1, 'rgba(255,255,255,0.18)' );

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

	public function width(): int {
		return self::WIDTH;
	}

	public function height(): int {
		return self::HEIGHT;
	}

	public function extension(): string {
		return 'png';
	}
}
