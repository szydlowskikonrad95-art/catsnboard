<?php
/*
 * Sprzątanie przy ODINSTALOWANIU pluginu (WP woła ten plik gdy klient usuwa wtyczkę z Wtyczek → Usuń).
 * Zasada „po odpięciu jak było": deaktywacja NIC nie kasuje (dane zostają, można wrócić),
 * ale pełne USUNIĘCIE = klient świadomie mówi „precz" → czyścimy nasze opcje i dane, żeby nie zostawić śmieci.
 *
 * ⚠️ RODO: CPT pnb_zapis trzyma DANE GOŚCI (maile, telefony zapisanych na wydarzenia). Przy usunięciu
 * pluginu kasujemy je razem z resztą — klient nie ma prawa trzymać ich „w tle" po usunięciu narzędzia.
 * Wydarzeń (pnb_wydarzenie) NIE ruszamy? — patrz decyzja niżej.
 */

// WordPress ustawia tę stałą tylko gdy sam woła uninstall. Bezpośrednie wejście = stop.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

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
 * odinstalowaniu byłoby destrukcyjne (klient mógł usunąć plugin przez pomyłkę). Świadoma decyzja. */
