<?php
/**
 * Předchozí / další akce — dvousloupcový full-width pruh pod obsahem.
 *
 * Řadí se podle DATA KONÁNÍ, ne podle publikace: vlevo dřívější, vpravo
 * pozdější. Oproti článkům (post-nav.php) je to obráceně a je to záměr —
 * u akcí se myslí v kalendáři, ne v pořadí vydání.
 *
 * Třídy `.post-nav` jsou společné s navigací článků; `.post-nav` má breakout
 * na 100vw, takže pruh funguje i uvnitř containeru.
 *
 * @package kct
 *
 * @var array $args {
 *     @type array  $events  Seznam akcí z Events::get_events(), seřazený podle data.
 *     @type ?array $current Aktuální akce, nebo null když ji web nevypisuje.
 * }
 */

$kct_current = $args['current'] ?? null;

if ( ! $kct_current ) {
	return;
}

$kct_around = kct_event_neighbours( $args['events'] ?? array(), $kct_current );
$kct_prev   = $kct_around['prev'];
$kct_next   = $kct_around['next'];

if ( ! $kct_prev && ! $kct_next ) {
	return;
}
?>
<nav class="post-nav" aria-label="<?php esc_attr_e( 'Navigace mezi akcemi', 'kct' ); ?>">
	<div class="post-nav__inner">
		<?php if ( $kct_prev ) : ?>
			<a class="post-nav__item post-nav__item--prev" href="<?php echo esc_url( $kct_prev['permalink'] ?? '#' ); ?>">
				<span class="post-nav__dir"><span class="post-nav__arw" aria-hidden="true">&larr;</span> <?php esc_html_e( 'Předchozí akce', 'kct' ); ?></span>
				<span class="post-nav__title"><?php echo esc_html( $kct_prev['title'] ?? '' ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>

		<?php if ( $kct_next ) : ?>
			<a class="post-nav__item post-nav__item--next" href="<?php echo esc_url( $kct_next['permalink'] ?? '#' ); ?>">
				<span class="post-nav__dir"><?php esc_html_e( 'Následující akce', 'kct' ); ?> <span class="post-nav__arw" aria-hidden="true">&rarr;</span></span>
				<span class="post-nav__title"><?php echo esc_html( $kct_next['title'] ?? '' ); ?></span>
			</a>
		<?php else : ?>
			<span class="post-nav__item post-nav__item--empty" aria-hidden="true"></span>
		<?php endif; ?>
	</div>
</nav>
