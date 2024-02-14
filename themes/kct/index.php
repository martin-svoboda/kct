<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();
?>
    <div class="container pt-0">
        <main id="primary" class="site-main">

			<?php
			if ( get_query_var( 'db_id' ) ) {
				get_template_part( 'template-parts/content', 'akce' );
			} elseif ( is_404() ) {
				get_template_part( 'template-parts/content', '404' );
			} else {

				if ( have_posts() ) :

					if ( is_home() && ! is_front_page() ) :
						?>
                        <header>
                            <h1 class="entry-title"><?php echo get_the_title( get_option( 'page_for_posts', true ) ); ?></h1>
                        </header>
					<?php
					endif;
					?>
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
					the_posts_navigation();

				else :

					get_template_part( 'template-parts/content', 'none' );

				endif;
			}
			?>

        </main><!-- #main -->
    </div>
<?php
get_footer();
