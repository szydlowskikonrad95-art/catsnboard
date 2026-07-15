<?php
/*
 * SPRZĄTANIE przy odinstalowaniu (RODO + higiena): tabela słownika, opcje, klucz API, transienty.
 * Odpala się TYLKO gdy właściciel usuwa wtyczkę w panelu (WP wywołuje ten plik sam).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// tabela słownika (v0.2) + stare tabele prototypu v0.1 (gdyby ktoś aktualizował z niego)
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pnb_slownik_en_pl" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pnb_auto_pl" );       // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pnb_auto_pl_cache" ); // phpcs:ignore WordPress.DB

/*
 * OPCJE — w tym KLUCZE API obu silników (sekrety klienta NIE zostają w bazie).
 *
 * ⚠️ ŻELAZNA ZASADA (lekcja z recenzji 2026-07-15): dodajesz opcję gdziekolwiek w kodzie →
 * DOPISZ JĄ TUTAJ w tym samym PR. Recenzent wyłapał, że silnik Gemini (v2.4.0) dodał 6 opcji,
 * a uninstall nie znał ŻADNEJ — klucz API klienta zostawał w bazie po usunięciu wtyczki.
 * Sprawdzenie kompletności listy (uruchom przed wydaniem):
 *   grep -ohE "(get|update)_option\( *'(pnb[^']+)'" inc/*.php *.php | grep -oE "'pnb[^']*'" | sort -u
 * — każda opcja z tej listy MUSI być poniżej.
 */
foreach ( array(
	// --- silnik Claude ---
	'pnb_auto_pl_klucz',              // KLUCZ API Anthropic (sekret!)
	'pnb_auto_pl_model',
	'pnb_auto_pl_limit_znakow',
	'pnb_pl_licznik',                 // licznik znaków/dzień
	// --- silnik Gemini (v2.4.0) ---
	'pnb_auto_pl_gemini_klucz',       // KLUCZ API Google (sekret!)
	'pnb_auto_pl_gemini_model',
	'pnb_auto_pl_gemini_limit_rpd',
	'pnb_pl_gemini_licznik',          // licznik zapytań/dobę
	'pnb_pl_gemini_ostatnie',         // znacznik throttle RPM
	// --- wspólne ---
	'pnb_auto_pl_silnik',             // wybór silnika (claude/gemini)
	'pnb_pl_nieaktualne',
	'pnb_pl_cache_wersja',            // licznik wersji cache stron PL (front.php)
	'pnb_pl_cache_kod_wersja',        // wersja wtyczki przy ostatnim czyszczeniu cache (front.php)
) as $opcja ) {
	delete_option( $opcja );
}

/*
 * TRANSIENTY (cache par „pnb_pl_pary" + cache stron PL „pnb_plc_<hash>").
 *
 * ⚠️ POPRAWKA 2026-07-15 (recenzja kolegi): było `LIKE '_transient_pnb_%'` — BEZ escape.
 * W SQL podkreślnik `_` w LIKE to WILDCARD („dowolny pojedynczy znak"), nie dosłowny znak.
 * Wzorzec łapał więc szerzej niż nasze transienty i mógł skasować CUDZE dane (innej wtyczki).
 *
 * WZORZEC Z RDZENIA WORDPRESSA (wp-includes/option.php:1645-1655, funkcja delete_expired_transients):
 * `$wpdb->prepare(... LIKE %s ..., $wpdb->esc_like( '_transient_' ) . '%')`
 * — esc_like() = addcslashes($text,'_%\\') escapuje `_` i `%`, znak `%` doklejamy PO escapowaniu.
 * Kopiujemy dokładnie tę metodę — to sposób twórców WP, nie nasz wymysł.
 */
$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
	$wpdb->esc_like( '_transient_pnb_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_pnb_' ) . '%'
) );
