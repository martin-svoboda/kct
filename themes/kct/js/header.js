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

	// ── 1) Stav po odscrollování (body.is-scrolled): zmenšení loga + podbarvení
	//        průhledné hlavičky. Běží na všech stránkách.
	//
	// HYSTEREZE (dva prahy): logo se po odscrollování zmenší o ~40 px, což mění
	// výšku hlavičky. Prohlížeč pak přes „scroll anchoring" posune scrollY zpět,
	// a s jedním prahem to u vrcholu překlápělo tam a zpět (hlavička se „třásla").
	// Přidáme třídu až nad ADD_AT a sundáme ji až pod REMOVE_AT — mezera je větší
	// než ten skok, takže oscilace nevznikne.
	var ADD_AT    = 90;
	var REMOVE_AT = 10;
	var scrolled  = false;
	var scrollY   = function () { return window.scrollY || window.pageYOffset || 0; };
	var onScroll  = function () {
		var y = scrollY();
		if ( ! scrolled && y > ADD_AT ) {
			scrolled = true;
			body.classList.add( 'is-scrolled' );
		} else if ( scrolled && y < REMOVE_AT ) {
			scrolled = false;
			body.classList.remove( 'is-scrolled' );
		}
	};
	// Init dle aktuální pozice (např. po reloadu uprostřed stránky) — bez oscilace.
	scrolled = scrollY() > ADD_AT;
	body.classList.toggle( 'is-scrolled', scrolled );
	window.addEventListener( 'scroll', onScroll, { passive: true } );
	window.addEventListener( 'resize', onScroll, { passive: true } );

	// ── Kopírovat odkaz (sdílení) ──
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.js-copy-link' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var url  = btn.getAttribute( 'data-url' ) || window.location.href;
		var done = function () {
			btn.classList.add( 'is-copied' );
			window.setTimeout( function () { btn.classList.remove( 'is-copied' ); }, 1600 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( done ).catch( function () {} );
		} else {
			var ta = document.createElement( 'textarea' );
			ta.value = url;
			ta.setAttribute( 'readonly', '' );
			ta.style.position = 'absolute';
			ta.style.left = '-9999px';
			document.body.appendChild( ta );
			ta.select();
			try { document.execCommand( 'copy' ); done(); } catch ( err ) {}
			document.body.removeChild( ta );
		}
	} );

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
