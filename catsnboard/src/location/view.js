/**
 * Front-end view script for `catsnboard/location`.
 *
 * IMPORTANT — no double animation.
 * The Cats'N'Board THEME already enqueues assets/js/main.js site-wide, and that
 * script animates this markup (split-words on .splitw, [data-rev] reveals)
 * because render.php emits the same classes. Running GSAP again here would
 * double-wrap words and double-init ScrollTrigger. So this view script is a
 * GUARD: it only acts as a fallback when the theme JS is NOT present (e.g. the
 * block used in a different theme).
 *
 * Detection: the theme's main.js creates a Lenis instance and exposes
 * `window.lenis`. If that exists, the theme owns the animations and we do
 * nothing. Otherwise we apply a minimal, safe fallback so content is at least
 * visible (no hidden opacity:0 elements) — without requiring GSAP.
 */
( function () {
	'use strict';

	function themeDrivesAnimations() {
		return (
			typeof window.lenis !== 'undefined' ||
			document.querySelector( '.cnb-location .splitw .wi' ) !== null
		);
	}

	function ensureVisible() {
		document
			.querySelectorAll( '.cnb-location [data-rev]' )
			.forEach( function ( el ) {
				var o = parseFloat( getComputedStyle( el ).opacity );
				if ( o < 0.9 ) {
					el.style.setProperty( 'opacity', '1', 'important' );
					el.style.setProperty( 'transform', 'none', 'important' );
				}
			} );
	}

	function run() {
		if ( ! document.querySelector( '.cnb-location' ) ) {
			return;
		}
		if ( themeDrivesAnimations() ) {
			return; // theme owns everything — stay silent.
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
