<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * Výpis tras.
 *
 * Trasy jsou běžné příspěvky webu, takže se berou obyčejným dotazem — žádné
 * volání do Turinky. Výpis musí fungovat i webu, který propojený není.
 *
 * @var $args array Template arguments
 */

$count  = (int) ( $args['count'] ?? 6 );
$order  = (string) ( $args['order'] ?? 'date' );
$button = $args['button'] ?? null;

$routes = get_posts( array(
	'post_type'        => \Kct\PostTypes\RoadPostType::KEY,
	'post_status'      => 'publish',
	'numberposts'      => $count > 0 ? $count : 6,
	'orderby'          => 'title' === $order ? 'title' : 'date',
	'order'            => 'title' === $order ? 'ASC' : 'DESC',
	'suppress_filters' => false,
) );

?>
<div class="kct-block block-routes">
	<?php if ( empty( $routes ) ) : ?>
		<p><?php esc_html_e( 'Žádné trasy tu zatím nejsou.', 'kct' ); ?></p>
	<?php else : ?>
		<div class="articles_grid">
			<?php
			global $post;
			foreach ( $routes as $post ) {
				setup_postdata( $post );
				get_template_part( 'template-parts/content-boxed', 'trasa' );
			}
			wp_reset_postdata();
			?>
		</div>
	<?php endif; ?>

	<?php if ( $button ) : ?>
		<a class="button mt-1" href="<?php echo esc_url( (string) get_post_type_archive_link( \Kct\PostTypes\RoadPostType::KEY ) ); ?>"><?php echo esc_html( (string) $button ); ?></a>
	<?php endif; ?>
</div>
