<?php
/**
 * Template part for displaying posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<?php kct_post_thumbnail(); ?>

    <header class="entry-header">
		<?php

		the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );

		if ( 'post' === get_post_type() ) :
			?>
            <div class="entry-meta">
				<?php
				kct_posted_on();
				?>
            </div><!-- .entry-meta -->
		<?php endif; ?>
    </header><!-- .entry-header -->

    <div class="entry-content">
		<?php
		the_excerpt();
		?>
    </div><!-- .entry-content -->

    <footer class="entry-footer">
		<?php
		printf( '<a class="uppercase" href="%s" title="%s">%s</a>', get_permalink(), sprintf( __( 'Přečíst článek %s', 'kct' ), get_the_title() ), __( 'Ćíst dále', 'kct' ) )
		?>
    </footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
