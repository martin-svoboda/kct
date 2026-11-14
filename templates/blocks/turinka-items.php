<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * Výpis tras nebo míst z Turinky.
 *
 * Karta ukazuje všechno, co nese výpis API — nic se nedopočítává a nikam se
 * nechodí pro detail: výpis je na kartu udělaný. U trasy to je mapa se stopou,
 * druhy aktivity, délka, převýšení, odhad času, obtížnost, výškový profil,
 * vhodnost a sezónnost; u místa fotka, kategorie, přístupnost, obec a okres.
 *
 * **Odkaz vede mimo web, do aplikace — a je to na kartě vidět:** ikona u názvu
 * a věta „Otevře se v Turince“ v patičce karty. Trasy a místa nemají na webu
 * vlastní stránku, takže není kam jinam odkázat, a návštěvník má dopředu
 * vědět, že odchází.
 *
 * Prázdný výpis nevypíše nic. Blok vložený „do zásoby“ (než odbor založí první
 * trasu) nesmí na stránce zanechat prázdný rámeček ani hlášku o chybě.
 *
 * @var $args array Template arguments
 */

use Kct\Turinka\Field;
use Kct\Turinka\Image;
use Kct\Turinka\Profile;

$kct_items   = (array) ( $args['items'] ?? array() );
$kct_type    = (string) ( $args['type'] ?? 'routes' );
$kct_heading = trim( (string) ( $args['heading'] ?? '' ) );
$kct_routes  = 'routes' === $kct_type;

if ( ! $kct_items ) {
	return;
}

/** Adresa aplikace; z ní se skládá logo i odkaz pod výpisem. */
$kct_app = kct_container()->get( \Kct\Turinka\Config::class )->public_url();

/** Logo aplikace do patičky karty — tentýž soubor, jaký má vkládaný widget. */
$kct_logo = $kct_app . '/images/turi_kct_logo.svg';

