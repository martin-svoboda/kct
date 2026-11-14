<?php
/**
 * „Další ročníky“ — tatáž akce v jiných letech, od nejnovějšího k nejstaršímu.
 *
 * Série se skládá podle názvu z centrální databáze a odboru (viz
 * kct_event_series_key()), takže drží i u čistě vlastní akce, která v
 * centrální databázi není vůbec.
 *
 * Položka má stejnou stavbu jako karta výpisu akcí (kct_render_event_item):
 * vlevo boxík, vpravo nadpis a meta. Místo data je v boxíku číslo ročníku —
 * datum se tu neopakuje jako hlavní údaj, protože ročník je to, čím se
 * položky v tomhle seznamu od sebe liší.
 *
 * Vypisuje se jako poslední prvek postranního oddílu akce. Je-li oddíl
 * vypnutý (`details_position = hide`), nevypíše se ani tohle — oddíl je
 * jeden celek.
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

$kct_series = kct_event_series( $args['events'] ?? array(), $kct_current );

if ( ! $kct_series ) {
	return;
}

$kct_today = current_time( 'Y-m-d' );
?>
<section class="event-series">
	<h2 class="event-series__title event-section__title"><?php esc_html_e( 'Další ročníky', 'kct' ); ?></h2>
	<ul class="event-series__list">
		<?php
		foreach ( $kct_series as $kct_item ) :
			$kct_date  = (string) ( $kct_item['date'] ?? '' );
			$kct_stamp = strtotime( $kct_date );
			$kct_year  = (int) ( $kct_item['year'] ?? 0 );
			$kct_past  = strcmp( $kct_date, $kct_today ) < 0;

			// Bez vyplněného ročníku zbývá jediné, čím se položka dá označit:
			// rok konání. Popisek se mění s ním, ať boxík nelže.
			$kct_box_num   = $kct_year > 0 ? (string) $kct_year : date_i18n( 'Y', $kct_stamp );
			$kct_box_label = $kct_year > 0 ? __( 'ročník', 'kct' ) : __( 'rok', 'kct' );
			?>
			<li class="event-series__item">
				<a class="event-series__link" href="<?php echo esc_url( $kct_item['permalink'] ?? '#' ); ?>">
					<span class="event-series__box">
						<span class="event-series__box-head"><?php echo esc_html( $kct_box_label ); ?></span>
						<span class="event-series__box-num"><?php echo esc_html( $kct_box_num ); ?></span>
					</span>
					<span class="event-series__content">
						<span class="event-series__name"><?php echo esc_html( $kct_item['title'] ?? '' ); ?></span>
						<span class="event-series__meta">
							<span class="event-series__state event-series__state--<?php echo $kct_past ? 'past' : 'future'; ?>">
								<?php echo $kct_past ? esc_html__( 'Proběhlo', 'kct' ) : esc_html__( 'Chystá se', 'kct' ); ?>
							</span>
							<span class="event-series__date"><?php echo esc_html( date_i18n( 'j. n. Y', $kct_stamp ) ); ?></span>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
