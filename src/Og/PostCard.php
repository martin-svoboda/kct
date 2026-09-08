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
class PostCard implements Card {

	public const WIDTH  = 1200;
	public const HEIGHT = 630;

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

		$canvas = '' !== $data['photo'] ? $r->photo( $data['photo'], self::WIDTH, self::HEIGHT ) : null;

		if ( null === $canvas ) {
			// Bez fotky (nebo když se nedá načíst) stejné grafické pozadí
			// jako u akcí, ať karta nikdy nevyjde jako prázdná plocha.
			$canvas = $r->canvas( self::WIDTH, self::HEIGHT, OgImageRenderer::INK );
			$r->gradient( $canvas, OgImageRenderer::INK, OgImageRenderer::INK_LIGHT, self::HEIGHT, 0 );
		}

		// Spodní ztmavení přes 60 % výšky, jako .post-hero__overlay.
		$shade = (int) round( self::HEIGHT * 0.6 );
		$r->gradient( $canvas, 'transparent', 'rgba(13,25,38,0.92)', $shade, self::HEIGHT - $shade );

		// Horní ztmavení, aby logo drželo i na světlé fotce.
		$r->gradient( $canvas, 'rgba(13,25,38,0.72)', 'transparent', 150, 0 );

		$r->logo( $canvas, $data['logo'] );

		$x   = OgImageRenderer::PAD;
		$max = self::WIDTH - 2 * OgImageRenderer::PAD;

		$meta_baseline = self::HEIGHT - OgImageRenderer::PAD - OgImageRenderer::STRIP;
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
			$top   = $title_bottom - self::TITLE_LEADING * count( $lines ) - 34;

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
