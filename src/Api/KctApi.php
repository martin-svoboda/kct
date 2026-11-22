<?php

namespace Kct\Api;

use Kct\Features\Events;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class KctApi extends WP_REST_Controller {
	/** @var string */
	protected $namespace = 'kct/v1';

	/** @var string */
	protected $nonce_action = 'wp_rest';

	public function __construct(
		private Events $events
	) {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	/**
	 * Register the routes for the objects of the controller.
	 *
	 * Jediná veřejná routa je `events-list`, a to proto, že vydává hotové HTML
	 * výpisu — tedy právě to, co je na stránce vidět i bez JS, nic víc.
	 *
	 * Vedle ní tu kdysi stály routy, které vracely akce a odbory jako data
	 * (`events`, `event-types`, `departments`). Vznikly pro výpisy skládané
	 * v prohlížeči; ty se mezitím vrátily do PHP a routy zůstaly bez jediného
	 * konzumenta. Nebyly ale bez následků: v datech akce i odboru je kontakt,
	 * takže anonymní požadavek vydal e-maily a telefony všech organizátorů
	 * a odborů najednou, ve strojově čitelném tvaru a bez omezení počtu —
	 * pohodlněji, než se dají posbírat ze stránek. Čtení z logu ukázalo, že
	 * tak je používaly jedině prolézací roboty, které si je našly v indexu
	 * rout na `/wp-json/kct/v1`.
	 *
	 * Nová routa sem tedy patří jen tehdy, když ji něco volá, a kontakt
	 * z jejího výstupu nevypadne jako přívažek dat o akci.
	 */
	public function register_routes() {
		// Vyfiltrovaný výpis akcí jako HOTOVÉ HTML + data markerů pro mapu.
		// Používá AJAX filtr na archivu /akce/ (progresivní vylepšení nad PHP výpisem).
		register_rest_route(
			$this->namespace,
			'events-list',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events_list' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Vyfiltrovaný výpis akcí jako HTML (stejný markup jako PHP archiv) + markery.
	 *
	 * Vrací dvě podoby téhož: `html` je celý seznam (tím skript nahrazuje výpis
	 * po změně filtru) a `items` jsou jen karty té části, o kterou se ptal
	 * (ty skript připojuje pod už načtené, aby se výpis nehnul). `more` je
	 * stav tlačítka pro další část, prázdný řetězec znamená „už nic nezbývá".
	 *
	 * `count` a `countLabel` jsou tu proto, že výpis mění skript: bez nich by se
	 * věta o počtu nalezených akcí po filtrování rozešla s tím, co je vidět, a
	 * odečítač obrazovky by o změně nedostal zprávu vůbec.
	 *
	 * @return WP_REST_Response { html: string, items: string, more: string, markers: array, count: int, countLabel: string }
	 */
	public function get_events_list( WP_REST_Request $request ): WP_REST_Response {
		$date_from  = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to    = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		$type       = sanitize_text_field( (string) $request->get_param( 'type' ) );
		$department = sanitize_text_field( (string) $request->get_param( 'department' ) );

		$events = $this->events->get_events(
			$date_from ?: null,
			$date_to ?: null,
			$type,
			$department
		);

		$total = count( $events );

		// Výpis se krájí stejně jako v archivu, aby skript neukázal všech sto akcí
		// tam, kde jich PHP vypsalo pětadvacet. `kct_page` je číslo části, která se
		// má dotáhnout; `items` jsou karty jen z ní, `html` je celý seznam od
		// začátku — podle toho, jestli skript připojuje, nebo nahrazuje.
		$per_page = function_exists( 'kct_events_per_page' ) ? kct_events_per_page() : 25;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = max( 1, (int) $request->get_param( 'kct_page' ) );
		$page     = min( $page, $pages );
		$chunk    = array_slice( $events, ( $page - 1 ) * $per_page, $per_page );
		$slice    = array_slice( $events, 0, $page * $per_page );

		$more = function_exists( 'kct_events_more' )
			? kct_events_more( $total, $page, array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'type'      => $type,
			) )
			: '';

		ob_start();
		foreach ( $chunk as $event ) {
			if ( function_exists( 'kct_render_event_item' ) ) {
				kct_render_event_item( $event, 2 );
			}
		}
		$items = ob_get_clean();

		ob_start();
		if ( empty( $slice ) ) {
			echo function_exists( 'kct_events_empty_html' )
				? kct_events_empty_html( array(
					'date_from' => $date_from,
					'date_to'   => $date_to,
					'type'      => $type,
				) )
				: '<div class="events-empty">' . esc_html__( 'Nenašli jsme žádnou akci.', 'kct' ) . '</div>';
		} else {
			echo '<ul class="events-list">';
			foreach ( $slice as $event ) {
				if ( function_exists( 'kct_render_event_item' ) ) {
					// Druhá úroveň, protože tenhle výpis nahrazuje seznam
					// v archivu akcí — tam je nad kartami jen h1 stránky.
					kct_render_event_item( $event, 2 );
				}
			}
			echo '</ul>';
			echo $more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- odkaz je escapovaný v helperu
		}
		$html = ob_get_clean();

		// Markery ze VŠECH nalezených akcí, ne jen ze zobrazené části — mapa je
		// přehled kraje, ne obsah jedné stránky výpisu.
		$markers = array_map(
			static function ( $e ) {
				return array(
					'title'         => $e['title'] ?? '',
					'permalink'     => $e['permalink'] ?? '',
					'lat'           => $e['lat'] ?? '',
					'lng'           => $e['lng'] ?? '',
					'formated_date' => $e['formated_date'] ?? null,
				);
			},
			$events
		);

		return new WP_REST_Response(
			array(
				'html'       => $html,
				'items'      => $items,
				'more'       => $more,
				'markers'    => $markers,
				'count'      => $total,
				'countLabel' => function_exists( 'kct_events_found_label' ) ? kct_events_found_label( $total ) : '',
			),
			200
		);
	}
}
