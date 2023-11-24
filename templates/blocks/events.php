<?php

if ( empty( ABSPATH ) ) {
	exit;
}

/**
 * @var $args array Template arguments
 */

$count       = $args['count'];
$time_period = $args['time_period'];

$date_from = $time_period == 'future' ? current_time( 'Y-m-d' ) : null;
$date_to   = $time_period == 'past' ? current_time( 'Y-m-d' ) : null;
// Get events
$events_feature = kct_container()->get( \Kct\Features\Events::class );
$events         = $events_feature->get_events( $date_from, $date_to );

if ( $count ) {
	$events = array_slice( $events, 0, $count );
}

?>
<div class="events">
	<ul class="events-list">
		<?php
		foreach ( $events as $event ) : ?>
			<li>
				<a href="<?= $event['permalink'] ?>" class="event">
					<div class="date">
						<?php if ( $event['date'] ) { ?>
							<span class="day-name"><?= date_i18n( 'l', strtotime( $event['date'] ) ) ?></span>
							<span class="date-number"><?= date( 'j. n.', strtotime( $event['date'] ) ) ?></span>
							<span class="date-year"><?= date( 'Y', strtotime( $event['date'] ) ) ?></span>
						<?php } ?>
					</div>
					<?php if ( ! empty( $event['image'] ) ) {
						printf( '<img src="%s" title="%s">', $event['image']['url'], $event['title'] );
					} ?>
					<div class="content">
						<h3><?= $event['year'] ? $event['year'] . '. ' : '' ?><?= $event['title'] ?></h3>
						<p>
							<?php
							$first_line = [];
							if ( isset( $event['organiser'] ) && isset( $event['organiser']['name'] ) && ! empty( $event['organiser']['name'] ) ) {
								$first_line[] = $event['organiser']['name'];
							}

							$place = [];
							if ( isset( $event['place'] ) && ! empty( $event['place'] ) ) {
								$place[] = $event['place'];
							}
							if ( isset( $event['district'] ) && ! empty( $event['district'] ) ) {
								$place[] = 'okr. ' . $event['district'];
							}
							if ( ! empty( $place ) ) {
								$first_line[] = implode( ', ', $place );
							}

							echo implode( ' – ', $first_line );
							?>
						</p>
						<p>
							<?php
							$details = [];
							if ( isset( $event['details'] ) && ! empty( $event['details'] ) ) {
								foreach ( $event['details'] as $detail ) {
									if ( empty( $detail['km'] ) ) {
										continue;
									}

									$words   = explode( " ", $detail['name'] );
									$acronym = "";

									foreach ( $words as $w ) {
										$acronym .= mb_substr( $w, 0, 1 );
									}

									$details[] = strtoupper( $acronym ) . ': ' . $detail['km'];
								}
							}

							if ( ! empty( $details ) ) {
								echo implode( '; ', $details );
							}
							?>
						</p>
					</div>
					<div class="icons">
						<?php
						if ( isset( $event['details'] ) && ! empty( $event['details'] ) ) {
							foreach ( $event['details'] as $detail ) {
								if ( empty( $detail['icon'] ) ) {
									continue;
								}

								printf( '<img src="%s" title="%s" width="30" height="30">', $detail['icon'], $detail['name'] );
							}
						}
						?>
					</div>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
