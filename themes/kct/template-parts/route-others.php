<?php
/**
 * Postranní oddíl trasy — rozcestník po ostatních trasách webu.
 *
 * Údaje o trase (délka, převýšení, obtížnost) tu nejsou schválně: ty drží
 * Turinka a vypisuje je rám pod textem. Web do postranního oddílu dává to, co
 * vlastní — ostatní trasy a cestu do archivu. Obdoba „Dalších ročníků“
 * v postranním oddílu akce.
 *
 * @package kct
 */

$kct_current = (int) get_the_ID();

$kct_routes = get_posts( array(
	'post_type'        => \Kct\PostTypes\RoadPostType::KEY,
	'post_status'      => 'publish',
	'numberposts'      => 8,
	'post__not_in'     => array( $kct_current ),
	'orderby'          => 'title',
	'order'            => 'ASC',
	'suppress_filters' => false,
) );

if ( ! $kct_routes ) {
	return;
}
?>
<section class="route-others">
	<h2 class="route-others__title"><?php esc_html_e( 'Trasy na webu', 'kct' ); ?></h2>

	<ul class="route-others__list">
		<?php foreach ( $kct_routes as $kct_route ) : ?>
			<li class="route-others__item">
				<a class="route-others__link" href="<?php echo esc_url( (string) get_permalink( $kct_route ) ); ?>">
					<?php if ( has_post_thumbnail( $kct_route ) ) : ?>
						<?php echo get_the_post_thumbnail( $kct_route, 'thumbnail', array( 'class' => 'route-others__thumb', 'alt' => '' ) ); ?>
					<?php endif; ?>
					<span class="route-others__name"><?php echo esc_html( (string) get_the_title( $kct_route ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<a class="button mt-1" href="<?php echo esc_url( (string) get_post_type_archive_link( \Kct\PostTypes\RoadPostType::KEY ) ); ?>">
		<?php esc_html_e( 'Všechny trasy', 'kct' ); ?>
	</a>
</section>
