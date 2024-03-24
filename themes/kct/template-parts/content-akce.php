<?php
/**
 * Template part for displaying posts
 *
 * @link    https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package kct
 */
global $post;

$db_event_id = get_query_var( 'db_id' ) ?? '';
$event       = kct_container()->get( \Kct\Features\Events::class )->get_event( get_the_ID(), $db_event_id );

$image_url = '';
if ( isset( $event['image'] ) && $event['image'] ) {
	$image_url = $event['image']['url'];
} else {
	$image_url = get_the_post_thumbnail_url( null, 'full' );
}
?>

<article id="post-<?php echo $db_event_id ?: get_the_ID(); ?>" class="event-post">
	<header
		class="entry-header full-width <?= $image_url ? 'large' : '' ?>" <?php if ( $image_url ) { ?> style="background-image: url('<?= $image_url ?>')" <?php } ?>>
		<div class="container">
			<?php if ( isset( $event['year'] ) && ! empty( $event['year'] ) ) {
				echo $event['year'] . '. ročník';
			} ?>
			<h1 class="entry-title"><?= $event['title'] ?></h1>
		</div>
	</header><!-- .entry-header -->
	<div class="kct-block infoboxes">
		<?php if ( ( isset( $event['organiser']['name'] ) && ! empty( $event['organiser']['name'] ) ) || ( isset( $event['place'] ) && ! empty( $event['place'] ) ) ) : ?>
			<div class="cart light shadow">
				<svg xmlns="http://www.w3.org/2000/svg" width="74" height="74" viewBox="0 0 100 100">
					<path fill="currentColor"
						  d="M50.002 0C28.103 0 10 17.008 10 37.932c0 8.061 2.712 15.566 7.268 21.71l25.996 36.194l.109.135c2.05 2.538 4.37 4.262 7.094 4.004c2.724-.26 4.491-2.142 6.156-4.28l.088-.11l28.732-39.937l.018-.03c.671-1.151 1.166-2.323 1.588-3.464A35.903 35.903 0 0 0 90 37.932C90 17.009 71.9 0 50.002 0Zm0 5.318c19.148 0 34.674 14.762 34.674 32.614c0 4.296-.9 8.395-2.531 12.168l-.03.068l-.027.07c-.362.986-.756 1.892-1.223 2.694l-.004.007L52.37 92.484c-1.327 1.683-2.261 2.18-2.408 2.194c-.146.014-.854-.126-2.371-1.973l-25.9-36.033l-.075-.1c-3.968-5.313-6.29-11.726-6.29-18.64c0-17.851 15.529-32.614 34.677-32.614z"
						  color="currentColor"/>
					<path fill="currentColor"
						  d="M50.04 12.432c-2.268 0-4.19.791-5.772 2.373c-1.53 1.529-2.295 3.4-2.295 5.615c0 2.214.791 4.112 2.373 5.693c1.581 1.582 3.479 2.371 5.693 2.371c2.214 0 4.086-.79 5.615-2.37c1.582-1.582 2.373-3.48 2.373-5.694s-.791-4.086-2.373-5.615c-1.529-1.582-3.4-2.373-5.615-2.373zm3.89 20.832c.246 0-.49.037-.72.109l-15.448 3.65c-.967.309-1.44 1.17-1.063 1.934l.582 1.174c.379.765 1.47 1.149 2.45.861c.969-.285 1.716-.394 2.15-.394c.4 0 .449.057.451.058l.059.037c-.115-.072.09.089.232.567c.576 5.733.336 7.126.336 13.492c0 1.765-.307 2.955-.402 3.115l-.02.037c-.236.429-.459.595-.682.694c.056-.025-1.063.228-2.792.228c-1.055 0-1.91.672-1.91 1.5V61.5c0 .828.855 1.5 1.91 1.5H61.52c1.055 0 1.909-.672 1.909-1.5v-1.174c0-.828-.854-1.5-1.909-1.5c-1.65 0-2.697-.228-2.785-.264c-.294-.137-.512-.325-.722-.666c-.08-.165-.35-1.363-.35-3.144V34.764c0-.829-.856-1.5-1.91-1.5H53.93z"/>
				</svg>
				<div class="content">
					<h3>Pořadatel</h3>
					<p><?php
						$organiser = [];
						if ( ! empty( $event['organiser']['name'] ) ) {
							$organiser[] = $event['organiser']['name'];
						}
						//					if ( ! empty( $event['organiser']['street'] ) ) {
						//						$organiser[] = $event['organiser']['street'];
						//					}
						//					if ( ! empty( $event['organiser']['zip'] ) || ! empty( $event['organiser']['town'] ) ) {
						//						$organiser[] = ( $event['organiser']['zip'] ?? '' ) . ' ' . ( $event['organiser']['town'] ?? '' );
						//					}

						echo implode( ', ', $organiser );
						?></p>
					<h3>Místo konání</h3>
					<p><?php
						$place = [];
						if ( isset( $event['place'] ) && ! empty( $event['place'] ) ) {
							$place[] = $event['place'];
						}
						if ( isset( $event['district'] ) && ! empty( $event['district'] ) ) {
							$place[] = 'okr. ' . $event['district'];
						}
						echo implode( ', ', $place );
						?></p>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( isset( $event['start']['date'] ) && ! empty( $event['start']['date'] ) ) : ?>
			<div class="cart light shadow">
				<svg xmlns="http://www.w3.org/2000/svg" width="74" height="74" viewBox="0 0 100 100">
					<path fill="currentColor"
						  d="M21 32C9.459 32 0 41.43 0 52.94c0 4.46 1.424 8.605 3.835 12.012l14.603 25.244c2.045 2.672 3.405 2.165 5.106-.14l16.106-27.41c.325-.59.58-1.216.803-1.856A20.668 20.668 0 0 0 42 52.94C42 41.43 32.544 32 21 32Zm0 9.812c6.216 0 11.16 4.931 11.16 11.129c0 6.198-4.944 11.127-11.16 11.127c-6.215 0-11.16-4.93-11.16-11.127c0-6.198 4.945-11.129 11.16-11.129z"/>
					<path fill="currentColor" fill-rule="evenodd"
						  d="M88.209 37.412c-2.247.05-4.5.145-6.757.312l.348 5.532a126.32 126.32 0 0 1 6.513-.303zm-11.975.82c-3.47.431-6.97 1.045-10.43 2.032l1.303 5.361c3.144-.896 6.402-1.475 9.711-1.886zM60.623 42.12a24.52 24.52 0 0 0-3.004 1.583l-.004.005l-.006.002c-1.375.866-2.824 1.965-4.007 3.562c-.857 1.157-1.558 2.62-1.722 4.35l5.095.565c.038-.406.246-.942.62-1.446h.002v-.002c.603-.816 1.507-1.557 2.582-2.235l.004-.002a19.64 19.64 0 0 1 2.388-1.256zM58 54.655l-3.303 4.235c.783.716 1.604 1.266 2.397 1.726l.01.005l.01.006c2.632 1.497 5.346 2.342 7.862 3.144l1.446-5.318c-2.515-.802-4.886-1.576-6.918-2.73c-.582-.338-1.092-.691-1.504-1.068Zm13.335 5.294l-1.412 5.327l.668.208l.82.262c2.714.883 5.314 1.826 7.638 3.131l2.358-4.92c-2.81-1.579-5.727-2.611-8.538-3.525l-.008-.002l-.842-.269zm14.867 7.7l-3.623 3.92c.856.927 1.497 2.042 1.809 3.194l.002.006l.002.009c.372 1.345.373 2.927.082 4.525l5.024 1.072c.41-2.256.476-4.733-.198-7.178c-.587-2.162-1.707-4.04-3.098-5.548zM82.72 82.643a11.84 11.84 0 0 1-1.826 1.572h-.002c-1.8 1.266-3.888 2.22-6.106 3.04l1.654 5.244c2.426-.897 4.917-1.997 7.245-3.635l.006-.005l.003-.002a16.95 16.95 0 0 0 2.639-2.287zm-12.64 6.089c-3.213.864-6.497 1.522-9.821 2.08l.784 5.479c3.421-.575 6.856-1.262 10.27-2.18zm-14.822 2.836c-3.346.457-6.71.83-10.084 1.148l.442 5.522c3.426-.322 6.858-.701 10.285-1.17zm-15.155 1.583c-3.381.268-6.77.486-10.162.67l.256 5.536c3.425-.185 6.853-.406 10.28-.678zm-15.259.92c-2.033.095-4.071.173-6.114.245l.168 5.541a560.1 560.1 0 0 0 6.166-.246z"
						  color="currentColor"/>
				</svg>
				<div class="content">
					<h3>
						Start <?php echo $event['start']['date'] ? date( 'j. n. Y', strtotime( $event['start']['date'] ) ) : ''; ?></h3>
					<?php
					if ( ! empty( $event['start']['time'] ) ) {
						echo '<p>čas: ' . $event['start']['time'] . '</p>';
					}
					if ( ! empty( $event['start']['place'] ) ) {
						echo '<p>' . $event['start']['place'] . '</p>';
					} ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( isset( $event['finish']['date'] ) && ! empty( $event['finish']['date'] ) ) : ?>
			<div class="cart light shadow">
				<svg xmlns="http://www.w3.org/2000/svg" width="74" height="74" viewBox="0 0 100 100">
					<path fill="currentColor"
						  d="M87.75 0C81.018 0 75.5 5.501 75.5 12.216c0 2.601.83 5.019 2.237 7.006l8.519 14.726c1.193 1.558 1.986 1.262 2.978-.082l9.395-15.99c.19-.343.339-.708.468-1.082a12.05 12.05 0 0 0 .903-4.578C100 5.5 94.484 0 87.75 0Zm0 5.724c3.626 0 6.51 2.876 6.51 6.492c0 3.615-2.884 6.49-6.51 6.49c-3.625 0-6.51-2.875-6.51-6.49c0-3.616 2.885-6.492 6.51-6.492z"/>
					<path fill="currentColor" fill-rule="evenodd"
						  d="M88.209 37.412c-2.247.05-4.5.145-6.757.312l.348 5.532a126.32 126.32 0 0 1 6.513-.303zm-11.975.82c-3.47.431-6.97 1.045-10.43 2.032l1.303 5.361c3.144-.896 6.402-1.475 9.711-1.886zM60.623 42.12a24.52 24.52 0 0 0-3.004 1.583l-.004.005l-.006.002c-1.375.866-2.824 1.965-4.007 3.562c-.857 1.157-1.558 2.62-1.722 4.35l5.095.565c.038-.406.246-.942.62-1.446h.002v-.002c.603-.816 1.507-1.557 2.582-2.235l.004-.002a19.64 19.64 0 0 1 2.388-1.256zM58 54.655l-3.303 4.235c.783.716 1.604 1.266 2.397 1.726l.01.005l.01.006c2.632 1.497 5.346 2.342 7.862 3.144l1.446-5.318c-2.515-.802-4.886-1.576-6.918-2.73c-.582-.338-1.092-.691-1.504-1.068Zm13.335 5.294l-1.412 5.327l.668.208l.82.262c2.714.883 5.314 1.826 7.638 3.131l2.358-4.92c-2.81-1.579-5.727-2.611-8.538-3.525l-.008-.002l-.842-.269zm14.867 7.7l-3.623 3.92c.856.927 1.497 2.042 1.809 3.194l.002.006l.002.009c.372 1.345.373 2.927.082 4.525l5.024 1.072c.41-2.256.476-4.733-.198-7.178c-.587-2.162-1.707-4.04-3.098-5.548zM82.72 82.643a11.84 11.84 0 0 1-1.826 1.572h-.002c-1.8 1.266-3.888 2.22-6.106 3.04l1.654 5.244c2.426-.897 4.917-1.997 7.245-3.635l.006-.005l.003-.002a16.95 16.95 0 0 0 2.639-2.287zm-12.64 6.089c-3.213.864-6.497 1.522-9.821 2.08l.784 5.479c3.421-.575 6.856-1.262 10.27-2.18zm-14.822 2.836c-3.346.457-6.71.83-10.084 1.148l.442 5.522c3.426-.322 6.858-.701 10.285-1.17zm-15.155 1.583c-3.381.268-6.77.486-10.162.67l.256 5.536c3.425-.185 6.853-.406 10.28-.678zm-15.259.92c-2.033.095-4.071.173-6.114.245l.168 5.541a560.1 560.1 0 0 0 6.166-.246z"
						  color="currentColor"/>
				</svg>
				<div class="content">
					<h3>
						Cíl <?php echo $event['finish']['date'] ? date( 'j. n. Y', strtotime( $event['finish']['date'] ) ) : ''; ?></h3>
					<?php
					if ( ! empty( $event['finish']['time'] ) ) {
						echo '<p>čas: ' . $event['finish']['time'] . '</p>';
					}
					if ( ! empty( $event['finish']['place'] ) ) {
						echo '<p>' . $event['finish']['place'] . '</p>';
					} ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<div class="event-content-wrap">
		<div class="entry-content">
			<?php
			echo $event['content'];

			if ( $event['lng'] && $event['lat'] ) { ?>
				<div>
					<iframe style="border:none"
							src="https://frame.mapy.cz/?x=<?= $event['lng'] ?>&y=<?= $event['lat'] ?>&z=13"
							width="800" height="400" frameborder="0"></iframe>
				</div>
			<?php }
			if ( ! $db_event_id ) {
				kct_entry_footer();
			} else {
				$url = add_query_arg( array(
					'kct-action' => 'convert-action',
					'db_id'      => $db_event_id,
					'_wpnonce'   => wp_create_nonce( 'kct-convert-action' ),
				), admin_url( 'admin-post.php' ) );

				echo '<a class="" href="' . esc_url( $url ) . '">Převést na vlastní akci a upravit</a>';
			} ?>
		</div><!-- .entry-content -->
		<div class="event-sidebar">

			<?php
			if ( isset( $event['image'] ) && $event['image'] ) {
				printf( '<img src="%s" title="%2$s" alt="%2$s">', $event['image']['url'], $event['title'] );
			} elseif ( $image_url ) {
				kct_post_thumbnail();
			}
			if ( isset( $event['details'] ) && ! empty( $event['details'] ) ) :
				if ( isset( $event['details']['name'] ) && ! is_array( reset( $event['details'] ) ) ) {
					$event['details'] = [ $event['details'] ];
				}

				$details = [];
				foreach ( $event['details'] as $detail ) {
					$text      = $detail['name'] . ( $detail['km'] ? ': ' . $detail['km'] : '' );
					$details[] = sprintf( '<img src="%s" title="%s" width="30" height="30"> %s', $detail['icon'], $detail['name'], $text );
				}


				if ( $details ) {
					echo '<ul>';
					foreach ( $details as $item ) {
						echo '<li>' . $item . '</li>';
					}
					echo '</ul>';
				}
			endif;
			if ( isset( $event['contact'] ) && ! empty( $event['contact'] ) ) : ?>
				<h3>Kontakt</h3>
				<?php
				$contacts = 1;
				if ( ! empty( $event['contact']['person'] ) && is_array( $event['contact']['person'] ) ) {
					$contacts = count( $event['contact']['person'] );
				}
				for ( $k = 0; $k < $contacts; $k ++ ) {
					$input_data = array(
						'person' => ! isset( $event['contact']['person'] ) ? '' : ( is_array( $event['contact']['person'] ) && isset( $event['contact']['person'][ $k ] ) ? $event['contact']['person'][ $k ] : $event['contact']['person'] ),
						'street' => ! isset( $event['contact']['street'] ) ? '' : ( is_array( $event['contact']['street'] ) && isset( $event['contact']['street'][ $k ] ) ? $event['contact']['street'][ $k ] : $event['contact']['street'] ),
						'zip'    => ! isset( $event['contact']['zip'] ) ? '' : ( is_array( $event['contact']['zip'] ) && isset( $event['contact']['zip'][ $k ] ) ? $event['contact']['zip'][ $k ] : $event['contact']['zip'] ),
						'town'   => ! isset( $event['contact']['town'] ) ? '' : ( is_array( $event['contact']['town'] ) && isset( $event['contact']['town'][ $k ] ) ? $event['contact']['town'][ $k ] : $event['contact']['town'] ),
						'phone'  => ! isset( $event['contact']['phone'] ) ? '' : ( is_array( $event['contact']['phone'] ) && isset( $event['contact']['phone'][ $k ] ) ? $event['contact']['phone'][ $k ] : $event['contact']['phone'] ),
						'email'  => ! isset( $event['contact']['email'] ) ? '' : ( is_array( $event['contact']['email'] ) && isset( $event['contact']['email'][ $k ] ) ? $event['contact']['email'][ $k ] : $event['contact']['email'] ),
						'web'    => ! isset( $event['contact']['web'] ) ? '' : ( is_array( $event['contact']['web'] ) && isset( $event['contact']['web'][ $k ] ) ? $event['contact']['web'][ $k ] : $event['contact']['web'] ),
					);

					$data = [];
					foreach ( $input_data as $name => $value ) {
						if ( ! empty( $value ) ) {
							if ( $name === 'phone' ) {
								$data[] = sprintf( '<a href="tel:%1$s">%1$s</a>', $value );
							} elseif ( $name === 'email' ) {
								$data[] = sprintf( '<a href="mailto:%1$s">%1$s</a>', $value );
							} elseif ( $name === 'web' ) {
								$data[] = sprintf( '<a href="%1$s" target="_blank">%1$s</a>', $value );
							} else {
								$data[] = $value;
							}
						}
					}
					echo '<p>' . implode( ', ', $data ) . '</p>';
				}
				?>
			<?php endif;
			if ( isset( $event['proposal'] ) && ! empty( $event['proposal']['url'] ) ) : ?>
				<h3>Propozice</h3>
				<p>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20">
						<path fill="currentColor"
							  d="M17.924 7.154h-.514l.027-1.89a.464.464 0 0 0-.12-.298L12.901.134A.393.393 0 0 0 12.618 0h-9.24a.8.8 0 0 0-.787.784v6.37h-.515c-.285 0-.56.118-.76.328A1.14 1.14 0 0 0 1 8.275v5.83c0 .618.482 1.12 1.076 1.12h.515v3.99A.8.8 0 0 0 3.38 20h13.278c.415 0 .78-.352.78-.784v-3.99h.487c.594 0 1.076-.503 1.076-1.122v-5.83c0-.296-.113-.582-.315-.792a1.054 1.054 0 0 0-.76-.328ZM3.95 1.378h6.956v4.577a.4.4 0 0 0 .11.277a.37.37 0 0 0 .267.115h4.759v.807H3.95V1.378Zm0 17.244v-3.397h12.092v3.397H3.95ZM12.291 1.52l.385.434l2.58 2.853l.143.173h-2.637c-.2 0-.325-.033-.378-.1c-.053-.065-.084-.17-.093-.313V1.52ZM3 14.232v-6h1.918c.726 0 1.2.03 1.42.09c.34.09.624.286.853.588c.228.301.343.69.343 1.168c0 .368-.066.678-.198.93c-.132.25-.3.447-.503.59a1.72 1.72 0 0 1-.62.285c-.285.057-.698.086-1.239.086h-.779v2.263H3Zm1.195-4.985v1.703h.654c.471 0 .786-.032.945-.094a.786.786 0 0 0 .508-.762a.781.781 0 0 0-.19-.54a.823.823 0 0 0-.48-.266c-.142-.027-.429-.04-.86-.04h-.577Zm4.04-1.015h2.184c.493 0 .868.038 1.127.115c.347.103.644.288.892.552c.247.265.436.589.565.972c.13.384.194.856.194 1.418c0 .494-.06.92-.182 1.277c-.148.437-.36.79-.634 1.06c-.207.205-.487.365-.84.48c-.263.084-.616.126-1.057.126H8.235v-6ZM9.43 9.247v3.974h.892c.334 0 .575-.019.723-.057c.194-.05.355-.132.482-.25c.128-.117.233-.31.313-.579c.081-.269.121-.635.121-1.099c0-.464-.04-.82-.12-1.068a1.377 1.377 0 0 0-.34-.581a1.132 1.132 0 0 0-.553-.283c-.167-.038-.494-.057-.98-.057H9.43Zm4.513 4.985v-6H18v1.015h-2.862v1.42h2.47v1.015h-2.47v2.55h-1.195Z"/>
					</svg>
					<?php printf( '<a href="%s" target="_blank">%s</a>', $event['proposal']['url'], $event['proposal']['name'] ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</article><!-- #post-<?php the_ID(); ?> -->
