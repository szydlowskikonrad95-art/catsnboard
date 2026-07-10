<?php
/**
 * TEST TRYBÓW AWARII importera (symulacje na żywym, TESTOWYM WordPressie).
 *
 * ⚠️ TYLKO NA INSTALACJI TESTOWEJ — test manipuluje opcjami importera, kasuje i odtwarza
 *    jedno wydarzenie, wyłącza na czas testu harmonogram crona (przywraca na końcu).
 *
 * Jak uruchomić (2 kroki, z katalogu głównego WordPressa):
 *   1. w drugim terminalu: php -S 127.0.0.1:9999 wp-content/plugins/pnb-blocks/../../../testy/awarie/mock-zrodlo.php
 *      (albo wskaż mock-zrodlo.php tam, gdzie leży — ważne, żeby słuchał na 127.0.0.1:9999)
 *   2. php testy/awarie/test-awarie.php   (albo: wp eval-file testy/awarie/test-awarie.php)
 *
 * Co sprawdza (każdy scenariusz = PASS/FAIL z dowodem):
 *   A. LOCK — drugi cykl przy aktywnym locku jest pomijany
 *   B. CIRCUIT BREAKER — źródło HTTP 500: porażka→pauza 20 min, cykl w pauzie pomijany,
 *      kolejna porażka→40 min (eskalacja)
 *   C. PUSTE ŹRÓDŁO ×3 — 0 wydarzeń NIE kasuje strony; po 3. razie breaker
 *   D. PODEJRZANY SPADEK — źródło nagle daje <30% poprzedniej liczby → wygasanie wstrzymane
 *   E. DEAD LETTER — wydarzenie oznaczone jako martwe (≥5 porażek) jest pomijane,
 *      po odblokowaniu wraca w następnym cyklu
 * Na końcu: przywrócenie źródła, liczników i harmonogramu crona.
 *
 * Ostatni przebieg: 2026-07-10 na poligonie odbiorczym — 5/5 PASS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( __DIR__, 2 ) . '/wp-load.php'; // testy/awarie/ → korzeń WP
	if ( ! file_exists( $wp_load ) ) {
		$wp_load = getcwd() . '/wp-load.php';
	}
	if ( ! file_exists( $wp_load ) ) {
		fwrite( STDERR, "Nie widzę wp-load.php — odpal z katalogu głównego WordPressa.\n" );
		exit( 1 );
	}
	define( 'WP_USE_THEMES', false );
	require $wp_load;
}

$MOCK = 'http://127.0.0.1:9999';

function pnb_t_cykl() { pnb_importer_jeden_cykl(); delete_option( 'pnb_importer_lock' ); }
function pnb_t_ile() { $c = wp_count_posts( 'pnb_wydarzenie' ); return (int) $c->publish; }
function pnb_t_log() { $l = get_option( 'pnb_importer_log', array() ); $e = is_array( $l ) ? end( $l ) : ''; return is_array( $e ) ? wp_json_encode( $e ) : (string) $e; }

// sanity: mock żyje?
$ping = wp_remote_get( "$MOCK/pad", array( 'timeout' => 3 ) );
if ( is_wp_error( $ping ) ) {
	fwrite( STDERR, "Mock nie odpowiada na $MOCK — najpierw: php -S 127.0.0.1:9999 mock-zrodlo.php\n" );
	exit( 1 );
}

// wygeneruj /malo z 2 PRAWDZIWYCH source_id z bazy (alert spadku potrzebuje realnych ID)
$sidy = get_posts( array( 'post_type' => 'pnb_wydarzenie', 'post_status' => 'publish', 'posts_per_page' => 2,
	'fields' => 'ids', 'meta_key' => '_pnb_source_id' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
if ( count( $sidy ) < 2 ) {
	fwrite( STDERR, "Za mało zaimportowanych wydarzeń (potrzeba ≥2) — odpal najpierw normalny cykl importu.\n" );
	exit( 1 );
}
$html = "<html><body>\n";
foreach ( $sidy as $pid ) {
	$num   = preg_replace( '/\D/', '', (string) get_post_meta( $pid, '_pnb_source_id', true ) );
	$html .= '<a href="/e/test-tickets-' . $num . '">w</a>' . "\n";
}
$html .= '</body></html>';
file_put_contents( sys_get_temp_dir() . '/pnb-mock-malo.html', $html );

$ORYG = get_option( 'pnb_importer_source_url' );
wp_clear_scheduled_hook( 'pnb_importer_cykl' ); // cisza od crona na czas testu
$W = array();

// ── A. LOCK ──
update_option( 'pnb_importer_lock', time() + 300, false );
$przed = get_option( 'pnb_scraper_status' );
pnb_importer_jeden_cykl();
$po = get_option( 'pnb_scraper_status' );
delete_option( 'pnb_importer_lock' );
$W['A-lock'] = ( $przed === $po ) ? 'PASS — cykl pominięty (status nietknięty)' : 'FAIL — cykl przebił lock!';

// ── B. BREAKER ──
update_option( 'pnb_importer_source_url', "$MOCK/pad" );
update_option( 'pnb_importer_porazki', 0 ); update_option( 'pnb_importer_breaker_do', 0 );
pnb_t_cykl();
$p1 = (int) get_option( 'pnb_importer_porazki' ); $b1 = (int) get_option( 'pnb_importer_breaker_do' ) - time();
pnb_t_cykl(); // w pauzie → pomija
$p2 = (int) get_option( 'pnb_importer_porazki' );
update_option( 'pnb_importer_breaker_do', 0 ); pnb_t_cykl(); // „upłynął czas" → 2. porażka
$p3 = (int) get_option( 'pnb_importer_porazki' ); $b3 = (int) get_option( 'pnb_importer_breaker_do' ) - time();
$W['B-breaker'] = ( 1 === $p1 && $b1 > 1100 && $b1 < 1300 && 1 === $p2 && 2 === $p3 && $b3 > 2300 && $b3 < 2500 )
	? 'PASS — porażka1=20min, cykl w pauzie pominięty, porażka2=40min (eskalacja)'
	: "FAIL — p1=$p1 b1={$b1}s p2=$p2 p3=$p3 b3={$b3}s";

// ── C. PUSTE ŹRÓDŁO ×3 ──
update_option( 'pnb_importer_source_url', "$MOCK/pusta" );
update_option( 'pnb_importer_porazki', 0 ); update_option( 'pnb_importer_breaker_do', 0 );
$cnt = pnb_t_ile();
pnb_t_cykl(); pnb_t_cykl(); pnb_t_cykl();
$pc = (int) get_option( 'pnb_importer_porazki' ); $bc = (int) get_option( 'pnb_importer_breaker_do' );
$W['C-puste'] = ( pnb_t_ile() === $cnt && 3 === $pc && $bc > time() )
	? "PASS — 0 wydarzeń ×3: strona nietknięta ($cnt), porażki=3, breaker włączony | " . pnb_t_log()
	: 'FAIL — przed=' . $cnt . ' po=' . pnb_t_ile() . " porazki=$pc breaker=" . ( $bc > time() ? 'on' : 'OFF' );

// ── D. PODEJRZANY SPADEK ──
update_option( 'pnb_importer_source_url', "$MOCK/malo" );
update_option( 'pnb_importer_porazki', 0 ); update_option( 'pnb_importer_breaker_do', 0 );
update_option( 'pnb_importer_ostatnia_liczba', max( 10, pnb_t_ile() ) );
$cnt = pnb_t_ile();
pnb_t_cykl();
$stat = get_option( 'pnb_scraper_status' );
$W['D-spadek'] = ( pnb_t_ile() === $cnt && isset( $stat['pobrane'] ) && 2 === (int) $stat['pobrane'] )
	? "PASS — źródło nagle daje 2: wygasanie wstrzymane, nic nie poszło do kosza ($cnt)"
	: 'FAIL — przed=' . $cnt . ' po=' . pnb_t_ile() . ' pobrane=' . ( isset( $stat['pobrane'] ) ? $stat['pobrane'] : '?' );

// ── E. DEAD LETTER (2 realne cykle — wymaga dostępu do prawdziwego źródła) ──
update_option( 'pnb_importer_source_url', $ORYG );
update_option( 'pnb_importer_porazki', 0 ); update_option( 'pnb_importer_breaker_do', 0 );
$ofiara = get_posts( array( 'post_type' => 'pnb_wydarzenie', 'post_status' => 'publish', 'posts_per_page' => 1,
	'fields' => 'ids', 'meta_key' => '_pnb_source_id', 'orderby' => 'ID', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
$oid = $ofiara[0]; $sid = get_post_meta( $oid, '_pnb_source_id', true ); $tyt = get_the_title( $oid );
wp_delete_post( $oid, true );
$martwe = get_option( 'pnb_importer_dead', array() ); if ( ! is_array( $martwe ) ) { $martwe = array(); }
$martwe[ $sid ] = array( 'proby' => 5, 'tytul' => $tyt, 'kiedy' => current_time( 'mysql' ) );
update_option( 'pnb_importer_dead', $martwe, false );
pnb_t_cykl();
$jest1 = get_posts( array( 'post_type' => 'pnb_wydarzenie', 'post_status' => 'any', 'posts_per_page' => 1,
	'fields' => 'ids', 'meta_key' => '_pnb_source_id', 'meta_value' => $sid ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
$martwe = get_option( 'pnb_importer_dead', array() ); unset( $martwe[ $sid ] );
update_option( 'pnb_importer_dead', $martwe, false );
pnb_t_cykl();
$jest2 = get_posts( array( 'post_type' => 'pnb_wydarzenie', 'post_status' => 'any', 'posts_per_page' => 1,
	'fields' => 'ids', 'meta_key' => '_pnb_source_id', 'meta_value' => $sid ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
$W['E-deadletter'] = ( empty( $jest1 ) && ! empty( $jest2 ) )
	? "PASS — martwe („{$tyt}\") pominięte mimo obecności w źródle; po odblokowaniu odtworzone"
	: 'FAIL — po-blokadzie=' . count( $jest1 ) . ' po-odblokowaniu=' . count( $jest2 );

// ── F. SPRZĄTANIE ──
update_option( 'pnb_importer_porazki', 0 ); update_option( 'pnb_importer_breaker_do', 0 );
pnb_importer_zaplanuj();
$W['F-stan'] = 'wydarzenia=' . pnb_t_ile() . ' | źródło przywrócone | cron=' . ( wp_next_scheduled( 'pnb_importer_cykl' ) ? 'zaplanowany' : 'BRAK!' );

$fail = 0;
foreach ( $W as $k => $v ) {
	echo "$k: $v\n";
	if ( 0 === strpos( $v, 'FAIL' ) ) { $fail++; }
}
echo $fail ? "❌ FAIL — $fail scenariusz(e) padły\n" : "✅ PASS — wszystkie tryby awarii zachowują się zgodnie z projektem\n";
exit( $fail ? 1 : 0 );
