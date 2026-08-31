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

	private const TITLE_SIZE    = 96;
	private const TITLE_LEADING = 106;
	private const TITLE_LINES   = 3;

	private const META_SIZE = 30;

	/** Odsazení chipu kategorie od horní hrany panelu. */
	private const CHIP_TOP = 40;

	/**
	 * Mezera pod chipem.
	 *
	 * Drží se zvlášť od CHIP_TOP, aby posun chipu nehnul titulkem — součet
	 * obou je vzdálenost horní hrany panelu od začátku titulku.
	 */
	private const CHIP_BOTTOM = 50;

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
		}

		// Ztmavení pod logem, aby drželo i na světlé fotce.
		$r->gradient( $canvas, 'rgba(13,25,38,0.72)', 'transparent', 200, 0 );
		$r->logo( $canvas, $data['logo'] );

		// Barevný panel, ne bílý: titulek na značkové modré drží pozornost
		// líp než černý text na bílé a karta se ve feedu pozná na první pohled.
		$r->rect( $canvas, 0, self::PANEL_TOP, self::WIDTH, self::HEIGHT - self::PANEL_TOP, OgImageRenderer::BLUE );

		$x   = OgImageRenderer::PAD;
		$max = self::WIDTH - 2 * OgImageRenderer::PAD;

		$top = self::PANEL_TOP + self::CHIP_TOP;

		if ( '' !== $data['category'] ) {
			$label = $r->truncate( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $data['category'], 460 );
			$width = $r->width( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, $label );

			// Na modrém panelu musí být chip bílý s modrým textem — modrý na
			// modrém by zmizel.
			$chip = $r->rounded_rect( $width + 2 * self::CHIP_PAD, self::CHIP_HEIGHT, 6, OgImageRenderer::WHITE );
			$canvas->compositeImage( $chip, Imagick::COMPOSITE_OVER, $x, $top );
			$chip->clear();

			$r->text( $canvas, OgImageRenderer::BODY_BOLD, self::CHIP_SIZE, OgImageRenderer::BLUE, $x + self::CHIP_PAD, $top + 32, $label );

			$top += self::CHIP_HEIGHT + self::CHIP_BOTTOM;
		}

		foreach ( $r->wrap( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, $data['title'], $max, self::TITLE_LINES ) as $i => $line ) {
			$r->text( $canvas, OgImageRenderer::HEAD_BOLD, self::TITLE_SIZE, OgImageRenderer::WHITE, $x, $top + 64 + self::TITLE_LEADING * $i, $line );
		}

		// Meta řádek se kotví ke spodní hraně, ne pod titulek — jinak by
		// u jednořádkového titulku zůstal viset uprostřed panelu.
		$r->text(
			$canvas,
			OgImageRenderer::BODY_MEDIUM,
			self::META_SIZE,
			'rgba(255,255,255,0.82)',
			$x,
			self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP,
			$r->truncate( $canvas, OgImageRenderer::BODY_MEDIUM, self::META_SIZE, $data['meta'], $max )
		);

		$r->strip( $canvas );

		return $r->jpeg( $canvas, self::QUALITY );
	}
}
