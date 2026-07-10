<?php
/*
 * Plugin Name:       PNB Gallery & Events
 * Description:       Premium gallery (film strip + "Moments" section) and an events calendar with guest sign-ups — two Gutenberg blocks (Gallery and Events pages), editable in the block editor. Does not touch the rest of the site.
 * Version:           1.10.37
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            dzidek
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pnb-toolkit
 * Domain Path:       /languages
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Stałe — nazwy PNB_TOOLKIT_* zachowane (moduły galerii/kalendarza ich używają; brak przepisywania). */
define( 'PNB_TOOLKIT_VERSION', '1.10.37' );
define( 'PNB_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'PNB_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );

/* TŁUMACZENIA: napisy pluginu domyślnie po ANGIELSKU (standard WordPress). Plik languages/pnb-toolkit-pl_PL.mo
 * → WordPress po polsku pokaże plugin po polsku automatycznie (bez Chrome Translate). */
add_action( 'init', function () {
	load_plugin_textdomain( 'pnb-toolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/* RDZEŃ: minimalny silnik tekstów/języka (pnb_txt, pnb_lang) — bez panelu, bez przejmowania edytora. */
require_once PNB_TOOLKIT_DIR . 'modules/rdzen.php';

/* GALERIA: silnik (render taśmy, lightbox, assety) + blok Gutenberg. */
require_once PNB_TOOLKIT_DIR . 'modules/galeria.php';
require_once PNB_TOOLKIT_DIR . 'modules/blok-galeria.php';

/* KALENDARZ: silnik (CPT wydarzenie, render, zapisy, maile, metaboxy) + blok Gutenberg. */
require_once PNB_TOOLKIT_DIR . 'modules/kalendarz.php';
require_once PNB_TOOLKIT_DIR . 'modules/blok-wydarzenia.php';
require_once PNB_TOOLKIT_DIR . 'modules/importer.php'; // automat wydarzeń W PLUGINIE (WP-Cron, bez Pythona)

/* Aktywacja: rejestracja CPT wydarzeń + flush rewrite (żeby /events/, single wydarzeń działały od razu). */
register_activation_hook( __FILE__, 'pnb_blocks_aktywacja' );
function pnb_blocks_aktywacja() {
	if ( function_exists( 'pnb_kalendarz_rejestruj' ) ) {
		pnb_kalendarz_rejestruj();
	}
	/* Strona Events powstaje dopiero Z wtyczką (kalendarza nie ma bez niej).
	   Raz, przy aktywacji; istniejącej nie ruszamy. */
	if ( ! get_page_by_path( 'events' ) ) {
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Events',
			'post_name'    => 'events',
			'post_content' => '<!-- wp:pnb/wydarzenia /-->',
			'post_author'  => 0, // jak reszta stron witryny — bez autora (aktywacja z panelu podpisywałaby admina)
		) );
	}
	/* Gallery: motyw sieje prostą galerię (blok catsnboard/gallery) — podmieniamy ją
	   na premium TYLKO gdy strona ma dokładnie ten goły blok (żadnych edycji klienta).
	   Brak strony → tworzymy z premium. Cokolwiek innego w treści → nie tykamy. */
	$galeria = get_page_by_path( 'gallery' );
	if ( ! $galeria ) {
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Gallery',
			'post_name'    => 'gallery',
			'post_content' => pnb_blocks_demo_galeria_tresc(),
			'post_author'  => 0, // jak reszta stron witryny — bez autora
		) );
	} elseif ( '<!-- wp:catsnboard/gallery /-->' === trim( $galeria->post_content ) ) {
		wp_update_post( array(
			'ID'           => $galeria->ID,
			'post_content' => pnb_blocks_demo_galeria_tresc(),
		) );
	}
	/* Wydarzenia DEMO (tylko gdy kalendarz całkiem PUSTY): świeża strona ma od razu
	   wyglądać jak żywa — 3 przykładowe wydarzenia ze zdjęciami z motywu (hero kalendarza
	   bierze zdjęcie z 1. wydarzenia). Klient edytuje/usuwa je jak swoje. */
	$sa_wydarzenia = get_posts( array(
		'post_type'      => 'pnb_wydarzenie',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	if ( ! $sa_wydarzenia ) {
		pnb_blocks_zasiej_demo_wydarzenia();
	}
	// Tło hero podstrony Events: świeża instalacja dostaje demo-zdjęcie od razu (jak galeria/wydarzenia),
	// żeby hero nie był pusty zanim klient wgra własne. Tylko gdy NIEustawione (nie nadpisujemy wyboru
	// klienta przy reaktywacji). Kot-7 = spokojny kadr poziomy, dobry pod napis „Save the date".
	if ( ! (int) get_option( 'pnb_events_hero_id', 0 ) ) {
		$hero = pnb_blocks_demo_zalacznik( 'kot-7.jpg' );
		if ( $hero ) {
			update_option( 'pnb_events_hero_id', (int) $hero );
		}
	}
	// Zaplanuj automat importu (WP-Cron co 10 min) — startuje gdy klient ustawi źródło w panelu.
	if ( function_exists( 'pnb_importer_zaplanuj' ) ) {
		pnb_importer_zaplanuj();
	}
	flush_rewrite_rules();
}

/* Kopiuje zdjęcie z motywu do biblioteki mediów (na obcym motywie — brak pliku — zwraca 0).
   Bez dubli: to samo zdjęcie użyte przez galerię i wydarzenie = JEDEN załącznik. */
function pnb_blocks_demo_zalacznik( $plik ) {
	$tytul = preg_replace( '/\.[^.]+$/', '', $plik );
	$sa    = get_posts(
		array(
			'post_type'      => 'attachment',
			'title'          => $tytul,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'inherit',
		)
	);
	if ( $sa ) {
		return (int) $sa[0];
	}
	$zrodlo = get_template_directory() . '/assets/img/' . $plik;
	if ( ! file_exists( $zrodlo ) ) {
		// Obcy motyw (brak zdjęć motywu) → wtyczka jest samowystarczalna: własny komplet demo
		// w assets/img-demo/ (decyzja 2026-07-07: pełna galeria/kalendarz też bez naszego motywu).
		$zrodlo = PNB_TOOLKIT_DIR . 'assets/img-demo/' . $plik;
	}
	if ( ! file_exists( $zrodlo ) ) {
		return 0;
	}
	$up = wp_upload_bits( $plik, null, (string) file_get_contents( $zrodlo ) );
	if ( ! empty( $up['error'] ) ) {
		return 0;
	}
	$typ = wp_check_filetype( $up['file'] );
	$id  = wp_insert_attachment(
		array(
			'post_mime_type' => $typ['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $plik ),
			'post_status'    => 'inherit',
		),
		$up['file']
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	return (int) $id;
}

/* Treść bloku galerii z kotami DEMO jako prawdziwymi załącznikami w bibliotece mediów —
   klient od pierwszej chwili EDYTUJE kafelki w edytorze (przestawia/kasuje/dodaje),
   zamiast patrzeć na „Choose photos (0)". Zdjęcia: najpierw motyw, bez motywu — komplet demo z wtyczki. */
function pnb_blocks_demo_galeria_tresc() {
	$pliki = array(
		'kot-1.jpg', 'kot-6.jpg', 'kot-3.jpg', 'kot-8.jpg', 'kot-2.jpg', 'kot-15.jpg',
		'kot-4.jpg', 'kot-11.jpg', 'kot-7.jpg', 'kot-13.jpg', 'kot-10.jpg', 'kot-16.jpg',
	);
	$ids = array();
	foreach ( $pliki as $plik ) {
		$id = pnb_blocks_demo_zalacznik( $plik );
		if ( $id ) {
			$ids[] = $id;
		}
	}
	if ( ! $ids ) {
		return '<!-- wp:pnb/galeria /-->';
	}
	$atrybuty = array( 'imageIds' => $ids );
	// sekcja „Moments" dostaje WŁASNY zestaw demo: 12 zdjęć = 6 na rzekę (rzeki zapętlają
	// swój zestaw — przy mniejszej liczbie powtórki tego samego kota rażą w oczy)
	$moments = array();
	foreach ( array(
		'kot-5.jpg', 'kot-9.jpg', 'kot-12.jpg', 'kot-14.jpg', 'kot-6.jpg', 'kot-15.jpg',
		'kot-1.jpg', 'kot-3.jpg', 'kot-8.jpg', 'kot-2.jpg', 'kot-11.jpg', 'kot-13.jpg',
	) as $plik ) {
		$id = pnb_blocks_demo_zalacznik( $plik );
		if ( $id ) {
			$moments[] = $id;
		}
	}
	if ( $moments ) {
		$atrybuty['momentsIds'] = $moments;
	}
	$hero = pnb_blocks_demo_zalacznik( 'kot-7.jpg' );
	if ( $hero ) {
		$atrybuty['heroImageId']  = $hero;
		$atrybuty['heroImageUrl'] = (string) wp_get_attachment_image_url( $hero, 'full' );
	}
	return '<!-- wp:pnb/galeria ' . wp_json_encode( $atrybuty ) . ' /-->';
}

/* 3 wydarzenia demo (daty względne od dziś — kalendarz nigdy nie startuje pusty ani przeterminowany). */
function pnb_blocks_zasiej_demo_wydarzenia() {
	$demo = array(
		array(
			'title'   => 'Kitten Adoption Day',
			'content' => "Spend an afternoon with our kittens who are ready to find their forever homes. You'll get to meet each little one, learn about their personality and needs, and chat with our carers about what adopting a cat really involves. No pressure to decide on the spot — come just to say hello, give some cuddles, and see if one of them purrs their way into your heart. Coffee, treats and plenty of kittens included.",
			'za_dni'  => 2,
			'time'    => '12:00',
			'time_end' => '14:00',
			'limit'   => 8,
			'cat'     => 'adoption',
			'foto'    => 'kot-8.jpg',
		),
		array(
			'title'   => 'Cat Socialising Class',
			'content' => "A calm, gentle session for shy or nervous cats who need a little help feeling safe around people and other cats. In a quiet space and at your cat's own pace, we use positive play and slow introductions to build confidence — no forcing, no stress. Our carer will guide you through simple techniques you can continue at home. Perfect for kittens finding their paws or older cats who stay hidden when guests arrive. Small group, big patience.",
			'za_dni'  => 9,
			'time'    => '17:30',
			'time_end' => '19:00',
			'limit'   => 8,
			'cat'     => 'class',
			'foto'    => 'kot-2.jpg',
		),
		array(
			'title'   => 'Open Doors Day',
			'content' => "Curious where your cat would stay? Come and see for yourself. Our doors are open all day — walk through the boarding rooms, peek into the sunny nap spots and play areas, and meet the aunties and uncles who'll be looking after your furry friend. Ask us anything: feeding, medication, how we keep shy cats comfortable, how booking works. No appointment needed, bring the whole family. It's the easiest way to know your cat will be in good hands.",
			'za_dni'  => 18,
			'time'    => '11:00',
			'time_end' => '15:00',
			'limit'   => 0,
			'cat'     => 'openday',
			'foto'    => 'kot-4.jpg',
		),
	);
	foreach ( $demo as $w ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'pnb_wydarzenie',
				'post_status'  => 'publish',
				'post_title'   => $w['title'],
				'post_content' => $w['content'],
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_pnb_event_date', gmdate( 'Y-m-d', time() + $w['za_dni'] * DAY_IN_SECONDS ) );
		update_post_meta( $id, '_pnb_event_time', $w['time'] );
		update_post_meta( $id, '_pnb_event_time_end', $w['time_end'] );
		update_post_meta( $id, '_pnb_event_place', "Cats'N'Board" );
		update_post_meta( $id, '_pnb_event_limit', (int) $w['limit'] );
		update_post_meta( $id, '_pnb_event_cat', $w['cat'] );
		$foto = pnb_blocks_demo_zalacznik( $w['foto'] );
		if ( $foto ) {
			set_post_thumbnail( $id, $foto );
		}
		// AUTO-TŁUMACZENIE demo (2026-07-09): demo szło INNĄ drogą niż scrapowane (importer woła
		// pnb_pl_auto_po_zapisie → pełny opis PL), przez co PEŁNY opis demo na singlu zostawał EN
		// (skrót na karcie łapał gotowiec, ale pełny opis akapitami — nie). Teraz demo woła to samo
		// co scrapowane. GUARD: tylko gdy klucz API wpięty — inaczej pomija (bez klucza aktywacja nie
		// wisi, opis dotłumaczy się gdy klient wpisze klucz i kliknie „Przetłumacz witrynę”).
		if ( function_exists( 'pnb_pl_auto_po_zapisie' ) && '' !== trim( (string) get_option( 'pnb_auto_pl_klucz', '' ) ) ) {
			pnb_pl_auto_po_zapisie( get_post( $id ) );
		}
	}
}

register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'pnb_importer_odplanuj' ) ) {
		pnb_importer_odplanuj(); // wyłącz automat gdy wtyczka nieaktywna
	}
	flush_rewrite_rules();
} );
