<?php
/**
 * „Mohlo by vás zajímat“ — nejbližší budoucí akce, přednostně stejného typu.
 *
 * Karty jsou TYTÉŽ jako v archivu a v bloku akcí (kct_render_event_item),
 * žádný třetí druh karty. Obdoba post-related.php u článků, kde se stejně
 * přednostně bere stejná rubrika a doplňuje se nejnovějšími.
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

$kct_related = kct_event_related(
	$args['events'] ?? array(),
	$kct_current,
	current_time( 'Y-m-d' ),
	3
);

if ( ! $kct_related ) {
	return;
}
?>
<section class="event-related">
	<div class="kct-eyebrow">
		<span class="kct-tricolor" aria-hidden="true"></span>
		<span class="kct-eyebrow__text"><?php esc_html_e( 'Mohlo by vás zajímat', 'kct' ); ?></span>
	</div>
	<h2 class="wp-block-heading"><?php esc_html_e( 'Další akce', 'kct' ); ?></h2>

	<?php // Obal .events je nutný: karty výpisu se stylují pod `.block-events, .events`
	// (core/blocks/events.scss), samotné .events-list je bez něj holý seznam.
	// Archiv /akce/ obaluje seznam stejně. ?>
	<div class="events">
		<ul class="events-list">
			<?php foreach ( $kct_related as $kct_event ) { kct_render_event_item( $kct_event ); } ?>
		</ul>
	</div>
</section>
