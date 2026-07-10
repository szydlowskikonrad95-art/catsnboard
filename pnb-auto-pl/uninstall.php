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

// opcje (w tym KLUCZ API — sekret klienta nie zostaje w bazie)
foreach ( array(
	'pnb_auto_pl_klucz',
	'pnb_auto_pl_model',
	'pnb_auto_pl_limit_znakow',
	'pnb_pl_licznik',
	'pnb_pl_nieaktualne',
	'pnb_pl_cache_wersja',      // licznik wersji cache stron PL (front.php)
	'pnb_pl_cache_kod_wersja',  // wersja wtyczki przy ostatnim czyszczeniu cache (front.php)
) as $opcja ) {
	delete_option( $opcja );
}

// transienty (cache par + stare pnb_cs_*)
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pnb_%' OR option_name LIKE '_transient_timeout_pnb_%'" ); // phpcs:ignore WordPress.DB
