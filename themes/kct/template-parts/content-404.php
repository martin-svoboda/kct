<?php
/**
 * Obsah 404 stránky.
 *
 * Dřív se sem nikdy nedošlo doopravdy — NOBLOGREDIRECT (wp-config.php)
 * přesměrovávalo úplně každou 404 na hlavním webu jinam (viz Frontend.php,
 * kde je to teď zrušené). Teprve od téhle opravy stránku reálně vidí
 * návštěvník, typicky ten, kdo přišel z vyhledávače na adresu zrušené nebo
 * přejmenované akce — tomu je text přizpůsobený, ne obecné „chyba 404".
 *
 * @package kct
 */

$kct_posts_page_id  = (int) get_option( 'page_for_posts' );
$kct_posts_page_url = $kct_posts_page_id ? get_permalink( $kct_posts_page_id ) : false;

$kct_events_archive_url      = get_post_type_archive_link( 'akce' );
$kct_departments_archive_url = get_post_type_archive_link( 'odbory' );
?>
	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Tahle adresa už neplatí', 'kct' ); ?></h1>
		</header><!-- .page-header -->

		<div class="page-content">
			<p>
				<?php esc_html_e( 'Stránka, kterou hledáte, byla pravděpodobně zrušena nebo přesunuta jinam — často jde o starou akci, na kterou už nevede platný odkaz. Zkuste ji najít přes vyhledávání, nebo pokračujte na některou z těchto stránek:', 'kct' ); ?>
			</p>

			<?php get_search_form(); ?>

			<ul class="error-404__links">
				<?php if ( $kct_events_archive_url ) : ?>
				<li>
					<a class="btn" href="<?php echo esc_url( $kct_events_archive_url ); ?>">
						<?php esc_html_e( 'Přehled akcí', 'kct' ); ?>
					</a>
				</li>
				<?php endif; ?>

				<?php if ( $kct_departments_archive_url ) : ?>
				<li>
					<a class="btn btn--ghost" href="<?php echo esc_url( $kct_departments_archive_url ); ?>">
						<?php esc_html_e( 'Odbory', 'kct' ); ?>
					</a>
				</li>
				<?php endif; ?>

				<?php if ( $kct_posts_page_url ) : ?>
				<li>
					<a class="btn btn--ghost" href="<?php echo esc_url( $kct_posts_page_url ); ?>">
						<?php esc_html_e( 'Aktuality a zprávy', 'kct' ); ?>
					</a>
				</li>
				<?php endif; ?>

				<li>
					<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Domovská stránka', 'kct' ); ?>
					</a>
				</li>
			</ul>
		</div><!-- .page-content -->
	</section><!-- .error-404 -->
<?php
