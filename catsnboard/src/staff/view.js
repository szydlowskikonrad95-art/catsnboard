/**
 * Front-end view script for `catsnboard/staff`.
 *
 * IMPORTANT — no double animation.
 * The Cats'N'Board THEME already enqueues assets/js/main.js site-wide, and that
 * script fully animates this markup (split-words in the hero, .member reveal)
 * because render.php emits the same classes/IDs. Running GSAP again here would
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
		// Theme main.js sets window.lenis. Also treat presence of already
		// split words (.wi) as proof the theme's split-words ran.
		return (
			typeof window.lenis !== 'undefined' ||
			document.querySelector( '.cnb-staff .splitw .wi' ) !== null
		);
	}

	function ensureVisible() {
		// Fallback only: the theme CSS keeps [data-rev] visible by default; the
		// only elements the theme JS hides before revealing are the .member
		// cards. If the theme JS never runs, make sure nothing is stuck
		// invisible.
		document
			.querySelectorAll( '.cnb-staff [data-rev], .cnb-staff .member' )
			.forEach( function ( el ) {
				var o = parseFloat( getComputedStyle( el ).opacity );
				if ( o < 0.9 ) {
					el.style.setProperty( 'opacity', '1', 'important' );
					el.style.setProperty( 'transform', 'none', 'important' );
				}
			} );
	}

	function run() {
		if ( ! document.querySelector( '.cnb-staff' ) ) {
			return;
		}
		// Give the theme's main.js a tick to boot (it runs at DOM ready too).
		if ( themeDrivesAnimations() ) {
			return; // theme owns everything — stay silent.
		}
		// Second chance shortly after, in case theme JS boots slightly later.
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
