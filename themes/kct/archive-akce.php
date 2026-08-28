<?php
/**
 * Archiv akcí (/akce/).
 *
 * Výpis i filtr jsou v PHP (SEO, funguje bez JS). Filtr je nativní <form method="get">;
 * bez JS reloadne stránku s parametry v URL. JS (events-archive.js) submit odchytne a
 * filtruje přes AJAX bez reloadu. Mapa je vanilla Leaflet (map.js), markery bere ze
 * serveru (window.kctMarkers). Žádný React.
 *
 * @package kct
 */

get_header();

$events_feature = kct_container()->get( \Kct\Features\Events::class );

// Filtr z URL (fallback bez JS). Defaulty stejné jako dřív: dnešek → +1 rok.
$kct_today     = current_time( 'Y-m-d' );
$kct_next_year = date( 'Y-m-d', strtotime( $kct_today . ' +1 year' ) );

$kct_date_from = ( isset( $_GET['date_from'] ) && $_GET['date_from'] !== '' ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : $kct_today;
$kct_date_to   = ( isset( $_GET['date_to'] ) && $_GET['date_to'] !== '' ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : $kct_next_year;
$kct_type      = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';

$kct_events      = $events_feature->get_events( $kct_date_from ?: null, $kct_date_to ?: null, $kct_type );
$kct_event_types = $events_feature->get_event_types();

// Mapa je volitelná (Customizer → Vzhled šablony). Některé odbory ji nechtějí.
$kct_show_map = (bool) get_theme_mod( 'kct_events_map', true );

if ( $kct_show_map ) :
	// Markery pro mapu (jen potřebná pole).
	$kct_markers = array_values( array_map( static function ( $e ) {
		return array(
			'title'         => $e['title'] ?? '',
			'permalink'     => $e['permalink'] ?? '',
			'lat'           => $e['lat'] ?? '',
			'lng'           => $e['lng'] ?? '',
			'formated_date' => $e['formated_date'] ?? null,
		);
	}, $kct_events ) );
	?>
	<script>window.kctMarkers = <?php echo wp_json_encode( $kct_markers ); ?>;</script>

	<div class="kct-map"><div id="map"></div></div>
<?php endif; ?>

<div class="events-archive">
	<header class="events-archive__head">
		<h1 class="entry-title"><?php echo esc_html( get_theme_mod( 'kct_akce_archive_title' ) ?: post_type_archive_title( '', false ) ); ?></h1>
	</header>
	<div class="events-archive__inner">
		<aside class="events-filter">
			<form class="events-filter__inner" method="get" data-events-filter>
				<div class="events-filter__field">
					<label for="date-from">Od</label>
					<input type="date" id="date-from" name="date_from" value="<?php echo esc_attr( $kct_date_from ); ?>">
				</div>
				<div class="events-filter__field">
					<label for="date-to">Do</label>
					<input type="date" id="date-to" name="date_to" value="<?php echo esc_attr( $kct_date_to ); ?>">
				</div>
				<?php if ( ! empty( $kct_event_types ) ) : ?>
					<div class="events-filter__field">
						<label for="type">Typ akce</label>
						<select id="type" name="type">
							<option value="">Všechny</option>
							<?php foreach ( $kct_event_types as $kct_et ) : ?>
								<?php if ( empty( $kct_et['detailid'] ) ) { continue; } ?>
								<option value="<?php echo esc_attr( $kct_et['detailid'] ); ?>" <?php selected( $kct_type, $kct_et['detailid'] ); ?>><?php echo esc_html( $kct_et['name'] ?? '' ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
				<noscript><button type="submit" class="events-filter__submit">Filtrovat</button></noscript>
			</form>
		</aside>
		<main id="primary" class="site-main events-main">
			<div class="events">
				<?php if ( empty( $kct_events ) ) : ?>
					<div class="events-empty">Je nám líto, ale nebyly nalezeny žádné akce.</div>
				<?php else : ?>
					<ul class="events-list">
						<?php foreach ( $kct_events as $kct_event ) { kct_render_event_item( $kct_event ); } ?>
					</ul>
				<?php endif; ?>
			</div>
		</main>
	</div>
</div>
<?php
get_footer();
