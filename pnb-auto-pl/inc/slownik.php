<?php
/*
 * SŁOWNIK TŁUMACZEŃ — serce prostej wersji (schema z ŻYWEGO kodu TranslatePress, ~5 mln instalacji).
 *
 * Wzorzec [KOD TP class-query.php]: tabela per para języków, kolumny original/translated/status,
 * lookup batchem WHERE original IN (...), zapis INSERT ... ON DUPLICATE KEY UPDATE.
 * Nasza zmiana vs TP: klucz = hash znormalizowanego segmentu (szybki, unikalny indeks zamiast prefiksu 100 zn.).
 *
 * PRZEPŁYW: tłumaczymy RAZ (przycisk w adminie) → pary EN→PL lądują tu → front robi tylko str_replace
 * gotowych par (zero API, zero DOM przy gościu). Decyzja po 3 niezależnych audytach 2026-07-05.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Nazwa tabeli słownika EN→PL. */
function pnb_pl_tabela_slownika() {
	global $wpdb;
	return $wpdb->prefix . 'pnb_slownik_en_pl';
}

/** Statusy wpisu (jak TP): 0=do tłumaczenia, 1=maszyna (Claude), 2=człowiek poprawił. */
const PNB_PL_STATUS_BRAK    = 0;
const PNB_PL_STATUS_MASZYNA = 1;
const PNB_PL_STATUS_CZLOWIEK = 2;

/** Tworzy tabelę słownika (aktywacja). */
function pnb_pl_utworz_slownik() {
	global $wpdb;
	$tabela  = pnb_pl_tabela_slownika();
	$collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $tabela (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		hash CHAR(32) NOT NULL,
		original LONGTEXT NOT NULL,
		translated LONGTEXT NULL,
		status TINYINT NOT NULL DEFAULT 0,
		block_type VARCHAR(20) NOT NULL DEFAULT '',
		zmieniono DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY hash (hash)
	) $collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Normalizacja segmentu przed hashem (wzorzec TP trp_full_trim + research W3C):
 * trim + zbij białe znaki do 1 spacji. Inline-tagi ZOSTAJĄ (są częścią tożsamości segmentu).
 */
function pnb_pl_normalizuj( $tekst ) {
	return trim( preg_replace( '/\s+/u', ' ', (string) $tekst ) );
}

/** Hash znormalizowanego segmentu — klucz słownika. */
function pnb_pl_hash( $tekst ) {
	return md5( pnb_pl_normalizuj( $tekst ) );
}

/**
 * BATCH lookup (wzorzec TP get_existing_translations): dla listy segmentów zwróć gotowe PL.
 *
 * @param string[] $segmenty  oryginały EN
 * @return array   hash => translated  (tylko te które SĄ przetłumaczone, status != 0)
 */
function pnb_pl_pobierz_wiele( $segmenty ) {
	global $wpdb;
	if ( empty( $segmenty ) ) {
		return array();
	}
	$hashe = array_map( 'pnb_pl_hash', $segmenty );
	$hashe = array_values( array_unique( $hashe ) );
	$tabela = pnb_pl_tabela_slownika();
	$ph = implode( ',', array_fill( 0, count( $hashe ), '%s' ) );
	$wiersze = $wpdb->get_results( $wpdb->prepare(
		"SELECT hash, translated FROM $tabela WHERE status != 0 AND hash IN ($ph)", // phpcs:ignore WordPress.DB.PreparedSQL
		$hashe
	) );
	$mapa = array();
	foreach ( (array) $wiersze as $w ) {
		$mapa[ $w->hash ] = (string) $w->translated;
	}
	return $mapa;
}

/**
 * Zapisz tłumaczenie segmentu (INSERT ... ON DUPLICATE KEY UPDATE — wzorzec TP).
 * Nie nadpisuje poprawek człowieka (status 2) tłumaczeniem maszyny.
 * ⚠️ original zapisujemy SUROWO (bajt w bajt z HTML) — front podmienia z kotwicami >tekst<,
 *    więc szukany string musi być identyczny jak w renderze. Hash liczony z formy znormalizowanej (dedup).
 */
function pnb_pl_zapisz_segment( $original, $translated, $block_type = '' ) {
	global $wpdb;
	$tabela = pnb_pl_tabela_slownika();
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $tabela (hash, original, translated, status, block_type, zmieniono)
		 VALUES (%s, %s, %s, %d, %s, %s)
		 ON DUPLICATE KEY UPDATE
		   translated = IF(status = 2, translated, VALUES(translated)),
		   status     = IF(status = 2, status, VALUES(status)),
		   zmieniono  = VALUES(zmieniono)", // phpcs:ignore WordPress.DB.PreparedSQL
		pnb_pl_hash( $original ),
		(string) $original,
		(string) $translated,
		PNB_PL_STATUS_MASZYNA,
		(string) $block_type,
		current_time( 'mysql' )
	) );
	pnb_pl_wyczysc_cache_par();
}

