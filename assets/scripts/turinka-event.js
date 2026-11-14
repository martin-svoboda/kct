/**
 * Panel „Trasy v Turince“ — u akce v editoru i v přehledu akcí.
 *
 * Vazby přidává a ruší server, protože token nesmí do prohlížeče. Odpověď
 * vrací rovnou překreslený panel, takže se to, co je na obrazovce, nikdy
 * nerozejde s tím, co o akci říká Turinka.
 *
 * Na stránce může být panelů víc (přehled akcí má řádek na akci), takže se
 * všechno hledá uvnitř panelu, ke kterému prvek patří — nikdy přes celý
 * dokument. Akci panel nese v datech obalu, ne v jednotlivých tlačítkách:
 * vnitřek se překresluje, obal zůstává.
 *
 * Psáno řetězením `.then()`, ne `async/await`: build převádí async na
 * regenerator, jehož běhové prostředí se do balíčku nepřidává.
 */
import apiFetch from '@wordpress/api-fetch';

const settings = window.kctTurinkaEvent || {};
const DEBOUNCE = 350;
const PANEL = '[data-kct-event-routes="panel"]';

let timer = null;

function panelOf( element ) {
	return element ? element.closest( PANEL ) : null;
}

/** Odkaz na akci, se kterou panel pracuje. */
function ref( panel ) {
	return {
		post_id: parseInt( panel.getAttribute( 'data-post' ), 10 ) || 0,
		turinka_id: parseInt( panel.getAttribute( 'data-turinka' ), 10 ) || 0,
	};
}

function repaint( panel, response ) {
	if ( panel && response && response.panel ) {
		panel.innerHTML = response.panel;
	}
}

function fail( panel, error ) {
	if ( ! panel ) {
		return;
	}

	const box = document.createElement( 'div' );
	box.className = 'notice notice-error inline';
	box.innerHTML = '<p></p>';
	box.querySelector( 'p' ).textContent =
		( error && error.message ) || settings.failed;

	const old = panel.querySelector( '.notice' );

	if ( old ) {
		old.remove();
	}

	panel.insertBefore( box, panel.firstChild );
}

function post( path, data ) {
	return apiFetch( {
		path: '/kct/v1/turinka/event-routes/' + path,
		method: 'POST',
		data,
	} );
}

/** Výsledky hledání: jeden klik = jedna vazba. */
function showResults( panel, routes ) {
	const list = panel.querySelector( '[data-kct-event-routes="results"]' );

	if ( ! list ) {
		return;
	}

	list.innerHTML = '';

	if ( ! routes.length ) {
		const empty = document.createElement( 'li' );
		empty.className = 'description';
		empty.textContent = settings.noResults;
		list.appendChild( empty );

		return;
	}

	routes.forEach( function ( route ) {
		const item = document.createElement( 'li' );
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.setAttribute( 'data-kct-event-routes', 'link' );
		button.setAttribute( 'data-route', route.id );
		button.innerHTML = '<strong></strong><br><span class="description"></span>';
		button.querySelector( 'strong' ).textContent = route.title;
		button.querySelector( 'span' ).textContent = route.meta;
		item.appendChild( button );
		list.appendChild( item );
	} );
}

function search( input ) {
	const panel = panelOf( input );
	const query = input.value.trim();

	if ( ! panel ) {
		return;
	}

	window.clearTimeout( timer );

	if ( query.length < 2 ) {
		showResults( panel, [] );

		return;
	}

	timer = window.setTimeout( function () {
		post( 'search', Object.assign( ref( panel ), { q: query } ) )
			.then( function ( response ) {
				showResults( panel, response.routes || [] );
			} )
			.catch( function ( error ) {
				fail( panel, error );
			} );
	}, DEBOUNCE );
}

/** Formulář na novou trasu, rovnou s vazbou na tuhle akci. */
function createRoute( button ) {
	const panel = panelOf( button );

	if ( ! panel ) {
		return;
	}

	if ( ! window.TurinkaForm || typeof window.TurinkaForm.open !== 'function' ) {
		fail( panel, { message: settings.hostMissing } );

		return;
	}

	const event = ref( panel );

	button.disabled = true;

	apiFetch( {
		path: '/kct/v1/turinka/form',
		method: 'POST',
		data: {
			type: 'routes',
			event_post_id: event.post_id,
			event_turinka_id: event.turinka_id,
		},
	} )
		.then( function ( response ) {
			button.disabled = false;

			window.TurinkaForm.open( {
				url: response.form_url,
				onSaved: function () {
					// Vazbu dopisovat netřeba — formulář ji má předvyplněnou.
					post( 'panel', Object.assign( ref( panel ), { obnovit: true } ) )
						.then( function ( result ) {
							repaint( panel, result );
						} )
						.catch( function ( error ) {
							fail( panel, error );
						} );
				},
				onCancel: function () {},
			} );
		} )
		.catch( function ( error ) {
			button.disabled = false;
			fail( panel, error );
		} );
}

/**
 * Otevření a zavření panelu pod řádkem přehledu akcí.
 *
 * Obsah se načítá až při prvním otevření — řádků je na stránce padesát.
 */
function toggleRow( link ) {
	const row = document.getElementById( link.getAttribute( 'data-row' ) );

	if ( ! row ) {
		return;
	}

	const open = row.hasAttribute( 'hidden' );

	link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

	if ( ! open ) {
		row.setAttribute( 'hidden', '' );

		return;
	}

	row.removeAttribute( 'hidden' );

	const panel = row.querySelector( PANEL );

	if ( ! panel || panel.getAttribute( 'data-loaded' ) === '1' ) {
		return;
	}

	panel.setAttribute( 'data-loaded', '1' );

	post( 'panel', ref( panel ) )
		.then( function ( response ) {
			repaint( panel, response );
		} )
		.catch( function ( error ) {
			panel.removeAttribute( 'data-loaded' );
			fail( panel, error );
		} );
}

document.addEventListener( 'input', function ( event ) {
	const input = event.target.closest( '[data-kct-event-routes="search"]' );

	if ( input ) {
		search( input );
	}
} );

// Delegovaně: panel se po každé změně překresluje, takže se na jeho prvky
// nedá navázat napevno.
document.addEventListener( 'click', function ( event ) {
	const button = event.target.closest( '[data-kct-event-routes]' );

	if ( ! button ) {
		return;
	}

	const action = button.getAttribute( 'data-kct-event-routes' );

	if ( 'toggle' === action ) {
		event.preventDefault();
		toggleRow( button );

		return;
	}

	if ( 'create' === action ) {
		event.preventDefault();
		createRoute( button );

		return;
	}

	if ( 'link' !== action && 'unlink' !== action ) {
		return;
	}

	const panel = panelOf( button );

	if ( ! panel ) {
		return;
	}

	event.preventDefault();
	button.disabled = true;

	post( action, Object.assign( ref( panel ), {
		route_id: button.getAttribute( 'data-route' ),
	} ) )
		.then( function ( response ) {
			repaint( panel, response );
		} )
		.catch( function ( error ) {
			button.disabled = false;
			fail( panel, error );
		} );
} );
