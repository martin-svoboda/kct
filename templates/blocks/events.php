<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */

$count       = $args['count'] ?? 5;
$time_period = $args['time_period'] ?? 'future';
$button      = $args['button'] ?? null;
$department  = $args['department'] ?? '';

$date_from = $time_period == 'future' ? current_time( 'Y-m-d' ) : null;
$date_to   = $time_period == 'past' ? current_time( 'Y-m-d' ) : null;
// Get events
$events_feature = kct_container()->get( \Kct\Features\Events::class );
$events         = $events_feature->get_events( $date_from, $date_to, $type = '', $department );

if ( $count ) {
	$events = array_slice( $events, 0, $count );
}

?>
<div class="kct-block block-events">
	<?php if ( empty( $events ) ) {
		echo 'Je nám líto, ale nebyli nalezeny žádné akce.';
	} else { ?>
		<ul class="events-list">
			<?php
			foreach ( $events as $event ) {
				kct_render_event_item( $event );
			}
			?>
		</ul>
	<?php }
	if ( $button ) { ?><a class="button mt-1" href="<?= get_post_type_archive_link( 'akce' ) ?>"
						  title="<?= $button ?>"><?= $button ?></a><?php }; ?>
</div>
