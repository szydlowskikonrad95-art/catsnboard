<?php
/*
 * SEGMENTACJA — tnie surowy HTML na segmenty do tłumaczenia. NAPRAWA GRAMATYKI.
 *
 * Zasada (DeepL/W3C ITS 2.0, potwierdzone researchem 2026-07-05): jednostka tłumaczenia = CAŁA
 * zawartość elementu BLOKOWEGO (p/h1-h6/li/td...) z inline-tagami (<b>,<a>,<span>) W ŚRODKU.
 * NIGDY węzeł-po-węźle — "Our <b>days</b> in frames" idzie JEDNYM stringiem → "Nasze <b>dni</b> w kadrach",
 * nie "Nasz dni w ramek". (Ciekawostka: nawet TranslatePress ma bug segmentacji per-węzeł — robimy LEPIEJ.)
 *
 * Działamy na SUROWYM HTML (regex, nie DOM) — bo front podmienia przez str_replace i szukany string
 * musi być BAJT W BAJT taki jak w renderze. DOM by przekodował encje/whitespace i nic by nie trafiało.
 * Segmentacja odpala się RAZ, przy tłumaczeniu w adminie — nie przy każdym wejściu gościa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Elementy blokowe = granice segmentów. Zawartość każdego z nich to jeden segment. */
function pnb_pl_tagi_blokowe() {
	return array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'figcaption', 'blockquote', 'dt', 'dd', 'summary', 'button', 'label', 'legend', 'caption', 'title' );
}

/** Atrybuty z tekstem widocznym/czytanym — tłumaczymy całą parę atrybut="wartość". */
function pnb_pl_atrybuty_tekstowe() {
	return array( 'alt', 'title', 'placeholder', 'aria-label' );
}

/**
 * Wytnij segmenty do tłumaczenia z surowego HTML strony.
 *
 * @param string $html  surowy HTML (złapany z renderu)
 * @return array  lista segmentów: każdy ['tekst' => surowy string do podmiany, 'typ' => 'blok'|'atrybut'|'meta']
 */
function pnb_pl_wytnij_segmenty( $html ) {
	$html = (string) $html;
	$segmenty = array();

	// 1) BLOKI: zawartość elementów blokowych (non-greedy; zagnieżdżone bloki odfiltruje pnb_pl_segment_ok)
	$tagi = implode( '|', pnb_pl_tagi_blokowe() );
	if ( preg_match_all( '#<(' . $tagi . ')\b[^>]*>(.*?)</\1>#isu', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$wnetrze = $hit[2];
			if ( pnb_pl_segment_ok( $wnetrze ) ) {
				$segmenty[] = array( 'tekst' => $wnetrze, 'typ' => 'blok:' . strtolower( $hit[1] ) );
			}
		}
	}

	// 1b) DIV/SPAN-LIŚCIE: tekst siedzący bezpośrednio w elemencie bez ŻADNYCH tagów w środku
	//     (np. eyebrow, ceny w <small>/ day</small> — audyt 2026-07-05: small/q/cite umykały)
	if ( preg_match_all( '#<(div|span|a|figure|em|strong|b|i|small|q|cite|sup|sub)\b[^>]*>([^<>]{2,300})</\1>#iu', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			if ( pnb_pl_tekst_wart_tlumaczenia( $hit[2] ) ) {
				$segmenty[] = array( 'tekst' => $hit[2], 'typ' => 'lisc:' . strtolower( $hit[1] ) );
			}
		}
	}

	// 2) ATRYBUTY: alt="...", title="...", placeholder="...", aria-label="..."
	$attrs = implode( '|', pnb_pl_atrybuty_tekstowe() );
	if ( preg_match_all( '#\b(' . $attrs . ')="([^"]{2,})"#iu', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			if ( pnb_pl_tekst_wart_tlumaczenia( $hit[2] ) ) {
				// segmentem jest SAMA wartość — podmiana attr="wartość" po stronie par (patrz pary linków)
				$segmenty[] = array( 'tekst' => $hit[2], 'typ' => 'atrybut:' . strtolower( $hit[1] ), 'caly' => $hit[0] );
			}
		}
	}

	// 3) META description / og:title / og:description (SEO w PL)
	if ( preg_match_all( '#<meta\s[^>]*(?:name|property)="(description|og:title|og:description|twitter:title|twitter:description)"[^>]*content="([^"]{2,})"[^>]*>#iu', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			if ( pnb_pl_tekst_wart_tlumaczenia( $hit[2] ) ) {
				$segmenty[] = array( 'tekst' => $hit[2], 'typ' => 'meta:' . strtolower( $hit[1] ) );
			}
		}
	}

	// dedup po treści (to samo zdanie w wielu miejscach = 1 segment; wzorzec TP array_unique)
	$widziane = array();
	$unikalne = array();
	foreach ( $segmenty as $s ) {
		$klucz = md5( $s['tekst'] );
		if ( ! isset( $widziane[ $klucz ] ) ) {
			$widziane[ $klucz ] = true;
			$unikalne[] = $s;
		}
	}
	return $unikalne;
}

