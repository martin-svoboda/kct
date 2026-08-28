<?php
/**
 * „Další aktuality" — související příspěvky přes STEJNÉ karty výpisu jako všude
 * jinde (.articles_grid + content-boxed). Žádné vlastní karty.
 *
 * @package kct
 */

$kct_cats = wp_get_post_categories( get_the_ID() );

$kct_related = get_posts( array(
	'post_type'           => 'post',
	'numberposts'         => 3,
	'post__not_in'        => array( get_the_ID() ),
	'category__in'        => $kct_cats ?: array(),
	'ignore_sticky_posts' => true,
) );

// Doplnění nejnovějšími, pokud je ve stejné rubrice málo článků.
if ( count( $kct_related ) < 3 ) {
	$kct_have = array_merge( array( get_the_ID() ), wp_list_pluck( $kct_related, 'ID' ) );
	$kct_related = array_merge( $kct_related, get_posts( array(
		'post_type'           => 'post',
		'numberposts'         => 3 - count( $kct_related ),
		'post__not_in'        => $kct_have,
		'ignore_sticky_posts' => true,
	) ) );
}

if ( ! $kct_related ) {
	return;
}

$kct_posts_page = (int) get_option( 'page_for_posts' );
?>
<section class="post-related">
	<div class="kct-eyebrow">
		<span class="kct-tricolor" aria-hidden="true"></span>
		<span class="kct-eyebrow__text"><?php esc_html_e( 'Mohlo by vás zajímat', 'kct' ); ?></span>
	</div>
	<h2 class="wp-block-heading"><?php esc_html_e( 'Další aktuality', 'kct' ); ?></h2>

	<div class="articles_grid">
		<?php
		/** @var WP_Post $post */
		foreach ( $kct_related as $post ) :
			setup_postdata( $post );
			get_template_part( 'template-parts/content-boxed', get_post_type() );
		endforeach;
		wp_reset_postdata();
		?>
	</div>
</section>
