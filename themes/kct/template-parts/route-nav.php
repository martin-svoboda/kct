<?php
/**
 * Předchozí / další trasa — dvousloupcový full-width pruh pod článkem.
 *
 * Řadí se podle data vydání článku jako u aktualit: trasa nemá vlastní datum,
 * podle kterého by se dala řadit v čase, a abecedu drží archiv i postranní
 * oddíl. Třídy `.post-nav` jsou společné s navigací článků a akcí, stejně jako
 * u event-nav.php — je to tentýž pruh, jen s jinými popisky.
 *
 * @package kct
 */

$kct_newer = get_next_post();
$kct_older = get_previous_post();

if ( ! $kct_newer && ! $kct_older ) {
	return;
}
?>
<nav class="post-nav" aria-label="<?php esc_attr_e( 'Navigace mezi trasami', 'kct' ); ?>">
	<div class="post-nav__inner">
		<?php if ( $kct_newer ) : ?>
			<a class="post-nav__item post-nav__item--prev" href="<?php echo esc_url( (string) get_permalink( $kct_newer ) ); ?>">
				<span class="post-nav__dir"><span class="post-nav__arw" aria-hidden="true">&larr;</span> <?php esc_html_e( 'Novější trasa', 'kct' ); ?></span>
				<span class="post-nav__title"><?php echo esc_html( (string) get_the_title( $kct_newer ) ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>

		<?php if ( $kct_older ) : ?>
			<a class="post-nav__item post-nav__item--next" href="<?php echo esc_url( (string) get_permalink( $kct_older ) ); ?>">
				<span class="post-nav__dir"><?php esc_html_e( 'Starší trasa', 'kct' ); ?> <span class="post-nav__arw" aria-hidden="true">&rarr;</span></span>
				<span class="post-nav__title"><?php echo esc_html( (string) get_the_title( $kct_older ) ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>
	</div>
</nav>
