<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package kct
 */

?>

<footer id="footer" class="site-footer">
    <div class="container">
        <div class="widget-area">
		    <?php dynamic_sidebar( 'footer' ); ?>
        </div>
        <div class="site-info">
	        <?php
	        /* translators: 1: Theme name, 2: Theme author. */
	        printf( 'Copyright %1$s %2$s', current_time('Y'), get_bloginfo('name') );
	        ?>
            <span class="sep"> | </span>
			<?php
			/* translators: 1: Theme name, 2: Theme author. */
			printf( esc_html__( 'Postaveno na šabloně %1$s', 'kct' ), 'KČT' );
			?>
        </div><!-- .site-info -->
    </div><!-- .container -->
</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