/**
 * PARY DO PODMIANY na froncie — z KOTWICAMI KONTEKSTU (lekcja z katastrofy „in"→„w" 2026-07-05:
 * goła para 2-literowa podmieniła KAŻDE „in" w dokumencie — <link>→<lwk, initial→witial — i zabiła CSS).
 * NIGDY gołego tekstu:
 *   blok/lisc  → '>tekst<'         => '>tłumaczenie<'   (tylko pełna zawartość elementu)
 *   atrybut:X  → 'X="tekst"'       => 'X="tłumaczenie"'
 *   meta:X     → 'content="tekst"' => 'content="tłumaczenie"'
 *   link       → już pełne 'href="..."' (zapis dosłowny)
 * Posortowane od najdłuższych (strtr i tak preferuje najdłuższe dopasowanie).
 */
function pnb_pl_pary_do_podmiany() {
	$pary = get_transient( 'pnb_pl_pary' );
	if ( false !== $pary && is_array( $pary ) ) {
		return $pary;
	}
	global $wpdb;
	$tabela = pnb_pl_tabela_slownika();
	$wiersze = $wpdb->get_results(
		"SELECT original, translated, block_type FROM $tabela WHERE status != 0 AND translated IS NOT NULL AND translated != ''" // phpcs:ignore WordPress.DB.PreparedSQL
	);
	$pary = array();
	foreach ( (array) $wiersze as $w ) {
		if ( $w->original === $w->translated ) {
			continue;
		}
		$typ = (string) $w->block_type;
		if ( 'link' === $typ ) {
			// zapis dosłowny — już pełny 'href="..."'
			$pary[ $w->original ] = $w->translated;
		} elseif ( false === strpos( $w->original, '<' ) && false === strpos( $w->original, '"' )
			&& false === strpos( $w->translated, '"' ) ) {
			// CZYSTY TEKST (bez tagów/cudzysłowów) → pary we WSZYSTKICH formach kotwic.
			// (Audyt: „Your name" złapane raz jako placeholder → forma >label< nie istniała → label został EN.
			//  Dedup po hashu daje 1 wiersz na tekst, więc formy muszą wychodzić z jednego wiersza.)
			$pary[ '>' . $w->original . '<' ] = '>' . $w->translated . '<';
			foreach ( array( 'alt', 'title', 'placeholder', 'aria-label', 'content', 'value' ) as $attr ) {
				$pary[ $attr . '="' . $w->original . '"' ] = $attr . '="' . $w->translated . '"';
			}
		} elseif ( 0 === strpos( $typ, 'atrybut:' ) ) {
			$attr = substr( $typ, 8 );
			if ( preg_match( '/^[a-z-]+$/', $attr ) ) {
				$pary[ $attr . '="' . $w->original . '"' ] = $attr . '="' . $w->translated . '"';
			}
		} elseif ( 0 === strpos( $typ, 'meta:' ) ) {
			$pary[ 'content="' . $w->original . '"' ] = 'content="' . $w->translated . '"';
		} else {
			// blok z tagami w środku — tylko kotwica pełnej zawartości elementu
			$pary[ '>' . $w->original . '<' ] = '>' . $w->translated . '<';
		}
	}
	// WARIANTY MYŚLNIKA: WordPress przez wptexturize zamienia w renderze zwykły „ - ” na en-dash „–”
	// (&#8211;) i „--” na em-dash „—”. Słownik trzyma ORYGINAŁ (z „-”), więc strtr nie łapał opisów
	// wydarzeń zawierających myślnik → zostawały EN (bug 2026-07-09: tytuły PL, ale opisy z Eventbrite EN).
	// Dla KAŻDEJ pary której klucz zawiera „ - ” dokładamy bliźniaczą parę z en-dashem — strtr złapie
	// obie formy. Wąsko (tylko myślnik w spacjach), nie ruszamy reszty (lekcja: kotwice, nie gołe stringi).
	$warianty = array();
	foreach ( $pary as $klucz => $wartosc ) {
		if ( false !== strpos( $klucz, ' - ' ) ) {
			$warianty[ str_replace( ' - ', ' – ', $klucz ) ] = str_replace( ' - ', ' – ', $wartosc ); // en-dash
		}
	}
	if ( $warianty ) {
		$pary = $pary + $warianty; // + zachowuje istniejące klucze, dokłada nowe
	}
	uksort( $pary, function ( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	} );
	set_transient( 'pnb_pl_pary', $pary, 12 * HOUR_IN_SECONDS );
	return $pary;
}

/** Czyści cache par (po zapisie do słownika). */
function pnb_pl_wyczysc_cache_par() {
	delete_transient( 'pnb_pl_pary' );
}

/** Ile wpisów w słowniku (status + licznik do panelu admina). */
function pnb_pl_statystyki_slownika() {
	global $wpdb;
	$tabela = pnb_pl_tabela_slownika();
	return array(
		'razem'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabela" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'gotowe'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabela WHERE status != 0" ), // phpcs:ignore WordPress.DB.PreparedSQL
		'czlowiek' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabela WHERE status = 2" ), // phpcs:ignore WordPress.DB.PreparedSQL
	);
}
