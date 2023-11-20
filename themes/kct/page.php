<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

get_header();

$no_top_padding = get_post_meta( get_the_ID(), 'no-top-padding', true ) ?: false;
$no_bottom_padding = get_post_meta( get_the_ID(), 'no-bottom-padding', true ) ?: false;
?>
	<div class="container<?= $no_top_padding ? ' pt-0' : '' ?><?= $no_bottom_padding ? ' pb-0' : '' ?>">
		<main id="primary" class="site-main">

			<?php
			echo '<pre>';
			var_dump(get_query_var( 'db_id' ));
			echo '</pre>';
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content', 'page' );

				// If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;

			endwhile; // End of the loop.
			?>

		</main><!-- #main -->

		<?php
		if ( ! get_post_meta( get_the_ID(), 'hide_sidebar', true ) ) {
			get_sidebar();
		}
		?>
	</div>
<?php
get_footer();
