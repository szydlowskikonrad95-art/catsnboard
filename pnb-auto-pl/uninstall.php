<?php
/*
 * SPRZĄTANIE przy odinstalowaniu (RODO + higiena). Odpala się TYLKO gdy właściciel usuwa wtyczkę
 * w panelu (WP wywołuje ten plik sam).
 *
 * CO KASUJEMY (stan na v2.4.1 — lista musi być KOMPLETNA, patrz bramka niżej):
 *  - tabelę słownika (+ stare tabele prototypu v0.1),
 *  - WSZYSTKIE opcje wtyczki — w tym KLUCZE API OBU silników (Claude i Gemini): sekrety klienta
 *    nie mogą zostać w bazie po usunięciu narzędzia,
 *  - transienty wtyczki WYMIENIONE Z NAZWY (pnb_pl_pary + pnb_plc_*) — kasujemy wyłącznie
 *    własne, nie cały prefiks pnb_ (zasada własności; recenzja 2026-07-16).
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
 *
 * 🛡️ NIE MUSISZ JUŻ O TYM PAMIĘTAĆ — pilnuje tego AUTOMAT: `testy/straznik-sprzatania.sh`
 * (job „strażnik sprzątania" w CI, leci przy każdym PR). Zapiszesz opcję i nie dopiszesz jej
 * tutaj → CI nie przepuści zmiany i wypisze jej nazwę.
 * Wcześniej stała tu instrukcja „uruchom ten grep przed wydaniem" — czyli ludzka pamięć.
 * Dokładnie ona zawiodła przy Gemini, dlatego bramka jest teraz maszyną, a nie notatką.
 * Sprawdzić strażnika ręcznie: `bash testy/straznik-sprzatania.sh`
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
 * TRANSIENTY — kasujemy TYLKO WŁASNE, wymienione z nazwy (recenzja 2026-07-16, poprawka #2):
 *   „pnb_pl_pary"     — cache par słownika (slownik.php)
 *   „pnb_plc_<hash>"  — cache gotowych stron PL (front.php, pnb_pl_cache_klucz())
 * To KOMPLETNA lista transientów tej wtyczki — tylko te dwa miejsca wołają set_transient().
 *
 * Historia dojścia (dwie poprawki recenzenta):
 * 15.07: było `LIKE '_transient_pnb_%'` BEZ escape — `_` to wildcard SQL, wzorzec łapał cudze.
 *        Naprawa: esc_like() + prepare (wzorzec z rdzenia WP, delete_expired_transients).
 * 16.07: recenzent — „cleanup transientów powinno zawęzić do pnb_pl_pary i pnb_plc_*, wtedy
 *        będzie elegancko spójne". Racja: escapowany prefiks `pnb_` wciąż obejmował CAŁĄ
 *        rodzinę pnb (np. przyszłe transienty siostrzanej pnb-blocks). Zasada własności:
 *        każda wtyczka sprząta wyłącznie to, co sama tworzy.
 * ⚠️ Dodajesz w kodzie nowy set_transient() → dopisz jego nazwę TUTAJ (jak z opcjami wyżej).
 */
$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	"DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s) OR option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
	'_transient_pnb_pl_pary',
	'_transient_timeout_pnb_pl_pary',
	$wpdb->esc_like( '_transient_pnb_plc_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_pnb_plc_' ) . '%'
) );
