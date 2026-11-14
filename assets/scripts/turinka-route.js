/**
 * Tlačítka metaboxu trasy: lístek ze serveru → formulář Turinky v rámu nad
 * stránkou → po uložení si nechat metabox překreslit.
 *
 * Stav propojení se nebere z toho, co ohlásí rám (`onSaved`), ale z odpovědi
 * serveru — rám běží na cizí doméně a jeho zpráva je pro nás jen signál
 * „podívej se, co se stalo“.
 *
 * Záměrně bez `async`/`await`: build šablony async funkce přepisuje na
 * regenerator, jehož runtime v bundlu není, takže by skript spadl na
 * `regeneratorRuntime is undefined` ještě před prvním kliknutím.
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const BOX = 'kct-turinka-route';

function box() {
	return document.getElementById( BOX );
}

function inner() {
	const el = box();

	return el ? el.querySelector( '.inside' ) : null;
}

function busy( on ) {
	const el = inner();

	if ( ! el ) {
		return;
	}

	el.style.opacity = on ? '0.5' : '';
	el.querySelectorAll( 'button' ).forEach( function ( b ) {
		b.disabled = on;
	} );
}

function repaint( html ) {
	const el = inner();

	if ( el ) {
		el.innerHTML = html;
	}
}

function note( message, className ) {
	const el = inner();

	if ( ! el || ! message ) {
		return;
	}

	const p = document.createElement( 'p' );
	p.className = className;
	p.style.padding = '6px 10px';
	p.textContent = message;
	el.prepend( p );
}

function fail( error, fallback ) {
	note( ( error && error.message ) || fallback, 'notice notice-error' );
}

function post( path, postId ) {
	return apiFetch( {
		path: '/kct/v1/turinka/' + path,
		method: 'POST',
		data: { post_id: postId },
	} );
}

function openForm( postId ) {
	busy( true );

	post( 'route-form', postId )
		.then( function ( data ) {
			const url = data && data.form_url;

			if ( ! url || ! window.TurinkaForm ) {
				fail( null, __( 'Formulář Turinky se nepodařilo otevřít.', 'kct' ) );

				return;
			}

			window.TurinkaForm.open( {
				url,
				onSaved: function () {
					post( 'route-link', postId )
						.then( function ( result ) {
							repaint( result.metabox );
						} )
						.catch( function ( e ) {
							fail( e, __( 'Uloženou trasu se nepodařilo dočíst.', 'kct' ) );
						} );
				},
				onCancel: function () {
					busy( false );
				},
			} );
		} )
		.catch( function ( e ) {
			fail( e, __( 'Turinka neodpověděla.', 'kct' ) );
		} )
		.then( function () {
			busy( false );
		} );
}

function removeRoute( postId ) {
	// Potvrzení tady smysl má: tohle je to rozhodnutí, které určuje, jestli
	// trasu lidé v Turince uvidí.
	if ( ! window.confirm( __( 'Odebrat trasu z Turinky? Lidé ji tam přestanou vidět.', 'kct' ) ) ) {
		return;
	}

	busy( true );

	post( 'route-remove', postId )
		.then( function ( data ) {
			repaint( data.metabox );
			note( data.message, 'description' );
		} )
		.catch( function ( e ) {
			fail( e, __( 'Odebrání se nepovedlo.', 'kct' ) );
		} )
		.then( function () {
			busy( false );
		} );
}

// Delegace na dokumentu, ne posluchače na tlačítkách: metabox se po uložení
// formuláře překresluje a navěšené posluchače by zmizely s ním.
document.addEventListener( 'click', function ( event ) {
	const button = event.target.closest && event.target.closest( '[data-kct-turinka-route]' );
	const el = box();

	if ( ! button || ! el || ! el.contains( button ) ) {
		return;
	}

	event.preventDefault();

	const postId = parseInt( button.dataset.post, 10 );

	if ( 'remove' === button.dataset.kctTurinkaRoute ) {
		removeRoute( postId );
	} else {
		openForm( postId );
	}
} );
