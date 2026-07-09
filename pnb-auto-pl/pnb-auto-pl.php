<?php
/*
 * Plugin Name:       PNB Polish Version (AI)
 * Description:       Polish version of the site with a PL/EN switcher: the "Translate site" button translates everything with Claude AI, and every page save auto-translates the changes. Visitors get ready-made Polish (zero AI calls per visit).
 * Version:           0.3.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            dzidek
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pnb-auto-pl
 * Domain Path:       /languages
 *
 * ARCHITEKTURA v0.2 (prosta wersja — po 3 niezależnych audytach + wzorce z żywego kodu TranslatePress):
 * „Tłumacz RAZ przyciskiem → zapisz do słownika → front tylko podmienia gotowe pary (strtr)".
 * - admin.php: przycisk z paskiem postępu; JS pobiera strony jak gość (fetch bez cookies — zero loopbacku)
 * - tlumaczenie.php: AJAX tnie HTML na segmenty BLOKOWE (gramatyka!) → batch Claude → słownik
 * - slownik.php: tabela original/translated/status (schema TranslatePress) + cache par
 * - front.php: ?lang=pl → strtr gotowych par w buforze z twardym try/catch; linki niosą lang → język trzyma się
 * - silnik-claude.php: batch JSON + walidacja tagów + wp_kses + dzienny limit znaków (bezpiecznik kosztu)
 * ŚWIADOMIE NIE MA: crona, loopbacku, DOMDocument na request, rewrite rules /pl/ (konflikt z WPML klienta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PNB_AUTO_PL_VERSION', '0.3.6' );
define( 'PNB_AUTO_PL_DIR', plugin_dir_path( __FILE__ ) );

require_once PNB_AUTO_PL_DIR . 'inc/slownik.php';      // tabela słownika + pary do podmiany (cache)
require_once PNB_AUTO_PL_DIR . 'inc/segmentacja.php';  // tnie HTML na segmenty blokowe + pary linków
require_once PNB_AUTO_PL_DIR . 'inc/silnik-claude.php'; // Claude API: batch + walidacja + limit kosztu
require_once PNB_AUTO_PL_DIR . 'inc/tlumaczenie.php';  // AJAX „przetłumacz stronę" + wykrywanie zmian
require_once PNB_AUTO_PL_DIR . 'inc/front.php';        // podmiana par (strtr) + przełącznik PL|EN

if ( is_admin() ) {
	require_once PNB_AUTO_PL_DIR . 'inc/admin.php';    // ustawienia + przycisk „Przetłumacz witrynę"
}

/* Tłumaczenia interfejsu wtyczki (EN domyślnie, PL przez .mo — standard WP). */
add_action( 'init', function () {
	load_plugin_textdomain( 'pnb-auto-pl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

register_activation_hook( __FILE__, function () {
	pnb_pl_utworz_slownik();
	pnb_pl_zasiej_slownik_startowy();
} );

/* SŁOWNIK STARTOWY: gotowe tłumaczenia domyślnych tekstów strony jadą w paczce
   (dane/slownik-startowy.json) — polska wersja i przełącznik PL|EN działają od
   pierwszej minuty, BEZ klucza API. Klucz potrzebny dopiero do NOWYCH tekstów
   klienta. Sieje wyłącznie do PUSTEGO słownika (niczego nie nadpisuje). */
function pnb_pl_zasiej_slownik_startowy() {
	global $wpdb;
	$tabela = pnb_pl_tabela_slownika();
	if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tabela" ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return;
	}
	$plik = plugin_dir_path( __FILE__ ) . 'dane/slownik-startowy.json';
	if ( ! file_exists( $plik ) ) {
		return;
	}
	$dane = json_decode( (string) file_get_contents( $plik ), true );
	if ( ! is_array( $dane ) ) {
		return;
	}
	// stary format = goła lista; nowy = {zrodlo, wpisy}
	$wpisy  = isset( $dane['wpisy'] ) ? $dane['wpisy'] : $dane;
	$zrodlo = isset( $dane['zrodlo'] ) ? untrailingslashit( (string) $dane['zrodlo'] ) : '';
	$tutaj  = untrailingslashit( home_url() );
	foreach ( (array) $wpisy as $w ) {
		if ( empty( $w['original'] ) || empty( $w['translated'] ) ) {
			continue;
		}
		$org = $w['original'];
		$tlu = $w['translated'];
		if ( $zrodlo && $zrodlo !== $tutaj ) {
			// segmenty z linkami niosą adres strony, na której tłumaczono — przepisz na tę stronę
			$org = str_replace( $zrodlo, $tutaj, $org );
			$tlu = str_replace( $zrodlo, $tutaj, $tlu );
		}
		pnb_pl_zapisz_segment( $org, $tlu, isset( $w['block_type'] ) ? $w['block_type'] : '' );
	}
	if ( function_exists( 'pnb_pl_wyczysc_cache_par' ) ) {
		pnb_pl_wyczysc_cache_par();
	}
}
