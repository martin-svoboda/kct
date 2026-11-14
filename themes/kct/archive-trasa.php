<?php
/**
 * Archiv tras (/trasa/).
 *
 * Vlastní šablona, ne index.php: ten je výpisem aktualit a vypisuje nad
 * seznamem rubrikové menu („Vše / Novinky / …“), které s trasami nemá nic
 * společného. Stavba je stejná jako u archivu odborů — hlavička s drobečky
 * a H1, pod ní mřížka karet a stránkování.
 *
 * Karty jsou TYTÉŽ jako v bloku Trasy a v „Dalších trasách“ pod detailem
 * (.articles_grid + content-boxed), žádný druhý druh karty.
 *
 * @package kct
 */

get_header();
?>

<div class="container pt-0">
	<main id="primary" class="site-main" style="width:100%">
		<header class="archive-header">
			<?php get_template_part( 'template-parts/breadcrumbs', null, array( 'muted' => true ) ); ?>
			<h1 class="entry-title"><?php echo esc_html( get_theme_mod( 'kct_trasa_archive_title' ) ?: post_type_archive_title( '', false ) ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="articles_grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content-boxed', get_post_type() );
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination( array(
				'mid_size'           => 2,
				'prev_text'          => '<span class="nav-arrow" aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Předchozí', 'kct' ) . '</span>',
				'next_text'          => '<span class="nav-arrow" aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Další', 'kct' ) . '</span>',
				'screen_reader_text' => esc_html__( 'Navigace v příspěvcích', 'kct' ),
			) );
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</main><!-- #main -->
</div>

<?php
get_footer();