/**
 * Czy zawartość bloku nadaje się na segment?
 * NIE gdy: zawiera zagnieżdżony tag blokowy (te złapią się osobno), skrypt/styl/formularz z nonce,
 * shortcode, brak liter, sama liczba/symbol, za długie (bezpiecznik API).
 */
function pnb_pl_segment_ok( $wnetrze ) {
	$wnetrze = (string) $wnetrze;
	if ( '' === trim( $wnetrze ) || mb_strlen( $wnetrze ) > 4000 ) {
		return false;
	}
	// zagnieżdżony blok w środku → to nie liść, pomiń (liście złapane osobnym trafieniem)
	$tagi = implode( '|', array_diff( pnb_pl_tagi_blokowe(), array( 'title' ) ) );
	if ( preg_match( '#<(?:' . $tagi . '|div|ul|ol|table|section|article|form|nav|header|footer|aside)\b#i', $wnetrze ) ) {
		return false;
	}
	// niebezpieczne/dynamiczne w środku → pomiń (nonce zamrożony = zepsuty formularz)
	if ( preg_match( '#<(?:script|style|input|select|textarea|iframe|svg)\b|_wpnonce|\[[a-z_]+[^\]]*\]#i', $wnetrze ) ) {
		return false;
	}
	return pnb_pl_tekst_wart_tlumaczenia( wp_strip_all_tags( $wnetrze ) );
}

/** Czy goły tekst wart tłumaczenia: ma litery, nie jest URL/mailem/samą liczbą. */
function pnb_pl_tekst_wart_tlumaczenia( $tekst ) {
	$t = trim( (string) $tekst );
	if ( mb_strlen( $t ) < 2 || ! preg_match( '/\p{L}{2,}/u', $t ) ) {
		return false;
	}
	if ( preg_match( '~^(https?://|mailto:|www\.)~i', $t ) || ( false !== strpos( $t, '@' ) && ! preg_match( '/\s/', $t ) ) ) {
		return false;
	}
	if ( preg_match( '/^[\d\s\+\-\.,:%()€$]+$/u', $t ) ) {
		return false;
	}
	return true;
}

/**
 * PARY LINKÓW: wewnętrzne linki treściowe dostają ?lang=pl (podmiana par na froncie).
 * Tylko BIAŁA lista treściowa — nigdy wp-admin/wp-login/wp-json/feed/pliki/#/mailto (audyt: zepsuty logout).
 *
 * @param string $html surowy HTML
 * @return array  'href="URL"' => 'href="URL?lang=pl"'
 */
function pnb_pl_pary_linkow( $html ) {
	$pary = array();
	$dom_strony = home_url( '/' );
	if ( ! preg_match_all( '#href="([^"]+)"#i', (string) $html, $m ) ) {
		return $pary;
	}
	foreach ( array_unique( $m[1] ) as $url ) {
		// tylko nasze wewnętrzne, treściowe
		$wzgledny = ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) );
		$naszePelne = ( 0 === strpos( $url, $dom_strony ) );
		if ( ! $wzgledny && ! $naszePelne ) {
			continue;
		}
		if ( preg_match( '#wp-admin|wp-login|wp-json|admin-ajax|xmlrpc|wp-content|wp-includes|/feed|\.(xml|jpg|jpeg|png|webp|svg|gif|pdf|zip|css|js)(\?|$)|\#|mailto:|tel:|lang=#i', $url ) ) {
			continue;
		}
		$nowy = $url . ( false === strpos( $url, '?' ) ? '?lang=pl' : '&lang=pl' );
		$pary[ 'href="' . $url . '"' ] = 'href="' . $nowy . '"';
	}
	return $pary;
}
