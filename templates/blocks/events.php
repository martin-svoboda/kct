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
<div class="kct-block events">
	<?php if ( empty( $events ) ) {
		echo 'Je nám líto, ale nebyli nalezeny žádné akce.';
	} else { ?>
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
							if ( isset( $event['proposal'] ) && ! empty( $event['proposal']['url'] ) ) { ?>
								<div class="proposal-icon">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20">
										<path fill="currentColor"
											  d="M17.924 7.154h-.514l.027-1.89a.464.464 0 0 0-.12-.298L12.901.134A.393.393 0 0 0 12.618 0h-9.24a.8.8 0 0 0-.787.784v6.37h-.515c-.285 0-.56.118-.76.328A1.14 1.14 0 0 0 1 8.275v5.83c0 .618.482 1.12 1.076 1.12h.515v3.99A.8.8 0 0 0 3.38 20h13.278c.415 0 .78-.352.78-.784v-3.99h.487c.594 0 1.076-.503 1.076-1.122v-5.83c0-.296-.113-.582-.315-.792a1.054 1.054 0 0 0-.76-.328ZM3.95 1.378h6.956v4.577a.4.4 0 0 0 .11.277a.37.37 0 0 0 .267.115h4.759v.807H3.95V1.378Zm0 17.244v-3.397h12.092v3.397H3.95ZM12.291 1.52l.385.434l2.58 2.853l.143.173h-2.637c-.2 0-.325-.033-.378-.1c-.053-.065-.084-.17-.093-.313V1.52ZM3 14.232v-6h1.918c.726 0 1.2.03 1.42.09c.34.09.624.286.853.588c.228.301.343.69.343 1.168c0 .368-.066.678-.198.93c-.132.25-.3.447-.503.59a1.72 1.72 0 0 1-.62.285c-.285.057-.698.086-1.239.086h-.779v2.263H3Zm1.195-4.985v1.703h.654c.471 0 .786-.032.945-.094a.786.786 0 0 0 .508-.762a.781.781 0 0 0-.19-.54a.823.823 0 0 0-.48-.266c-.142-.027-.429-.04-.86-.04h-.577Zm4.04-1.015h2.184c.493 0 .868.038 1.127.115c.347.103.644.288.892.552c.247.265.436.589.565.972c.13.384.194.856.194 1.418c0 .494-.06.92-.182 1.277c-.148.437-.36.79-.634 1.06c-.207.205-.487.365-.84.48c-.263.084-.616.126-1.057.126H8.235v-6ZM9.43 9.247v3.974h.892c.334 0 .575-.019.723-.057c.194-.05.355-.132.482-.25c.128-.117.233-.31.313-.579c.081-.269.121-.635.121-1.099c0-.464-.04-.82-.12-1.068a1.377 1.377 0 0 0-.34-.581a1.132 1.132 0 0 0-.553-.283c-.167-.038-.494-.057-.98-.057H9.43Zm4.513 4.985v-6H18v1.015h-2.862v1.42h2.47v1.015h-2.47v2.55h-1.195Z"/>
									</svg>
								</div>
							<?php };
							?>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php }
	if ( $button ) { ?><a class="button mt-1" href="<?= get_post_type_archive_link( 'akce' ) ?>"
						  title="<?= $button ?>"><?= $button ?></a><?php }; ?>
</div>
