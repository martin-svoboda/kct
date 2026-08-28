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
		<?php
		// Rozcestník na podstatný obsah webu — skládá se dynamicky (viz kct_footer_directory_columns()),
		// takže se na webech bez daného obsahu (odborové weby sítě) sám smrskne, nebo úplně nevykreslí.
		$kct_footer_columns = function_exists( 'kct_footer_directory_columns' ) ? kct_footer_directory_columns() : array();
		if ( ! empty( $kct_footer_columns ) ) :
			?>
			<nav class="footer-directory" aria-label="<?php esc_attr_e( 'Rozcestník', 'kct' ); ?>">
				<?php foreach ( $kct_footer_columns as $kct_footer_column ) : ?>
					<div class="footer-directory__column">
						<h3><?php echo esc_html( $kct_footer_column['title'] ); ?></h3>
						<ul>
							<?php foreach ( $kct_footer_column['links'] as $kct_footer_link ) : ?>
								<li><a href="<?php echo esc_url( $kct_footer_link['url'] ); ?>"><?php echo esc_html( $kct_footer_link['label'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
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
