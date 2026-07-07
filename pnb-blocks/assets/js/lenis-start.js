/* Płynny scroll (Lenis) DLA PODSTRON WTYCZKI — galeria i wydarzenia.
 * Powód: efekty scroll (taśma, głębia, oś czasu) wyglądają płynnie tylko z płynnym scrollem.
 * Nasz motyw ma własny zegar Lenisa; obce motywy klienta (BeTheme itd.) zwykle nie — więc wtyczka
 * dowozi płynność SAMA, ale TYLKO gdy nikt inny jej nie uruchomił (zero dwóch zegarów = zero szarpania).
 *
 * Bezpieczniki:
 *  - jeśli na stronie już żyje Lenis (motyw/inna wtyczka) → nie startujemy drugiego,
 *  - szanuje „prefers-reduced-motion" (dostępność),
 *  - podpina się pod ticker GSAP jeśli jest (jeden zegar animacji), inaczej własny requestAnimationFrame,
 *  - odświeża ScrollTrigger po każdym kroku (żeby efekty liczyły pozycję z płynnego scrolla). */
( function () {
	if ( typeof window.Lenis !== 'function' ) { return; }              // biblioteka nie doszła
	if ( window.__pnbLenis || window.lenis || window.__lenis ) { return; } // ktoś już ją odpalił
	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) { return; }

	var lenis = new window.Lenis( { lerp: 0.09, wheelMultiplier: 1.05 } );
	window.__pnbLenis = lenis;

	var g = window.gsap;
	var st = g && g.plugins ? ( g.plugins.scrollTrigger || window.ScrollTrigger ) : window.ScrollTrigger;

	if ( st && typeof st.update === 'function' ) {
		lenis.on( 'scroll', st.update );
	}

	if ( g && g.ticker && typeof g.ticker.add === 'function' ) {
		// JEDEN zegar: Lenis jedzie na tickerze GSAP (spójne z resztą animacji)
		g.ticker.add( function ( time ) { lenis.raf( time * 1000 ); } );
		if ( typeof g.ticker.lagSmoothing === 'function' ) { g.ticker.lagSmoothing( 0 ); }
	} else {
		// brak GSAP-tickera → własna pętla
		var raf = function ( t ) { lenis.raf( t ); requestAnimationFrame( raf ); };
		requestAnimationFrame( raf );
	}
} )();
