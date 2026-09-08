<?php
/**
 * The template for displaying all single posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package kct
 */

get_header();

while ( have_posts() ) :
	the_post();

	// Hero hlavička na plnou šířku (jen běžné příspěvky).
	if ( 'post' === get_post_type() ) {
		get_template_part( 'template-parts/post-hero' );
	}
	?>

	<div class="container single-body">
		<main id="primary" class="site-main">
			<?php
			get_template_part( 'template-parts/content', get_post_type() );

			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;
			?>
		</main><!-- #main -->

		<?php get_sidebar(); ?>
	</div>

	<?php
	// Sekce pod obsahem (jen běžné příspěvky):
	if ( 'post' === get_post_type() ) :
		// Prev/next — full-width bílý pruh (mimo container).
		get_template_part( 'template-parts/post-nav' );
		?>
		<div class="container single-after">
			<?php get_template_part( 'template-parts/post-related' ); ?>
		</div>
	<?php endif; ?>

	<?php
endwhile; // End of the loop.

get_footer();
