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
		<?php if ( 'post' === get_post_type() ) :
			$kct_cats = get_the_category();
			?>
            <div class="entry-meta">
				<?php if ( ! empty( $kct_cats ) ) : ?>
                    <span class="cat-label"><?php echo esc_html( $kct_cats[0]->name ); ?></span>
                    <span class="meta-sep" aria-hidden="true">/</span>
				<?php endif; ?>
                <time class="entry-date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j. n. Y' ) ); ?></time>
            </div><!-- .entry-meta -->
		<?php endif;

		// Celá karta je proklik na příspěvek (stretched-link přes ::after v CSS).
		the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );
		?>
    </header><!-- .entry-header -->

    <div class="entry-content">
		<?php the_excerpt(); ?>
    </div><!-- .entry-content -->
</article><!-- #post-<?php the_ID(); ?> -->
