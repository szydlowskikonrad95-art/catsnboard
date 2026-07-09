<?php
/*
 * FRONT — co widzi gość. Prosta wersja: ZERO API, ZERO parsowania DOM przy wejściu.
 *
 * Gdy ?lang=pl: lekki bufor robi JEDNĄ operację — strtr() gotowych par ze słownika
 * (segmenty przetłumaczone wcześniej przyciskiem w adminie). strtr = jednoprzebiegowy,
 * próbuje najdłuższe klucze pierwsze, nie podmienia w już-podmienionym (bezpieczniejszy od str_replace).
 *
 * Strona renderuje się NORMALNIE (formularze/nonce ŻYWE — nie serwujemy zamrożonego HTML).
 * Twardy try/catch (\Throwable): jakikolwiek błąd → oryginał EN. Strona klienta NIGDY nie pada od nas.
 *
 * Język trzyma się między stronami, bo pary linków (href → href?lang=pl) są w słowniku
 * i podmieniają się razem z tekstem. Bez rewrite rules = zero konfliktu z WPML klienta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bieżący język: 'pl' gdy ?lang=pl, inaczej 'en'. */
function pnb_auto_pl_jezyk() {
	return ( isset( $_GET['lang'] ) && 'pl' === $_GET['lang'] ) ? 'pl' : 'en'; // phpcs:ignore WordPress.Security.NonceVerification
}

/** Czy podmieniać ten request? Tylko front, tylko PL, nigdy admin/ajax/rest/feed. */
function pnb_pl_podmieniac() {
	if ( 'pl' !== pnb_auto_pl_jezyk() || is_admin() || wp_doing_ajax() ) {
		return false;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return false;
	}
	if ( is_feed() || is_comment_feed() ) {
		return false;
	}
	return true;
}

/*
 * OŻYWIENIE i18n (naprawa 2026-07-09): w trybie ?lang=pl przełącz WordPress locale na pl_PL, żeby
 * załadowały się pliki .po/.mo wtyczek → WSZYSTKIE etykiety __()/_e() interfejsu (Good to know,
 * Highlights, In person, No refunds, Add to Google Calendar...) wychodzą PL ZA DARMO, bez AI.
 * Wcześniej: ?lang=pl przełączał tylko strtr (słownik AI) → etykiety których AI nie złapało zostawały
 * EN mimo że były w .po (bug „ciągle coś nie tłumaczy”). Filtr `locale` woła się przy każdym __(),
 * więc działa na cały render (wcześniej niż bufor strtr na template_redirect). Zero kolizji ze strtr:
 * po .po tekst jest już PL, a pary strtr mają kotwice EN (>tekst<) które nie łapią PL.
 */
add_filter( 'locale', function ( $locale ) {
	return pnb_pl_podmieniac() ? 'pl_PL' : $locale;
} );

/* ============================ CACHE STRONY PL ============================
 * strtr na całym HTML kosztuje ~0.4-1s per żądanie. Zamiast liczyć za KAŻDYM razem, zapamiętujemy
 * gotowy przetłumaczony HTML (transient) i serwujemy go z pamięci → 0.4s spada do ~0.05s.
 *
 * ⚠️ PROBLEM NONCE: strony (np. Events) mają formularze z nonce (token bezp., ważny ~24h, zależny od
 * usera/czasu). Zamrożenie nonce w cache = po czasie „nonce expired” przy wysyłce formularza. ROZWIĄZANIE
 * (wzorzec pluginów cache): przed zapisem WYCINAMY nonce → placeholder z akcją; przy SERWOWANIU z cache
 * wstawiamy ŚWIEŻY nonce per żądanie. Formularze działają, reszta HTML z cache.
 *
 * Cache TYLKO dla: gość (nie zalogowany — admin widzi pasek/edycje), GET, brak query poza lang, komentarze
 * wyłączone. Każdy inny przypadek → normalny bufor (bez cache). Klucz = ścieżka+lang+wersja słownika.
 */
