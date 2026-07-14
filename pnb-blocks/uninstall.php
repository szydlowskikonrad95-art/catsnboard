<?php
/*
 * Sprzątanie przy ODINSTALOWANIU pluginu (WP woła ten plik gdy klient usuwa wtyczkę z Wtyczek → Usuń).
 * Zasada „po odpięciu jak było": deaktywacja NIC nie kasuje (dane zostają, można wrócić),
 * ale pełne USUNIĘCIE = klient świadomie mówi „precz" → czyścimy nasze opcje i dane, żeby nie zostawić śmieci.
 *
 * ⚠️ RODO: CPT pnb_zapis trzyma DANE GOŚCI (maile, telefony zapisanych na wydarzenia). Przy usunięciu
 * pluginu kasujemy je razem z resztą — klient nie ma prawa trzymać ich „w tle" po usunięciu narzędzia.
 *
 * SYMETRIA (2026-07-14, recenzja): kasujemy też to, co wtyczka SAMA stworzyła przy aktywacji
 * (strony Events/Gallery, 3 wydarzenia demo) — ale TYLKO w stanie nietkniętym (klient nic nie
 * edytował). Cokolwiek zmienione przez klienta = jego treść, zostaje. Wydarzenia WŁASNE klienta
 * i zaimportowane — patrz decyzja przy sekcji 3. Zdjęcia w Bibliotece mediów zawsze zostają.
 */

// WordPress ustawia tę stałą tylko gdy sam woła uninstall. Bezpośrednie wejście = stop.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/* 0. ID stron utworzonych przez wtyczkę — ZDJĄĆ ZANIM skasujemy opcje (sekcja 1 je czyści). */
$pnb_un_events_id  = (int) get_option( 'pnb_wydarzenia_strona', 0 );
$pnb_un_galeria_id = (int) get_option( 'pnb_galeria_strona', 0 );

/* 1. OPCJE pluginu (ustawienia — galeria, mail powiadomień). */
$opcje = array(
	'pnb_galeria',            // grupa Settings API
	'pnb_galeria_strona',     // ID strony galerii
	'pnb_galeria_zdjecia',    // lista wybranych zdjęć
	'pnb_kalendarz_email',    // email na który wpadają powiadomienia o zapisach
	'pnb_wydarzenia_strona',  // ID strony Events (jeśli był zapisany)
	'pnb_pl_wlaczony',        // flaga PL (z rdzenia)
	'pnb_teksty',             // słownik tekstów rdzenia (czytany w rdzen.php — audyt 2026-07-05)
	'pnb_kontakt_tel',        // kontakt w sekcji mapy
	'pnb_kontakt_mail',
	'pnb_events_hero_id',           // zdjęcie hero kalendarza
	'pnb_importer_source_url',      // importer: adres źródła wydarzeń
	'pnb_importer_breaker_do',      // importer: circuit breaker (pauza „do kiedy")
	'pnb_importer_porazki',         // importer: licznik porażek źródła
	'pnb_importer_ostatnia_liczba', // importer: liczba wydarzeń z ostatniego cyklu
	'pnb_importer_dead',            // importer: dead-letter (odłożone wydarzenia)
	'pnb_importer_log',             // importer: dziennik cykli
	'pnb_scraper_status',           // importer: status ostatniego syncu (ekran stanu)
	'pnb_importer_lock',            // importer: blokada „jeden cykl naraz" (zwykle self-cleaning,
	                                // ale przy usunięciu w trakcie cyklu mogłaby zostać — audyt 2026-07-10)
);
foreach ( $opcje as $o ) {
	delete_option( $o );
}

/* 2. DANE GOŚCI — CPT pnb_zapis (zapisy na wydarzenia: mail, telefon). RODO: kasujemy z meta.
 * force=true → omija kosz (twarde usunięcie, bo to dane osobowe). */
$zapisy = get_posts( array(
	'post_type'      => 'pnb_zapis',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'fields'         => 'ids',
	'suppress_filters' => true,
) );
foreach ( $zapisy as $id ) {
	wp_delete_post( $id, true );
}

/* 3. WYDARZENIA — CPT pnb_wydarzenie. To TREŚĆ klienta (nie dane osobowe, nie śmieć techniczny).
 * Zostawiamy: gdyby klient wgrał plugin ponownie, jego wydarzenia wrócą. Kasowanie treści przy
 * odinstalowaniu byłoby destrukcyjne (klient mógł usunąć plugin przez pomyłkę). Świadoma decyzja.
 * WYJĄTEK (sekcja 4): nasze 3 dema z aktywacji, o ile klient ich NIE zmienił. */

/* 4. WYDARZENIA DEMO z aktywacji — kasujemy TYLKO nietknięte (odcisk palca się zgadza).
 * Aktywacja zapisuje md5(tytuł|treść) w _pnb_demo_hash; klient edytował = hash się nie zgadza
 * = zostaje (to już jego treść). Starsze instalacje bez odcisku: dem nie ruszamy (bezpieczniej). */
$pnb_un_dema = get_posts( array(
	'post_type'        => 'pnb_wydarzenie',
	'post_status'      => 'any',
	'numberposts'      => -1,
	'fields'           => 'ids',
	'meta_key'         => '_pnb_demo_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	'suppress_filters' => true,
) );
foreach ( $pnb_un_dema as $pnb_un_id ) {
	$pnb_un_post = get_post( $pnb_un_id );
	if ( ! $pnb_un_post ) {
		continue;
	}
	$pnb_un_hash = (string) get_post_meta( $pnb_un_id, '_pnb_demo_hash', true );
	if ( $pnb_un_hash === md5( $pnb_un_post->post_title . '|' . $pnb_un_post->post_content ) ) {
		wp_delete_post( $pnb_un_id, true ); // nietknięte demo → precz (zdjęcia w mediach zostają)
	}
}

/* 5. STRONY Events/Gallery utworzone przy aktywacji — kasujemy TYLKO skorupy w stanie fabrycznym.
 * Events: treść to dokładnie nasz goły blok. Gallery: JEDEN blok pnb/galeria i nic więcej
 * (atrybuty z ID zdjęć różnią się między instalacjami, więc porównujemy kształt, nie bajty).
 * Jakakolwiek edycja klienta (dopisany tekst, inne bloki) = strona zostaje. */
$pnb_un_events = $pnb_un_events_id ? get_post( $pnb_un_events_id ) : get_page_by_path( 'events' );
if ( $pnb_un_events && 'page' === $pnb_un_events->post_type
	&& '<!-- wp:pnb/wydarzenia /-->' === trim( $pnb_un_events->post_content ) ) {
	wp_delete_post( $pnb_un_events->ID, true );
}
$pnb_un_galeria = $pnb_un_galeria_id ? get_post( $pnb_un_galeria_id ) : get_page_by_path( 'gallery' );
if ( $pnb_un_galeria && 'page' === $pnb_un_galeria->post_type ) {
	$pnb_un_tresc = trim( $pnb_un_galeria->post_content );
	if ( 1 === substr_count( $pnb_un_tresc, '<!-- wp:' )
		&& preg_match( '#\A<!-- wp:pnb/galeria\b.*/-->\z#s', $pnb_un_tresc ) ) {
		wp_delete_post( $pnb_un_galeria->ID, true );
	}
}
