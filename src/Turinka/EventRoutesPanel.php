<?php

namespace Kct\Turinka;

/**
 * Obsah panelu „Trasy v Turince“.
 *
 * Ukazuje, po kterých trasách akce vede, a dovolí vazby přidávat a rušit.
 * Trasy samotné nevlastní web — upravují se v Turince, odsud se jen váže.
 *
 * Panel je na dvou místech: u akce s příspěvkem v postranním sloupci editoru
 * (EventRoutesMetabox) a v přehledu akcí pod řádkem (EventsOverviewTable) —
 * i u akcí, které příspěvek nemají. Proto je vykreslení samostatná třída
 * a akce se mu předává odkazem, ne id příspěvku.
 *
 * Vrací se jen **vnitřek** panelu; obal s odkazem na akci kreslí to, co panel
 * zasazuje do stránky. Po každé změně vazby posílá vnitřek REST endpoint
 * k překreslení, takže administrace se neobnovuje.
 */
class EventRoutesPanel {

	public function __construct( private EventRoutes $routes ) {
	}

	/**
	 * @param array{post_id?: int, turinka_id?: int} $ref Odkaz na akci.
	 */
	public function html( array $ref ): string {
		$state = $this->routes->state( $ref );

		if ( ! $state['known'] ) {
			return sprintf(
				'<p>%s</p>',
				esc_html__( 'Turinka tuhle akci zatím nezná. Jakmile ji bude znát, dají se tu k ní přivázat trasy.', 'kct' )
			);
		}

		$out = $this->list_html( $state['routes'], (bool) $state['editable'] );

		if ( ! $state['editable'] ) {
			return $out . sprintf( '<p class="description">%s</p>', esc_html( $state['message'] ) );
		}

		$out .= sprintf(
			'<p><label class="screen-reader-text" for="%1$s">%2$s</label>'
			. '<input type="search" id="%1$s" class="widefat" autocomplete="off" placeholder="%3$s" data-kct-event-routes="search"></p>'
			. '<ul class="kct-route-results" data-kct-event-routes="results"></ul>',
			esc_attr( 'kct-route-search-' . (int) $state['id'] ),
			esc_html__( 'Hledat trasu', 'kct' ),
			esc_attr__( 'Hledat trasu v Turince…', 'kct' )
		);

		$out .= sprintf(
			'<p><button type="button" class="button" data-kct-event-routes="create">%s</button></p>',
			esc_html__( 'Založit trasu k této akci', 'kct' )
		);

		$out .= sprintf(
			'<p class="description">%s</p>',
			esc_html__( 'Přivázat jde i trasu, kterou založil někdo jiný — rozhoduje, že akce patří vašemu webu.', 'kct' )
		);

		return $out;
	}

	/**
	 * Obal panelu s odkazem na akci.
	 *
	 * Odkaz drží obal, ne jednotlivá tlačítka: panel se po každé změně
	 * překresluje a skript si ho z obalu přečte znovu.
	 *
	 * @param array{post_id?: int, turinka_id?: int} $ref
	 */
	public function wrap( array $ref, string $inner ): string {
		return sprintf(
			'<div data-kct-event-routes="panel" data-post="%d" data-turinka="%d">%s</div>',
			(int) ( $ref['post_id'] ?? 0 ),
			(int) ( $ref['turinka_id'] ?? 0 ),
			$inner
		);
	}

	/**
	 * Seznam navázaných tras.
	 *
	 * Vazby nese detail akce, a ten o trase říká jen název a adresu — délka
	 * a převýšení tu proto nejsou; u výsledků hledání ano.
	 *
	 * @param array<int,array> $routes
	 */
	private function list_html( array $routes, bool $editable ): string {
		if ( ! $routes ) {
			return sprintf(
				'<p class="description">%s</p>',
				esc_html__( 'Zatím tu není žádná trasa.', 'kct' )
			);
		}

		$items = '';

		foreach ( $routes as $route ) {
			$title = (string) ( $route['title'] ?? '' );
			$url   = (string) ( $route['url'] ?? '' );

			$name = '' === $url
				? esc_html( $title )
				: sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $title ) );

			$remove = $editable
				? sprintf(
					' <button type="button" class="button-link kct-route-remove" data-kct-event-routes="unlink" data-route="%d" aria-label="%s">&times;</button>',
					(int) ( $route['id'] ?? 0 ),
					/* translators: %s: název trasy. */
					esc_attr( sprintf( __( 'Odebrat vazbu na trasu %s', 'kct' ), $title ) )
				)
				: '';

			$items .= sprintf( '<li>%s%s</li>', $name, $remove );
		}

		return sprintf( '<ul class="kct-route-list">%s</ul>', $items );
	}
}
