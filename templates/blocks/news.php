<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */
$button = $args['button'];
$posts  = get_posts( array(
	'numberposts' => 3
) );

if ( $posts ) :
	?>
    <div class="kct-block block-news">
        <div class="articles_grid news-grid">
			<?php
			/* Start the Loop */
			/** @var WP_Post $post */
			foreach ( $posts as $post )  :
				/*
				 * Include the Post-Type-specific template for the content.
				 * If you want to override this in a child theme, then include a file
				 * called content-___.php (where ___ is the Post Type name) and that will be used instead.
				 */
				get_template_part( 'template-parts/content-boxed', get_post_type(), [ 'post_id' => $post ] );

			endforeach;
			?>
        </div>
	    <?php if ( $button ) {
			// Odkaz na stránku s příspěvky (blog), ne na archiv akcí.
			$news_url = get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' );
			?><a class="button mt-1" href="<?= esc_url( $news_url ) ?>" title="<?= esc_attr( $button ) ?>"><?= esc_html( $button ) ?></a><?php }; ?>
    </div>
<?php
endif;
