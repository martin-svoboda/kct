<?php
/**
 * The template for displaying archive pages > use index
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();
?>

	<div class="container">
		<main id="primary" class="site-main">

			<?php if ( have_posts() ) : ?>
				<header class="page-header">
					<?php get_template_part( 'template-parts/breadcrumbs', null, array( 'muted' => true ) ); ?>
					<?php
					the_archive_title( '<h1 class="page-title">', '</h1>' );
					the_archive_description( '<div class="archive-description">', '</div>' );
					?>
				</header><!-- .page-header -->

				<div class="articles_grid"> <?php
				/* Start the Loop */
				while ( have_posts() ) :
					the_post();

					/*
					 * Include the Post-Type-specific template for the content.
					 * If you want to override this in a child theme, then include a file
					 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
					 */
					get_template_part( 'template-parts/content-boxed', get_post_type() );

				endwhile;
				?>
				</div><?php
				the_posts_pagination( array(
					'mid_size'           => 2,
					'prev_text'          => '<span class="nav-arrow" aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Předchozí', 'kct' ) . '</span>',
					'next_text'          => '<span class="nav-arrow" aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__( 'Další', 'kct' ) . '</span>',
					'screen_reader_text' => esc_html__( 'Navigace v příspěvcích', 'kct' ),
				) );

			else :

				get_template_part( 'template-parts/content', 'none' );

			endif;
			?>

		</main><!-- #main -->

		<?php // get_sidebar(); ?>
	</div>
<?php
get_footer();