function pnb_pl_cache_wolno() {
	// nie cache'uj: zalogowany (admin-bar, edycje), POST, preview, wyszukiwarka, strony z paginacją komentarzy
	if ( is_user_logged_in() ) { return false; }
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) { return false; }
	if ( is_search() || is_preview() || is_404() ) { return false; }
	// query args: dopuszczamy tylko 'lang' (nasz przełącznik). Cokolwiek innego (utm, ?p=, filtry) → bez cache.
	$dozwolone = array( 'lang' );
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $k, $dozwolone, true ) ) { return false; }
	}
	return true;
}
/** Klucz cache = ścieżka URL + wersja słownika (zmiana tłumaczeń unieważnia cache automatycznie). */
function pnb_pl_cache_klucz() {
	$sciezka = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
	$wersja  = (string) get_option( 'pnb_pl_cache_wersja', '1' );
	return 'pnb_plc_' . md5( $sciezka . '|' . $wersja );
}
/** Wersja cache — bump przy każdym zapisie do słownika (unieważnia stary cache). */
function pnb_pl_cache_bump() {
	update_option( 'pnb_pl_cache_wersja', (string) time(), false );
}
/**
 * Nonce → placeholder (przed zapisem do cache). Łapiemy pnb_nonce (zapis gościa, akcja pnb_zapis_<event_id>).
 * event_id jest w tym samym <form> jako <input name="pnb_event" value="ID">. Placeholder niesie akcję,
 * żeby przy serwowaniu odtworzyć nonce dla właściwej akcji.
 */
function pnb_pl_nonce_na_placeholdery( $html ) {
	// Każdy <form> zapisu ma: name="pnb_event" value="ID" ... name="pnb_nonce" value="TOKEN".
	// Podmieniamy value tokenu na {{PNB_NONCE:pnb_zapis_<ID>}} — ID bierzemy z pnb_event w tym samym formularzu.
	return preg_replace_callback(
		'#<form\b[^>]*class="pnb-ev-form"[^>]*>.*?</form>#is',
		function ( $m ) {
			$form = $m[0];
			if ( ! preg_match( '#name="pnb_event"\s+value="(\d+)"#', $form, $ev ) ) { return $form; }
			$eid = $ev[1];
			// podmień value nonce (pnb_nonce) na placeholder z akcją
			$form = preg_replace(
				'#(name="pnb_nonce"\s+value=")[a-f0-9]+(")#',
				'${1}{{PNB_NONCE:pnb_zapis_' . $eid . '}}${2}',
				$form
			);
			return $form;
		},
		$html
	);
}
/** Placeholdery → świeże nonce (przy serwowaniu z cache). Odwrotność powyższego, token per żądanie. */
function pnb_pl_placeholdery_na_nonce( $html ) {
	return preg_replace_callback(
		'#\{\{PNB_NONCE:([a-z0-9_]+)\}\}#i',
		function ( $m ) { return wp_create_nonce( $m[1] ); },
		$html
	);
}

