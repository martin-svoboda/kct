<?php
/**
 * Hero hlavička detailu příspěvku — featured obrázek na pozadí, breadcrumb,
 * kategorie + štítky, velký titul, meta (datum, doba čtení). Trikolóra dole.
 *
 * @package kct
 */

$kct_thumb = get_the_post_thumbnail_url( get_the_ID(), 'full' );
$kct_cats  = get_the_category();
$kct_cat   = ! empty( $kct_cats ) ? $kct_cats[0] : null;
$kct_tags  = get_the_tags() ?: array();

// Odhad doby čtení (~200 slov/min).
$kct_words = str_word_count( wp_strip_all_tags( get_the_content() ) );
$kct_read  = max( 1, (int) round( $kct_words / 200 ) );
?>
<div class="post-hero<?php echo $kct_thumb ? '' : ' post-hero--noimg'; ?>">
	<?php if ( $kct_thumb ) : ?>
		<img class="post-hero__img" src="<?php echo esc_url( $kct_thumb ); ?>" alt="" loading="eager">
	<?php endif; ?>
	<span class="post-hero__overlay" aria-hidden="true"></span>

	<div class="post-hero__inner">
		<?php
		// Tmavá varianta (fotka na pozadí) — bez `muted`, plná opacita.
		// Zdůvodnění rezervy kontrastu viz components/breadcrumbs.scss.
		get_template_part( 'template-parts/breadcrumbs' );
		?>

		<?php if ( $kct_cat || $kct_tags ) : ?>
			<div class="post-hero__chips">
				<?php if ( $kct_cat ) : ?>
					<span class="post-hero__cat"><?php echo esc_html( $kct_cat->name ); ?></span>
				<?php endif; ?>
				<?php foreach ( $kct_tags as $kct_tag ) : ?>
					<span class="post-hero__tag"><?php echo esc_html( $kct_tag->name ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h1 class="post-hero__title"><?php the_title(); ?></h1>

		<div class="post-hero__meta">
			<span class="post-hero__meta-item">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="3"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>
				<?php echo esc_html( get_the_date( 'j. n. Y' ) ); ?>
			</span>
			<span class="post-hero__meta-sep" aria-hidden="true"></span>
			<span class="post-hero__meta-item">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7.5v5l3 2"></path></svg>
				<?php printf( esc_html__( '%d min čtení', 'kct' ), $kct_read ); ?>
			</span>
		</div>
	</div>

	<span class="post-hero__strip" aria-hidden="true"></span>
</div>
