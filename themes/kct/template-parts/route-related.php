<?php
/**
 * „Další trasy“ pod článkem — TYTÉŽ karty jako v archivu a v bloku Trasy
 * (.articles_grid + content-boxed), žádný druhý druh karty. Obdoba
 * post-related.php u aktualit a event-related.php u akcí.
 *
 * Postranní oddíl vypisuje trasy abecedně jako rozcestník, tady jsou naopak
 * nejnovější — dvě různé otázky („kam dál podle názvu“ a „co přibylo“).
 *
 * @package kct
 */

$kct_related = get_posts( array(
	'post_type'        => \Kct\PostTypes\RoadPostType::KEY,
	'post_status'      => 'publish',
	'numberposts'      => 3,
	'post__not_in'     => array( (int) get_the_ID() ),
	'suppress_filters' => false,
) );

if ( ! $kct_related ) {
	return;
}
?>
<section class="route-related">
	<div class="kct-eyebrow">
		<span class="kct-tricolor" aria-hidden="true"></span>
		<span class="kct-eyebrow__text"><?php esc_html_e( 'Mohlo by vás zajímat', 'kct' ); ?></span>
	</div>
	<h2 class="wp-block-heading"><?php esc_html_e( 'Další trasy', 'kct' ); ?></h2>

	<div class="articles_grid">
		<?php
		global $post;
		foreach ( $kct_related as $post ) :
			setup_postdata( $post );
			get_template_part( 'template-parts/content-boxed', 'trasa' );
		endforeach;
		wp_reset_postdata();
		?>
	</div>
</section>