/* Start bufora na template_redirect (odpala się tylko dla frontowych szablonów). */
add_action( 'template_redirect', function () {
	if ( ! pnb_pl_podmieniac() || headers_sent() ) {
		return;
	}
	// CACHE: jeśli mamy gotowy przetłumaczony HTML dla tej strony → serwuj z pamięci (ze świeżym nonce).
	if ( pnb_pl_cache_wolno() ) {
		$zapisany = get_transient( pnb_pl_cache_klucz() );
		if ( is_string( $zapisany ) && '' !== $zapisany ) {
			echo pnb_pl_placeholdery_na_nonce( $zapisany ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit; // pełny HTML wysłany — kończymy żądanie (0.05s zamiast liczyć strtr)
		}
	}
	ob_start( 'pnb_pl_podmien_bufor' );
}, 1 );

/**
 * Callback bufora: strtr gotowych par. Twardy try/catch — KAŻDY błąd → oryginał (audyt: biały ekran).
 */
function pnb_pl_podmien_bufor( $html ) {
	try {
		$html = (string) $html;
		if ( strlen( $html ) < 200 ) {
			return $html;
		}
		$pary = pnb_pl_pary_do_podmiany();
		if ( empty( $pary ) || ! is_array( $pary ) ) {
			return $html; // słownik pusty → EN (nic nie znika)
		}
		$wynik = strtr( $html, $pary );
		// DRUGI przebieg: linki WEWNĄTRZ przetłumaczonych bloków (np. <a> w <li> menu) — strtr nie
		// skanuje podmienionego tekstu, więc href z tłumaczenia zostawał bez ?lang=pl (klik = powrót EN).
		// ⚡ WYDAJNOŚĆ (2026-07-09): drugi strtr LECIAŁ NA WSZYSTKICH 2328 parach (544ms!) choć potrzebuje
		// tylko ~37 par-LINKÓW (href=). Strona PL ładowała się 1.14s (24× wolniej niż EN 0.05s) — user
		// czuł to jako „lag”. Teraz drugi przebieg tylko na parach z href= → z 544ms na kilka ms.
		$pary_linki = array();
		foreach ( $pary as $k => $v ) {
			if ( false !== strpos( $k, 'href=' ) ) {
				$pary_linki[ $k ] = $v;
			}
		}
		if ( $pary_linki ) {
			$wynik = strtr( $wynik, $pary_linki );
		}
		if ( ! is_string( $wynik ) || '' === $wynik ) {
			return $html;
		}
		// JĘZYK TRZYMA SIĘ NA PODSTRONACH NIEZALEŻNIE OD SŁOWNIKA: każdy wewnętrzny link
		// dostaje ?lang=pl dynamicznie (pary linków w słowniku niosą adresy strony, na której
		// tłumaczono — na innej domenie nie łapią i klik wracał do EN; ta przepustka jest
		// odporna na domenę). Pomijamy: linki z lang=, systemowe /wp-* i pliki z wp-content.
		$dom   = preg_quote( untrailingslashit( home_url() ), '#' );
		$wynik = preg_replace_callback(
			'#href="(' . $dom . '[^"]*)"#',
			function ( $m ) {
				$adres = $m[1];
				if ( false !== strpos( $adres, 'lang=' ) || false !== strpos( $adres, '/wp-' ) ) {
					return $m[0];
				}
				return 'href="' . esc_url( add_query_arg( 'lang', 'pl', $adres ) ) . '"';
			},
			$wynik
		) ?: $wynik;
		// atrybut języka dla SEO/czytników: <html ... lang="en-US"> → pl-PL (tylko w tagu <html>)
		$wynik = preg_replace(
			'#(<html\b[^>]*\blang=")[^"]*(")#i',
			'${1}pl-PL${2}',
			$wynik,
			1
		) ?: $wynik; // preg_replace null (błąd) → zostaw

		// ZAPIS DO CACHE: gotowy przetłumaczony HTML → transient (kolejne wejścia serwowane z pamięci).
		// Nonce → placeholder PRZED zapisem (przy serwowaniu wstawimy świeży). Cache tylko gdy wolno
		// (gość, GET, czysty URL). 6h TTL. Odtworzenie nonce dla TEGO żądania, żeby bieżący gość dostał
		// działający formularz od razu (nie placeholder).
		if ( function_exists( 'pnb_pl_cache_wolno' ) && pnb_pl_cache_wolno() ) {
			$do_cache = pnb_pl_nonce_na_placeholdery( $wynik );
			set_transient( pnb_pl_cache_klucz(), $do_cache, 6 * HOUR_IN_SECONDS );
			// bieżący gość: zwróć wersję ze świeżym nonce (nie placeholdery)
			$wynik = pnb_pl_placeholdery_na_nonce( $do_cache );
		}

		return $wynik;
	} catch ( \Throwable $e ) {
		return $html; // COKOLWIEK pęknie → oryginał EN, strona żyje
	}
}

/* ===== PRZEŁĄCZNIK PL | EN — widżet u góry po prawej (przy ikonkach socjali; decyzja projektowa) =====
 * ⚠️ href w POJEDYNCZYCH cudzysłowach! Pary linków podmieniają href="..." (podwójne) — przez to
 * przycisk EN dostawał ?lang=pl i NIE dało się wrócić na angielski. Pojedyncze = odporny na podmianę. */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	// przełącznik dopiero gdy SĄ tłumaczenia — bez nich to atrapa (instrukcja obiecuje go
	// po „Przetłumacz witrynę"; gość na nieprzetłumaczonej stronie nie ma czego przełączać)
	if ( ! function_exists( 'pnb_pl_pary_do_podmiany' ) || ! pnb_pl_pary_do_podmiany() ) {
		return;
	}
	$jezyk  = pnb_auto_pl_jezyk();
	$url_pl = esc_url( add_query_arg( 'lang', 'pl' ) );
	$url_en = esc_url( remove_query_arg( 'lang' ) );
	?>
	<style>
		/* Kameleon: font dziedziczy z motywu, szkło zamiast białego klocka, akcent przez zmienną
		   (--pnb-accent — motyw/klient może nadpisać; domyślnie koral jak CTA Cats'N'Board). */
		.pnb-auto-pl-switch{position:fixed;top:14px;right:240px;z-index:99999;display:inline-flex;gap:2px;
			background:rgba(255,255,255,.55);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
			border:1px solid rgba(0,0,0,.08);border-radius:100px;padding:3px;
			font-family:inherit;font-size:13px;line-height:1;letter-spacing:.06em;}
		body.admin-bar .pnb-auto-pl-switch{top:46px;}
		/* W PASKU nawigacji (JS wpina do flexa .nav-right / <nav>) — element paska jak socjale:
		   jedzie z paskiem przy scrollu, zero fixed. */
		.pnb-auto-pl-switch.pnb-w-pasku{position:static;transform:none;margin-right:10px;}
		/* fallback: absolute w znalezionym nagłówku */
		.pnb-auto-pl-switch.pnb-w-naglowku{position:absolute;top:50%;right:240px;transform:translateY(-50%);}
		body.admin-bar .pnb-auto-pl-switch.pnb-w-naglowku{top:50%;}
		@media (max-width:782px){.pnb-auto-pl-switch{right:76px;}
			.pnb-auto-pl-switch.pnb-w-naglowku{right:76px;}}
		.pnb-auto-pl-switch a{text-decoration:none;display:inline-block;padding:6px 12px;border-radius:100px;
			color:inherit;opacity:.7;transition:opacity .15s;}
		.pnb-auto-pl-switch a:hover{opacity:1;}
		.pnb-auto-pl-switch a.pnb-aktywny{background:var(--pnb-accent,#ef7461);color:#fff;opacity:1;}
	</style>
	<div class="pnb-auto-pl-switch">
		<a href='<?php echo $url_pl; // phpcs:ignore ?>' class="<?php echo 'pl' === $jezyk ? 'pnb-aktywny' : ''; ?>">PL</a>
		<a href='<?php echo $url_en; // phpcs:ignore ?>' class="<?php echo 'en' === $jezyk ? 'pnb-aktywny' : ''; ?>">EN</a>
	</div>
	<script>
	(function(){
		/* Wpinamy przełącznik W górny pasek → jedzie z nim przy scrollu (nie lata z ekranem).
		   1) prawa strefa paska nav (socjale/burger) → wchodzi do flexa jako zwykły element,
		   2) fallback: pierwszy nav/header na górze strony → absolute w nim,
		   3) nic nie znaleziono → zostaje fixed (bezpiecznie). */
		var w=document.querySelector('.pnb-auto-pl-switch');if(!w)return;
		try{
			var strefa=document.querySelector('nav .nav-right, nav .socials, header .socials');
			if(strefa){
				strefa.insertBefore(w,strefa.firstChild);
				w.classList.add('pnb-w-pasku');
				return;
			}
			var pasek=document.querySelector('body > nav, body > header, .site-header');
			if(pasek){
				if(getComputedStyle(pasek).position==='static'){pasek.style.position='relative';}
				pasek.appendChild(w);
				w.classList.add('pnb-w-naglowku');
			}
		}catch(e){/* zostaje fixed */}
	})();
	</script>
	<?php
} );
