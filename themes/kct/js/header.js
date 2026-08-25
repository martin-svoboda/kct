/**
 * Hlavička KČT:
 *  1) Průhledné menu: při scrollu přidá body.is-scrolled (podbarvení).
 *  2) Mobilní menu: hamburger otevírá/zavírá off-canvas drawer.
 */
( function () {
	var body = document.body;
	if ( ! body ) {
		return;
	}

	// ── 1) Podbarvení průhledné hlavičky při scrollu ──
	if ( body.classList.contains( 'header-transparent' ) ) {
		var THRESHOLD = 30;
		var onScroll = function () {
			body.classList.toggle( 'is-scrolled', window.scrollY > THRESHOLD );
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll, { passive: true } );
	}

	// ── 2) Mobilní menu ──
	var toggle   = document.querySelector( '.menu-toggle' );
	var backdrop = document.querySelector( '.menu-backdrop' );

	if ( toggle ) {
		var setOpen = function ( open ) {
			body.classList.toggle( 'menu-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		};

		toggle.addEventListener( 'click', function () {
			setOpen( ! body.classList.contains( 'menu-open' ) );
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', function () {
				setOpen( false );
			} );
		}

		// Zavřít po kliknutí na odkaz v menu
		var nav = document.getElementById( 'site-nav' );
		if ( nav ) {
			nav.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( 'a' ) ) {
					setOpen( false );
				}
			} );
		}

		// Zavřít klávesou Escape
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && body.classList.contains( 'menu-open' ) ) {
				setOpen( false );
			}
		} );

		// Při zvětšení na desktop menu zavřít (ať nezůstane drawer stav)
		window.addEventListener( 'resize', function () {
			if ( window.innerWidth > 992 && body.classList.contains( 'menu-open' ) ) {
				setOpen( false );
			}
		}, { passive: true } );
	}
}() );
