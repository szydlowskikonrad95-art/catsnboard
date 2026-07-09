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

/* Start bufora na template_redirect (odpala się tylko dla frontowych szablonów). */
add_action( 'template_redirect', function () {
	if ( ! pnb_pl_podmieniac() || headers_sent() ) {
		return;
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
		// Pary są zakotwiczone (href="..." / >tekst<), więc drugi przebieg jest bezpieczny (klucz EN
		// nie występuje już w polskim wyniku, a href="X" nie łapie href="X?lang=pl").
		$wynik = strtr( $wynik, $pary );
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
