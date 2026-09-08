/* global wp, jQuery */
/**
 * File customizer.js.
 *
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

( function( $ ) {
	// Site title and description.
	wp.customize( 'blogname', function( value ) {
		value.bind( function( to ) {
			$( '.site-title a' ).text( to );
		} );
	} );
	wp.customize( 'blogdescription', function( value ) {
		value.bind( function( to ) {
			$( '.site-description' ).text( to );
		} );
	} );

	// Skin / vzhled šablony — živý náhled (přepnutí stylesheetu + body class).
	wp.customize( 'kct_skin', function( value ) {
		value.bind( function( to ) {
			if ( [ 'photo', 'magazine', 'cards' ].indexOf( to ) === -1 ) {
				return;
			}
			var link = document.getElementById( 'kct-skin-css' );
			if ( link ) {
				link.href = link.href.replace( /\/(photo|magazine|cards)\.css/, '/' + to + '.css' );
			}
			document.body.className = document.body.className.replace( /\bskin-(photo|magazine|cards)\b/g, 'skin-' + to );
		} );
	} );

	// Header text color.
	wp.customize( 'header_textcolor', function( value ) {
		value.bind( function( to ) {
			if ( 'blank' === to ) {
				$( '.site-title, .site-description' ).css( {
					clip: 'rect(1px, 1px, 1px, 1px)',
					position: 'absolute',
				} );
			} else {
				$( '.site-title, .site-description' ).css( {
					clip: 'auto',
					position: 'relative',
				} );
				$( '.site-title a, .site-description' ).css( {
					color: to,
				} );
			}
		} );
	} );
}( jQuery ) );
