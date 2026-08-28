<?php
/**
 * Detail příspěvku — obsah (Gutenberg). Titul, náhled a meta řeší post-hero.php.
 *
 * @package kct
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>

	<?php if ( has_excerpt() ) : ?>
		<p class="entry-perex"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>

	<div class="entry-content">
		<?php
		the_content();

		wp_link_pages(
			array(
				'before' => '<div class="page-links">' . esc_html__( 'Stránky:', 'kct' ),
				'after'  => '</div>',
			)
		);
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php get_template_part( 'template-parts/post-share' ); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
