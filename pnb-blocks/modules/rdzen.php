<?php
/*
 * RDZEŃ pnb-blocks — minimalny silnik tekstów/języka dla bloków galerii i wydarzeń.
 *
 * Przeniesione z pnb-toolkit (teksty-i18n.php) TYLKO to, czego bloki realnie używają:
 *  - pnb_lang()  — bieżący język (URL ?lang= / cookie / WPML) → 'pl'|'en'
 *  - pnb_txt()   — tekst wg języka: słownik pnb_teksty → EN → domyślny z kodu
 *  - filtr pnb_txt_wynik — bloki (pnb/galeria, pnb/wydarzenia) nadpisują tu swoje teksty hero
 *
 * BEZ panelu „Teksty", BEZ przełącznika PL/EN UI, BEZ przejmowania edytora stron.
 * Słownik pnb_teksty pozostaje jako opcja (bloki nadpisują teksty przez filtr; gdy pusty → domyślne EN).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Język z żądania — URL/cookie/WPML. Bez get_locale() (uniknięcie pętli filtra locale).
 * @return string 'pl'|'en'|'' ('' = brak jawnego wyboru)
 */
function pnb_lang_zadanie() {
	if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
		return ( 0 === strpos( ICL_LANGUAGE_CODE, 'pl' ) ) ? 'pl' : 'en';
	}
	if ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$l = sanitize_key( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $l, array( 'pl', 'en' ), true ) ) {
			return $l;
		}
	}
	if ( isset( $_COOKIE['pnb_lang'] ) ) {
		$l = sanitize_key( wp_unslash( $_COOKIE['pnb_lang'] ) );
		if ( in_array( $l, array( 'pl', 'en' ), true ) ) {
			return $l;
		}
	}
	return '';
}

function pnb_lang() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$z = pnb_lang_zadanie();
	if ( '' !== $z ) {
		return $cache = $z;
	}
	$locale = get_locale();
	return $cache = ( 0 === strpos( (string) $locale, 'pl' ) ) ? 'pl' : 'en';
}

/* Słownik (opcja pnb_teksty) — cichy magazyn tekstów. Bloki nadpisują przez filtr; gdy pusty → domyślne EN. */
function pnb_teksty_slownik() {
	static $slo = null;
	if ( null === $slo ) {
		$slo = get_option( 'pnb_teksty', array() );
		if ( ! is_array( $slo ) ) {
			$slo = array();
		}
	}
	return $slo;
}

/**
 * pnb_txt — tekst wg bieżącego języka. Słownik[lang] → słownik[en] → domyślny z kodu.
 * @param string $klucz    klucz semantyczny (np. 'gallery.hero.title')
 * @param string $domyslny domyślny EN (fallback gdy słownik pusty)
 * @param bool   $echo     true = echo z esc_html, false = zwróć surowo
 */
function pnb_txt( $klucz, $domyslny = '', $echo = false ) {
	$slo  = pnb_teksty_slownik();
	$lang = pnb_lang();
	$wpis = isset( $slo[ $klucz ] ) && is_array( $slo[ $klucz ] ) ? $slo[ $klucz ] : array();

	$tekst = '';
	if ( ! empty( $wpis[ $lang ] ) ) {
		$tekst = $wpis[ $lang ];
	} elseif ( ! empty( $wpis['en'] ) ) {
		$tekst = $wpis['en'];
	} else {
		$tekst = $domyslny;
	}

	// Bloki (pnb/galeria, pnb/wydarzenia) nadpisują teksty hero swojej sekcji na swojej stronie.
	$tekst = apply_filters( 'pnb_txt_wynik', $tekst, $klucz );

	if ( $echo ) {
		echo esc_html( $tekst );
		return '';
	}
	return $tekst;
}

/* Echo z escape (dla szablonów). */
function pnb_e( $klucz, $domyslny = '' ) {
	pnb_txt( $klucz, $domyslny, true );
}

/* Czy nasz przełącznik PL/EN aktywny. Ten plugin (tylko bloki) NIE ma panelu włączania PL —
 * czyta opcję pnb_pl_wlaczony (gdyby została z poprzedniego pluginu), a przy WPML/Polylang oddaje im stery.
 * Kalendarz woła to z function_exists guard, więc i tak jest bezpieczne. */
function pnb_wlasny_przelacznik_aktywny() {
	if ( defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) ) {
		return false;
	}
	return (bool) get_option( 'pnb_pl_wlaczony', 0 );
}

/* „*słowo*" (laika) → <em>słowo</em>. Używane przez render galerii (mid/cta) i mostki tekstów. */
function pnb_zloz_em( $tekst ) {
	return preg_replace( '/\*([^*]+)\*/u', '<em>$1</em>', (string) $tekst );
}
