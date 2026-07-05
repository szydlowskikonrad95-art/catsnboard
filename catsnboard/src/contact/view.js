/**
 * Front-end view script for `catsnboard/contact`.
 *
 * IMPORTANT — no double animation.
 * The Cats'N'Board THEME already enqueues assets/js/main.js site-wide, and that
 * script animates this markup (split-words on .splitw, [data-rev] reveals)
 * because render.php emits the same classes. So this view script is only a
 * GUARD/fallback: if the theme JS is NOT present (block used in another theme),
 * it makes sure nothing is stuck invisible.
 *
 * Detection: the theme's main.js creates a Lenis instance (window.lenis) and
 * splits words into `.wi`. If either is present, the theme owns animations and
 * we do nothing.
 */
( function () {
	'use strict';

	function themeDrivesAnimations() {
		return (
			typeof window.lenis !== 'undefined' ||
			document.querySelector( '.cnb-contact .splitw .wi' ) !== null
		);
	}

	function ensureVisible() {
		document
			.querySelectorAll( '.cnb-contact [data-rev]' )
			.forEach( function ( el ) {
				var o = parseFloat( getComputedStyle( el ).opacity );
				if ( o < 0.9 ) {
					el.style.setProperty( 'opacity', '1', 'important' );
					el.style.setProperty( 'transform', 'none', 'important' );
				}
			} );
	}

	function run() {
		if ( ! document.querySelector( '.cnb-contact' ) ) {
			return;
		}
		if ( themeDrivesAnimations() ) {
			return;
		}
		setTimeout( function () {
			if ( themeDrivesAnimations() ) {
				return;
			}
			ensureVisible();
		}, 400 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
