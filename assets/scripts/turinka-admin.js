/**
 * Zakládání a úprava tras a míst z přehledů v administraci.
 *
 * Formulář je stránka Turinky v rámu nad administrací — plugin ho nestaví.
 * Server vydá lístek (nese token, který v prohlížeči být nesmí), hostitelský
 * skript aplikace z něj udělá modál a po uložení se stránka načte znovu, aby
 * tabulka ukázala, co v Turince doopravdy vzniklo.
 *
 * Psáno řetězením `.then()`, ne `async/await`: build převádí async na
 * regenerator, jehož běhové prostředí se do balíčku nepřidává, takže by se
 * skript v prohlížeči zastavil na `regeneratorRuntime is not defined`.
 */

import apiFetch from '@wordpress/api-fetch';

const settings = window.kctTurinkaAdmin || {};

/** Hláška nad tabulkou — stejné místo, kam je píše i WordPress. */
function notice( text, isError ) {
	const wrap = document.querySelector( '.wrap' );

	if ( ! wrap ) {
		return;
	}

	const old = wrap.querySelector( '.kct-turinka-notice' );

	if ( old ) {
		old.remove();
	}

	const box = document.createElement( 'div' );
	box.className = 'notice kct-turinka-notice ' + ( isError ? 'notice-error' : 'notice-info' );
	box.innerHTML = '<p></p>';
	box.querySelector( 'p' ).textContent = text;

	const heading = wrap.querySelector( 'h1' );

	if ( heading && heading.nextSibling ) {
		wrap.insertBefore( box, heading.nextSibling );
	} else {
		wrap.appendChild( box );
	}
}

/** Otevře formulář Turinky; prázdné `sourceId` znamená novou položku. */
function openForm( button, sourceId ) {
	if ( ! window.TurinkaForm || typeof window.TurinkaForm.open !== 'function' ) {
		notice( settings.hostMissing, true );
		return;
	}

	// Tlačítko se zamkne jen po dobu, než přijde lístek. Nechat ho zamčené po
	// otevření formuláře vypadá logicky, jenže modál se dá zavřít i křížkem
	// nebo klávesou Esc, o kterých se skript nedozví — tlačítko by pak zůstalo
	// zamčené až do načtení stránky.
	button.disabled = true;

	const data = { type: settings.type };

	if ( sourceId ) {
		data.source_id = sourceId;
	}

	apiFetch( { path: '/kct/v1/turinka/form', method: 'POST', data } )
		.then( function ( response ) {
			button.disabled = false;

			window.TurinkaForm.open( {
				url: response.form_url,
				onSaved: function () {
					// Uloženou odpověď výpisu zahodí server, ať tabulka po
					// načtení ukazuje čerstvý stav, ne deset minut starý.
					apiFetch( { path: '/kct/v1/turinka/saved', method: 'POST', data: { type: settings.type } } )
						.then( function () {
							window.location.reload();
						} )
						.catch( function () {
							window.location.reload();
						} );
				},
				onCancel: function () {},
			} );
		} )
		.catch( function ( error ) {
			button.disabled = false;
			notice( ( error && error.message ) || settings.failed, true );
		} );
}

/**
 * Odebrání položky z Turinky.
 *
 * Potvrzení je na místě: z pohledu redakce je to „smazání“, i když Turinka
 * položku jen schová. Co se s ní doopravdy stalo, řekne až odpověď serveru —
 * proto se hláška nevymýšlí tady, ale vypíše se ta, kterou pošle.
 */
function removeItem( button ) {
	const title = button.getAttribute( 'data-title' ) || '';

	if ( ! window.confirm( settings.confirmRemove.replace( '%s', title ) ) ) {
		return;
	}

	button.disabled = true;

	apiFetch( {
		path: '/kct/v1/turinka/remove',
		method: 'POST',
		data: { type: settings.type, source_id: button.getAttribute( 'data-source' ) || '' },
	} )
		.then( function () {
			// Hlášku o výsledku drží server do dalšího načtení stránky, takže
			// se dá rovnou načíst tabulka znovu.
			window.location.reload();
		} )
		.catch( function ( error ) {
			button.disabled = false;
			notice( ( error && error.message ) || settings.failed, true );
		} );
}

// Delegovaně na dokumentu: tlačítka jsou v tabulce, která se překresluje.
document.addEventListener( 'click', function ( event ) {
	const remove = event.target.closest( '[data-kct-turinka-remove]' );

	if ( remove ) {
		event.preventDefault();
		removeItem( remove );

		return;
	}

	const button = event.target.closest( '[data-kct-turinka-form]' );

	if ( ! button ) {
		return;
	}

	event.preventDefault();
	openForm( button, button.getAttribute( 'data-source' ) || '' );
} );
