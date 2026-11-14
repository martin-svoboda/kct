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

// Výpis se krájí po částech: nechodí přes WP_Query (sdružuje příspěvky
// s databází KČT), takže `posts_per_page` se ho netýká, a bez krájení byl
// na mobilu jeden seznam dlouhý tisíce pixelů. Další část se pod výpis
// dotahuje (kct_events_more), seznam se tedy nepřekresluje a nic v něm
// neuskočí; `kct_page` v adrese je tu pro prohlížeč bez JavaScriptu.
$kct_total    = count( $kct_events );
$kct_per_page = kct_events_per_page();
$kct_page     = max( 1, (int) ( $_GET['kct_page'] ?? 1 ) );
$kct_pages    = max( 1, (int) ceil( $kct_total / $kct_per_page ) );
$kct_page     = min( $kct_page, $kct_pages );
// Část se počítá od začátku, ne od okraje jedné stránky: `kct_page=2` znamená
// „prvních padesát", ne „druhá pětadvacítka". Výpis tak jen roste — kdo přijde
// na dotaženou adresu bez JavaScriptu, uvidí totéž, co by měl po dotažení,
// a ne seznam začínající uprostřed.
$kct_slice    = array_slice( $kct_events, 0, $kct_page * $kct_per_page );

// Parametry filtru, které musí odkaz na další část zachovat.
$kct_filter_args = array(
	'date_from' => $kct_date_from,
	'date_to'   => $kct_date_to,
	'type'      => $kct_type,
);

// Mapa je volitelná (Customizer → Vzhled šablony). Některé odbory ji nechtějí.
$kct_show_map = (bool) get_theme_mod( 'kct_events_map', true );

if ( $kct_show_map ) :
	// Markery pro mapu — ze VŠECH nalezených akcí, ne jen ze zobrazené části
	// výpisu. Mapa je přehled kraje: kdyby ukazovala jen dvacet pět akcí
	// z první stránky, vypadalo by to, že v půlce oblasti se nic nekoná.
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

	<?php
	// Mapa je plnoformátový pruh NAD stránkou, zakončený barvami značek — je to
	// prvek rozvržení, ne obsah výpisu, a do toku stránky nepatří. Na mobilu je
	// nižší (viz components/map.scss), aby nesnědla celou první obrazovku.
	?>
	<div class="kct-map"><div id="map" data-map-label="<?php esc_attr_e( 'Mapa akcí', 'kct' ); ?>"></div></div>
<?php endif; ?>

