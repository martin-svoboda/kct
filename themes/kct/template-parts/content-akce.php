<?php
/**
 * Template part for displaying posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */

$db_event_id = $_GET['db_id'] ?? '';
if ( $db_event_id ) {
	$event = kct_container()->get( \Kct\Repositories\DbEventRepository::class )->get_by_db_id( $db_event_id );
} else {
	$event = kct_container()->get( \Kct\Repositories\EventRepository::class )->get( get_the_ID() );
}
?>

<article id="post-<?php echo $db_event_id ?: get_the_ID(); ?>" class="event-post" >
	<header class="entry-header">
		<h1 class="entry-title"><?= $event->year ? $event->year . '. ' : '' ?><?= $event->title ?></h1>
	</header><!-- .entry-header -->

	<div class="event-info">
		<table class="event-data">
			<tbody>
			<tr>
				<?php if ( isset( $event->organiser ) && ! empty( $event->organiser ) ) : ?>
				<th>Pořadatel</th>
				<td><?php
					$organiser = [];
					if ( ! empty( $event->organiser['name'] ) ) {
						$organiser[] = $event->organiser['name'];
					}
					if ( ! empty( $event->organiser['street'] ) ) {
						$organiser[] = $event->organiser['street'];
					}
					if ( ! empty( $event->organiser['zip'] ) || ! empty( $event->organiser['town'] ) ) {
						$organiser[] = ( $event->organiser['zip'] ?? '' ) . ' ' . ( $event->organiser['town'] ?? '' );
					}
					if ( ! empty( $event->organiser['phone'] ) ) {
						$organiser[] = sprintf( '<a href="tel:%1$s">%1$s</a>', $event->organiser['phone'] );
					}
					if ( ! empty( $event->organiser['email'] ) ) {
						$organiser[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', $event->organiser['email'] );
					}
					if ( ! empty( $event->organiser['web'] ) ) {
						$organiser[] = sprintf( '<a href="%1$s" target="_blank">%1$s</a>', $event->organiser['web'] );
					}
					echo implode( ', ', $organiser );
					?></td>
			</tr>
			<?php endif;
			if ( ( isset( $event->place ) && ! empty( $event->place ) ) || ( isset( $event->district ) && ! empty( $event->district ) ) ) : ?>
				<tr>
					<th>Místo konání</th>
					<td><?php
						$place = [];
						if ( isset( $event->place ) && ! empty( $event->place ) ) {
							$place[] = $event->place;
						}
						if ( isset( $event->district ) && ! empty( $event->district ) ) {
							$place[] = 'okr. ' . $event->district;
						}
						echo implode( ', ', $place );
						?></td>
				</tr>
			<?php endif;
			if ( isset( $event->year ) && ! empty( $event->year ) ) : ?>
				<tr>
					<th>Ročník</th>
					<td><?= $event->year ?>. ročník</td>
				</tr>
			<?php endif;
			if ( isset( $event->start ) && ! empty( $event->start ) ) : ?>
				<tr>
					<th>Start</th>
					<td>
						<ul>
							<?php
							$start = [];
							if ( ! empty( $event->start['date'] ) ) {
								$start[] = $event->start['date'];
							}
							if ( ! empty( $event->start['time'] ) ) {
								$start[] = 'čas: ' . $event->start['time'];
							}
							if ( ! empty( $event->start['place'] ) ) {
								$start[] = $event->start['place'];
							}
							foreach ( $start as $item ) {
								echo '<li>' . $item . '</li>';
							}
							?>
						</ul>
					</td>
				</tr>
			<?php endif;
			if ( isset( $event->finish ) && ! empty( $event->finish ) ) : ?>
				<tr>
					<th>Cíl</th>
					<td>
						<ul>
							<?php
							$finish = [];
							if ( ! empty( $event->finish['date'] ) ) {
								$finish[] = $event->finish['date'];
							}
							if ( ! empty( $event->finish['time'] ) ) {
								$finish[] = 'čas: ' . $event->finish['time'];
							}
							if ( ! empty( $event->finish['place'] ) ) {
								$finish[] = $event->finish['place'];
							}
							foreach ( $finish as $item ) {
								echo '<li>' . $item . '</li>';
							}
							?>
						</ul>
					</td>
				</tr>
			<?php endif;
			if ( isset( $event->details ) && ! empty( $event->details ) ) : ?>
				<tr>
					<th></th>
					<td><?php
						$details = [];
						foreach ( $event->details as $detail ) {
							$text      = $detail['name'] . ( $detail['km'] ? ': ' . $detail['km'] : '' );
							$details[] = sprintf( '<img src="%s" title="%s" width="30" height="30"> %s', $detail['icon'], $detail['name'], $text );;
						}

						if ( $details ) {
							echo '<ul>';
							foreach ( $details as $item ) {
								echo '<li>' . $item . '</li>';
							}
							echo '</ul>';
						}
						?></td>
				</tr>
			<?php endif;
			if ( isset( $event->contact ) && ! empty( $event->contact ) ) : ?>
				<tr>
					<th>Kontakt</th>
					<td><?php
						$organiser = [];
						if ( ! empty( $event->contact['person'] ) ) {
							$organiser[] = $event->contact['person'];
						}
						if ( ! empty( $event->contact['street'] ) ) {
							$organiser[] = $event->contact['street'];
						}
						if ( ! empty( $event->contact['zip'] ) || ! empty( $event->contact['town'] ) ) {
							$organiser[] = ( $event->contact['zip'] ?? '' ) . ' ' . ( $event->contact['town'] ?? '' );
						}
						if ( ! empty( $event->contact['phone'] ) ) {
							$organiser[] = sprintf( '<a href="tel:%1$s">%1$s</a>', $event->contact['phone'] );
						}
						if ( ! empty( $event->contact['email'] ) ) {
							$organiser[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', $event->contact['email'] );
						}
						if ( ! empty( $event->contact['web'] ) ) {
							$organiser[] = sprintf( '<a href="%1$s" target="_blank">%1$s</a>', $event->contact['web'] );
						}
						echo implode( ', ', $organiser );
						?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<div class="event-side-info">
			<?php if ( $event->image ) {
				printf( '<img src="%s" title="%2$s" alt="%2$s">', $event->image['url'], $event->title );
			} else {
				kct_post_thumbnail();
			}
			if ( ( isset( $event->start ) && ! empty( $event->start['gps_n'] ) && ! empty( $event->start['gps_e'] ) ) ) { ?>
				<iframe style="border:none"
						src="https://frame.mapy.cz/?x=<?= $event->start['gps_e'] ?>&y=<?= $event->start['gps_n'] ?>&z=13"
						width="700" height="466" frameborder="0"></iframe>
			<?php } ?>
		</div>
	</div>
	<div class="entry-content">
		<?php
		//dump( $event->to_array() );
		echo $event->content;
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php kct_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-<?php the_ID(); ?> -->