/** Ikona odchozího odkazu; stejná u názvu i v patičce karty. */
$kct_external = '<svg class="turinka-out" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6"></path><path d="M20 4 11 13"></path><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"></path></svg>';
?>
<div class="kct-block block-turinka block-turinka--<?php echo esc_attr( $kct_type ); ?>">
	<?php if ( '' !== $kct_heading ) : ?>
		<h2 class="wp-block-heading"><?php echo esc_html( $kct_heading ); ?></h2>
	<?php endif; ?>

	<div class="articles_grid">
		<?php
		foreach ( $kct_items as $kct_item ) :
			$kct_url   = (string) ( $kct_item['url'] ?? '' );
			$kct_title = (string) ( $kct_item['title'] ?? '' );

			// U trasy je obrázek mapy se stopou výmluvnější než fotka, a je
			// v odpovědi vždycky; fotka u trasy bývá prázdná.
			$kct_picture = $kct_routes
				? ( $kct_item['map_image'] ?? $kct_item['featured_image'] ?? null )
				: ( $kct_item['featured_image'] ?? null );

			// Do karty široké kolem 400 px patří zmenšenina, ne originál:
			// mapa trasy má v plné velikosti přes megabajt.
			$kct_image       = Image::url( $kct_picture, 'medium' );
			$kct_attribution = Image::attribution( $kct_picture );

			// Měřitelné údaje na jeden řádek pod název.
			$kct_meta = array();

			if ( $kct_routes ) {
				$kct_km = (float) ( $kct_item['length_km'] ?? 0 );

				if ( $kct_km > 0 ) {
					/* translators: %s: délka trasy v kilometrech. */
					$kct_meta[] = sprintf( __( '%s km', 'kct' ), number_format_i18n( $kct_km, 1 ) );
				}

				$kct_gain = (int) ( $kct_item['elevation_gain_m'] ?? 0 );
				$kct_loss = (int) ( $kct_item['elevation_loss_m'] ?? 0 );

				if ( $kct_gain > 0 ) {
					/* translators: %s: nastoupané převýšení v metrech. */
					$kct_meta[] = sprintf( __( '↑ %s m', 'kct' ), number_format_i18n( $kct_gain ) );
				}

				// U okruhu je klesání rovno stoupání a druhé číslo nic nepřidá;
				// u trasy z místa na místo je to podstatný rozdíl.
				if ( $kct_loss > 0 && $kct_loss !== $kct_gain ) {
					/* translators: %s: klesání v metrech. */
					$kct_meta[] = sprintf( __( '↓ %s m', 'kct' ), number_format_i18n( $kct_loss ) );
				}

				$kct_time = trim( (string) ( $kct_item['estimated_time'] ?? '' ) );

				if ( '' !== $kct_time ) {
					$kct_meta[] = $kct_time;
				}

				$kct_difficulty = Field::label( $kct_item['difficulty'] ?? null );

				if ( '' !== $kct_difficulty ) {
					$kct_meta[] = $kct_difficulty;
				}
			} else {
				foreach ( array( $kct_item['category'] ?? null, $kct_item['accessibility'] ?? null ) as $kct_value ) {
					$kct_label = Field::label( $kct_value );

					if ( '' !== $kct_label ) {
						$kct_meta[] = $kct_label;
					}
				}

				$kct_place = array_filter( array(
					trim( (string) ( $kct_item['city'] ?? '' ) ),
					trim( (string) ( $kct_item['district'] ?? '' ) ),
				) );

				if ( $kct_place ) {
					$kct_meta[] = implode( ', ', $kct_place );
				}
			}

			// Štítky: vhodnost a sezónnost. Jsou to vlastnosti, ne čísla, proto
			// stojí zvlášť pod textem a ne v řádku s údaji.
			$kct_tags   = Field::labels( $kct_item['suitability'] ?? null );
			$kct_season = Field::label( $kct_item['seasonality'] ?? null );

			if ( '' !== $kct_season ) {
				$kct_tags[] = $kct_season;
			}

			$kct_favorites  = (int) ( $kct_item['favorites_count'] ?? 0 );
			$kct_tip        = ! empty( $kct_item['is_featured'] ) || ! empty( $kct_item['is_tip'] );
			$kct_activities = (array) ( $kct_item['activity_types'] ?? array() );
			?>
			<article class="turinka-card">
				<?php if ( '' !== $kct_image ) : ?>
					<a class="turinka-card__image" href="<?php echo esc_url( $kct_url ); ?>" target="_blank" rel="noopener" aria-hidden="true" tabindex="-1">
						<img src="<?php echo esc_url( $kct_image ); ?>" alt="" loading="lazy">
						<?php if ( $kct_tip ) : ?>
							<span class="turinka-card__badge"><?php esc_html_e( 'Tip', 'kct' ); ?></span>
						<?php endif; ?>
					</a>

					<?php if ( '' !== $kct_attribution ) : ?>
						<p class="turinka-card__attribution"><small><?php echo esc_html( $kct_attribution ); ?></small></p>
					<?php endif; ?>
				<?php endif; ?>

				<div class="turinka-card__body">
					<?php
					// Druhy aktivity: ikona dlaždice na podkladu v barvě, kterou
					// posílá API — tak je kreslí i Turinka. U akcí je v témže
					// poli obrázek z databáze KČT, proto se barva bere odsud
					// a ne z vlastní tabulky.
					if ( $kct_activities ) :
						?>
						<p class="turinka-card__activities">
							<?php foreach ( $kct_activities as $kct_activity ) : ?>
								<span class="turinka-activity" style="--turinka-activity: <?php echo esc_attr( (string) ( $kct_activity['color'] ?? '' ) ?: 'var(--primary)' ); ?>">
									<?php if ( ! empty( $kct_activity['icon_url'] ) ) : ?>
										<img class="turinka-activity__icon" src="<?php echo esc_url( (string) $kct_activity['icon_url'] ); ?>" alt="" width="16" height="16" loading="lazy">
									<?php endif; ?>
									<?php echo esc_html( Field::label( $kct_activity ) ); ?>
								</span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>

					<h3 class="turinka-card__title">
						<a href="<?php echo esc_url( $kct_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $kct_title ); ?><?php echo $kct_external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pevná značka výš. ?>
						</a>
					</h3>

					<?php if ( $kct_meta ) : ?>
						<p class="turinka-card__meta"><?php echo esc_html( implode( ' · ', $kct_meta ) ); ?></p>
					<?php endif; ?>

					<?php
					// Výškový profil: API posílá do třiceti bodů právě proto,
					// aby šel nakreslit do karty. Je to ozdoba, ne údaj, takže
					// je skrytý pro čtečky — čísla jsou v řádku nad ním.
					if ( $kct_routes ) :
						$kct_profile = Profile::svg( $kct_item['elevation_profile_mini'] ?? null );

						if ( '' !== $kct_profile ) :
							?>
							<div class="turinka-card__profile" aria-hidden="true"><?php echo $kct_profile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- skládá se z čísel ve třídě Profile. ?></div>
							<?php
						endif;
					endif;

					$kct_short = trim( (string) ( $kct_item['short_description'] ?? '' ) );

					if ( '' !== $kct_short ) :
						?>
						<p class="turinka-card__text"><?php echo esc_html( $kct_short ); ?></p>
					<?php endif; ?>

					<?php if ( $kct_tags ) : ?>
						<p class="turinka-card__tags">
							<?php foreach ( $kct_tags as $kct_tag ) : ?>
								<span class="turinka-tag"><?php echo esc_html( $kct_tag ); ?></span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>

					<?php
					// Patička sedí vždycky u spodní hrany karty (margin-top:auto
					// v CSS), takže řádky patiček jsou v mřížce zarovnané i při
					// různě dlouhých popisech.
					?>
					<p class="turinka-card__footer">
						<span class="turinka-card__open">
							<img class="turinka-card__logo" src="<?php echo esc_url( $kct_logo ); ?>" alt="<?php esc_attr_e( 'Turinka KČT', 'kct' ); ?>" height="16" loading="lazy">
							<?php
							echo esc_html__( 'Otevře se v Turince', 'kct' );
							echo $kct_external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pevná značka výš.
							?>
						</span>

						<?php if ( $kct_favorites > 0 ) : ?>
							<span class="turinka-card__favorites">
								<?php
								printf(
									/* translators: %s: počet lidí, kteří si položku uložili mezi oblíbené. */
									esc_html( _n( '%s oblíbení', '%s oblíbení', $kct_favorites, 'kct' ) ),
									esc_html( number_format_i18n( $kct_favorites ) )
								);
								?>
							</span>
						<?php endif; ?>
					</p>

				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php
	// Protihodnota za vypsaná data: odkaz do Turinky v DOM hostitele, ne jen
	// v kartách. Stejný důvod jako u vkládaného widgetu.
	?>
	<p class="turinka-cards__source">
		<small>
			<?php
			printf(
				/* translators: %s: odkaz na aplikaci Turinka. */
				esc_html__( 'Data poskytuje %s; odkazy z karet vedou do aplikace.', 'kct' ),
				sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( add_query_arg( array( 'utm_source' => 'blok', 'utm_medium' => 'vypis' ), $kct_app ) ),
					esc_html__( 'Turinka KČT', 'kct' )
				)
			);
			?>
		</small>
	</p>
</div>