<div class="events-archive">
	<header class="events-archive__head">
		<?php get_template_part( 'template-parts/breadcrumbs', null, array( 'muted' => true ) ); ?>
		<h1 class="entry-title"><?php echo esc_html( get_theme_mod( 'kct_akce_archive_title' ) ?: post_type_archive_title( '', false ) ); ?></h1>
	</header>

	<div class="events-archive__inner">
		<?php
		// Rychlé rozsahy a zrušení filtru. Jsou to odkazy, ne tlačítka pro skript:
		// každý rozsah má vlastní adresu, dá se poslat a funguje i bez JavaScriptu.
		$kct_ranges      = kct_events_quick_ranges( $kct_today, $kct_date_from, $kct_date_to, $kct_type );
		$kct_is_filtered = ( $kct_date_from !== $kct_today || $kct_date_to !== $kct_next_year || '' !== $kct_type );

		// Co je zrovna nastavené, napsané do jednoho řádku. Na mobilu je filtr
		// sbalený, takže tohle je jediné, co o něm zavřený box řekne — bez toho by
		// člověk viděl krátký výpis a nevěděl, čím je zúžený.
		$kct_state = array();

		foreach ( $kct_ranges as $kct_range ) {
			if ( $kct_range['current'] ) {
				$kct_state[] = $kct_range['label'];
				break;
			}
		}

		if ( ! $kct_state ) {
			$kct_state[] = sprintf(
				/* translators: 1: datum od, 2: datum do. */
				__( '%1$s – %2$s', 'kct' ),
				date_i18n( 'j. n. Y', strtotime( $kct_date_from ) ),
				date_i18n( 'j. n. Y', strtotime( $kct_date_to ) )
			);
		}

		if ( '' !== $kct_type ) {
			foreach ( $kct_event_types as $kct_et ) {
				if ( ! empty( $kct_et['detailid'] ) && (string) $kct_et['detailid'] === $kct_type ) {
					$kct_state[] = (string) ( $kct_et['name'] ?? '' );
					break;
				}
			}
		}
		?>
		<aside class="events-filter">
			<?php
			// Celý filtr je jeden sbalitelný box. Ze serveru je rozbalený, aby byl
			// bez JavaScriptu prostě vidět; na úzké obrazovce ho skript zavře
			// (assets/apps/events-archive.js), protože tam je výpis to, pro co lidé
			// na stránku chodí, a filtr ho tlačil pod okraj obrazovky.
			//
			// Na `<details>` se NESMÍ nastavit `display` — prohlížeč tím přestane
			// v zavřeném stavu schovávat obsah a pole zůstanou vidět. Rozvržení
			// proto drží `__body` a `__fields` uvnitř.
			?>
			<details class="events-filter__box" data-events-filter-box open>
				<summary class="events-filter__summary">
					<span class="events-filter__summary-title"><?php esc_html_e( 'Filtr akcí', 'kct' ); ?></span>
					<span class="events-filter__summary-state"><?php echo esc_html( implode( ' · ', array_filter( $kct_state ) ) ); ?></span>
				</summary>
				<div class="events-filter__body">
					<div class="events-filter__ranges">
						<span class="events-filter__ranges-label" id="events-ranges-label"><?php esc_html_e( 'Rychlá volba', 'kct' ); ?></span>
						<ul aria-labelledby="events-ranges-label">
							<?php foreach ( $kct_ranges as $kct_range ) : ?>
								<li>
									<a href="<?php echo esc_url( $kct_range['url'] ); ?>"<?php echo $kct_range['current'] ? ' aria-current="true" class="is-current"' : ''; ?>><?php echo esc_html( $kct_range['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
							<?php if ( $kct_is_filtered ) : ?>
								<li>
									<a class="events-filter__reset" href="<?php echo esc_url( (string) get_post_type_archive_link( 'akce' ) ); ?>"><?php esc_html_e( 'Zrušit filtr', 'kct' ); ?></a>
								</li>
							<?php endif; ?>
						</ul>
					</div>

					<?php
					// `max` na „Od" a `min` na „Do" drží pole svázaná: bez nich šlo
					// nastavit konec před začátkem a výpis se vyprázdnil, aniž by
					// kdokoli řekl proč. Skript je po každé změně přepisuje, aby
					// vazba platila i bez reloadu.
					?>
					<form class="events-filter__inner" method="get" data-events-filter aria-label="<?php esc_attr_e( 'Filtr akcí', 'kct' ); ?>">
						<div class="events-filter__fields">
							<div class="events-filter__field">
								<label for="date-from"><?php esc_html_e( 'Od', 'kct' ); ?></label>
								<input type="date" id="date-from" name="date_from" value="<?php echo esc_attr( $kct_date_from ); ?>" max="<?php echo esc_attr( $kct_date_to ); ?>">
							</div>
							<div class="events-filter__field">
								<label for="date-to"><?php esc_html_e( 'Do', 'kct' ); ?></label>
								<input type="date" id="date-to" name="date_to" value="<?php echo esc_attr( $kct_date_to ); ?>" min="<?php echo esc_attr( $kct_date_from ); ?>">
							</div>
							<?php if ( ! empty( $kct_event_types ) ) : ?>
								<div class="events-filter__field">
									<label for="type"><?php esc_html_e( 'Typ akce', 'kct' ); ?></label>
									<select id="type" name="type">
										<option value=""><?php esc_html_e( 'Všechny', 'kct' ); ?></option>
										<?php foreach ( $kct_event_types as $kct_et ) : ?>
											<?php if ( empty( $kct_et['detailid'] ) ) { continue; } ?>
											<option value="<?php echo esc_attr( $kct_et['detailid'] ); ?>" <?php selected( $kct_type, $kct_et['detailid'] ); ?>><?php echo esc_html( $kct_et['name'] ?? '' ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>
							<noscript><button type="submit" class="events-filter__submit"><?php esc_html_e( 'Filtrovat', 'kct' ); ?></button></noscript>
						</div>
					</form>
				</div>
			</details>
		</aside>
		<main id="primary" class="site-main events-main">
			<?php
			// `role="status"` schválně, ne `aria-live` na výpisu samotném: výpis má
			// desítky položek a odečítač obrazovky by po filtrování předčítal celý.
			// Tady se změní jediná věta, a to je přesně to, co má člověk slyšet.
			?>
			<p class="events-count" role="status" data-events-count><?php echo esc_html( kct_events_found_label( $kct_total ) ); ?></p>
			<div class="events">
				<?php if ( empty( $kct_slice ) ) : ?>
					<?php echo kct_events_empty_html( array(
						'date_from' => $kct_date_from,
						'date_to'   => $kct_date_to,
						'type'      => $kct_type,
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — hotové HTML z jedné funkce ?>
				<?php else : ?>
					<ul class="events-list">
						<?php foreach ( $kct_slice as $kct_event ) { kct_render_event_item( $kct_event, 2 ); } ?>
					</ul>
					<?php echo kct_events_more( $kct_total, $kct_page, $kct_filter_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — odkaz je escapovaný v helperu ?>
				<?php endif; ?>
			</div>
		</main>
	</div>
</div>
<?php
get_footer();
