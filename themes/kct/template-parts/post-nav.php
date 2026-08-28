<?php
/**
 * Navigace mezi příspěvky (dvousloupcový full-width pruh pod obsahem).
 * Vlevo NOVĚJŠÍ (←), vpravo STARŠÍ (→) — stejná logika jako stránkování archivu.
 *
 * @package kct
 */

$kct_newer = get_next_post();     // publikovaný později = novější
$kct_older = get_previous_post(); // publikovaný dříve = starší

if ( ! $kct_newer && ! $kct_older ) {
	return;
}
?>
<nav class="post-nav" aria-label="<?php esc_attr_e( 'Další příspěvky', 'kct' ); ?>">
	<div class="post-nav__inner">
		<?php if ( $kct_newer ) : ?>
			<a class="post-nav__item post-nav__item--prev" href="<?php echo esc_url( get_permalink( $kct_newer ) ); ?>">
				<span class="post-nav__dir"><span class="post-nav__arw" aria-hidden="true">&larr;</span> <?php esc_html_e( 'Novější', 'kct' ); ?></span>
				<span class="post-nav__title"><?php echo esc_html( get_the_title( $kct_newer ) ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>

		<?php if ( $kct_older ) : ?>
			<a class="post-nav__item post-nav__item--next" href="<?php echo esc_url( get_permalink( $kct_older ) ); ?>">
				<span class="post-nav__dir"><?php esc_html_e( 'Starší', 'kct' ); ?> <span class="post-nav__arw" aria-hidden="true">&rarr;</span></span>
				<span class="post-nav__title"><?php echo esc_html( get_the_title( $kct_older ) ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>
	</div>
</nav>
