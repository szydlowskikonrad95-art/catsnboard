<?php
/**
 * Furtka REST — wejście dla automatyzacji (scraper wydarzeń).
 *
 * Nasz kalendarz (CPT pnb_wydarzenie) ma show_in_rest => false, więc core wp/v2
 * nie działa. Ten moduł dodaje własny, chroniony endpoint POST /pnb/v1/events,
 * przez który zewnętrzny automat może dodać/zaktualizować wydarzenie.
 *
 * Autoryzacja: Application Password (WP ustawia current_user z Basic Auth przed
 * permission_callback). Wymagane uprawnienie: publish_posts (rola Editor/Admin).
 *
 * @package pnb-blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dozwolone kategorie wydarzeń (whitelista — musi zgadzać się z kalendarz.php). */
const PNB_REST_KATEGORIE = array( 'adoption', 'class', 'openday', 'other' );

/**
 * Rejestracja endpointu.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'pnb/v1',
			'/events',
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST.
				'callback'            => 'pnb_rest_utworz_wydarzenie',
				'permission_callback' => 'pnb_rest_sprawdz_uprawnienia',
			)
		);
		// Sprzątanie: automat podaje listę AKTUALNYCH source_id → importowane wydarzenia spoza listy
		// (zniknęły ze źródła) lądują w koszu. Trzyma stronę czystą (bez odwołanych/minionych).
		register_rest_route(
			'pnb/v1',
			'/events/sprzataj',
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST.
				'callback'            => 'pnb_rest_sprzataj_wygasle',
				'permission_callback' => 'pnb_rest_sprawdz_uprawnienia',
			)
		);
		// Monitoring: scraper wysyła metryki cyklu → panel pokazuje „ostatni sync / ile / błędy".
		register_rest_route(
			'pnb/v1',
			'/status',
			array(
				'methods'             => WP_REST_Server::CREATABLE, // POST.
				'callback'            => 'pnb_rest_zapisz_status',
				'permission_callback' => 'pnb_rest_sprawdz_uprawnienia',
			)
		);
	}
);

/**
 * Monitoring: zapisuje metryki ostatniego cyklu scrapera jako opcję WP. Panel (Events → Settings)
 * pokazuje je + „ile minut temu ostatni sync". Klient widzi że automat żyje bez wchodzenia na serwer.
 */
function pnb_rest_zapisz_status( WP_REST_Request $req ) {
	$dane = $req->get_json_params();
	if ( ! is_array( $dane ) ) {
		return new WP_Error( 'pnb_zly_json', 'Body musi być JSON.', array( 'status' => 400 ) );
	}
	// Tylko znane, liczbowe/tekstowe pola (nie ufamy ślepo).
	$czyste = array(
		'ostatni_sync' => sanitize_text_field( (string) ( $dane['ostatni_sync'] ?? '' ) ),
		'pobrane'      => (int) ( $dane['pobrane'] ?? 0 ),
		'nowe'         => (int) ( $dane['nowe'] ?? 0 ),
		'wyslane'      => (int) ( $dane['wyslane'] ?? 0 ),
		'juz_jest'     => (int) ( $dane['juz_jest'] ?? 0 ),
		'odrzucone'    => (int) ( $dane['odrzucone'] ?? 0 ),
		'bledy'        => (int) ( $dane['bledy'] ?? 0 ),
		'wygasle'      => (int) ( $dane['wygasle'] ?? 0 ),
		'spadek_alert' => ! empty( $dane['spadek_alert'] ),
		'zapisano'     => current_time( 'mysql' ),
	);
	update_option( 'pnb_scraper_status', $czyste );
	return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
}

/**
 * Sprawdzenie uprawnień. WP wiąże Application Password z użytkownikiem PRZED tym
 * wywołaniem, więc current_user_can widzi zalogowanego przez hasło aplikacji.
 *
 * @return bool|WP_Error
 */
function pnb_rest_sprawdz_uprawnienia() {
	if ( current_user_can( 'publish_posts' ) ) {
		return true;
	}
	return new WP_Error(
		'pnb_brak_uprawnien',
		'Brak uprawnień do dodawania wydarzeń.',
		array( 'status' => 401 )
	);
}

/**
 * Sprzątanie wygasłych: automat podaje listę AKTUALNYCH source_id ze źródła. Importowane wydarzenia
 * (mające _pnb_source_id) których NIE MA na tej liście = zniknęły ze źródła → do kosza.
 *
 * Zabezpieczenia (żeby nigdy nie skasować za dużo):
 *  - pusta lista → nic nie robimy (chroni przed wyczyszczeniem strony gdy scraper padł/0 wyników),
 *  - ruszamy TYLKO importowane (_pnb_source_id) — własne wydarzenia klienta nietknięte,
 *  - POMIJAMY ręcznie odblokowane (_pnb_locked) — admin je „przejął", scraper ich nie rusza,
 *  - wp_trash_post (KOSZ, nie delete) — odwracalne, RODO-safe (zapisy gości giną z wydarzeniem przy delete).
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function pnb_rest_sprzataj_wygasle( WP_REST_Request $req ) {
	$dane     = $req->get_json_params();
	$aktualne = ( is_array( $dane ) && isset( $dane['aktualne'] ) && is_array( $dane['aktualne'] ) )
		? array_map( 'sanitize_text_field', $dane['aktualne'] )
		: array();

	// Pusta lista = STOP (nie czyścimy strony gdy nie wiemy co jest aktualne).
	if ( empty( $aktualne ) ) {
		return new WP_REST_Response( array( 'status' => 'skip-empty', 'do_kosza' => 0 ), 200 );
	}

	// Wszystkie IMPORTOWANE, opublikowane wydarzenia (mają source_id).
	$importowane = get_posts(
		array(
			'post_type'      => 'pnb_wydarzenie',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_pnb_source_id', 'compare' => 'EXISTS' ),
			),
		)
	);

	$aktualne_set = array_flip( $aktualne );
	$do_kosza     = 0;
	foreach ( $importowane as $id ) {
		// Ręcznie przejęte przez admina → nie ruszamy.
		if ( get_post_meta( $id, '_pnb_locked', true ) ) {
			continue;
		}
		$sid = get_post_meta( $id, '_pnb_source_id', true );
		if ( $sid && ! isset( $aktualne_set[ $sid ] ) ) {
			wp_trash_post( $id ); // zniknęło ze źródła → do kosza
			++$do_kosza;
		}
	}

	return new WP_REST_Response( array( 'status' => 'ok', 'do_kosza' => $do_kosza ), 200 );
}

/**
 * Utworzenie (lub rozpoznanie duplikatu) wydarzenia z danych automatu.
 *
 * Oczekiwane pola JSON:
 *   source_id  (wymagane) — stały identyfikator wydarzenia u źródła (do dedup).
 *   title      (wymagane)
 *   date       (wymagane) — Y-m-d.
 *   category   (wymagane) — jedna z PNB_REST_KATEGORIE.
 *   time, time_end, place, limit, description — opcjonalne.
 *
 * @param WP_REST_Request $req Żądanie.
 * @return WP_REST_Response|WP_Error
 */
function pnb_rest_utworz_wydarzenie( WP_REST_Request $req ) {
	$dane = $req->get_json_params();
	if ( ! is_array( $dane ) ) {
		return new WP_Error( 'pnb_zly_json', 'Body musi być obiektem JSON.', array( 'status' => 400 ) );
	}

	// Wersja formatu payloadu — przyszłościowo. Znamy schema 1; nowszą przyjmujemy (forward-compatible),
	// ale logujemy ostrzeżenie żeby było wiadomo że warto zaktualizować wtyczkę.
	$schema = isset( $dane['schema_version'] ) ? (int) $dane['schema_version'] : 1;
	if ( $schema > 1 ) {
		error_log( "pnb: payload schema_version=$schema nowszy niż obsługiwany (1) — rozważ aktualizację wtyczki." );
	}

	// --- Pola wymagane ---------------------------------------------------.
	$source_id = isset( $dane['source_id'] ) ? sanitize_text_field( wp_unslash( $dane['source_id'] ) ) : '';
	$title     = isset( $dane['title'] ) ? sanitize_text_field( wp_unslash( $dane['title'] ) ) : '';
	$date      = isset( $dane['date'] ) ? sanitize_text_field( wp_unslash( $dane['date'] ) ) : '';
	$category  = isset( $dane['category'] ) ? sanitize_text_field( wp_unslash( $dane['category'] ) ) : '';

	if ( '' === $source_id ) {
		return new WP_Error( 'pnb_brak_source_id', 'Brak pola source_id.', array( 'status' => 400 ) );
	}
	if ( '' === $title ) {
		return new WP_Error( 'pnb_brak_title', 'Brak pola title.', array( 'status' => 400 ) );
	}
	// Data musi być realnym Y-m-d (nie ufamy że automat da poprawną).
	if ( ! pnb_rest_data_ok( $date ) ) {
		return new WP_Error( 'pnb_zla_data', 'Pole date musi być w formacie Y-m-d i być realną datą.', array( 'status' => 400 ) );
	}
	if ( ! in_array( $category, PNB_REST_KATEGORIE, true ) ) {
		return new WP_Error(
			'pnb_zla_kategoria',
			'Pole category musi być jedną z: ' . implode( ', ', PNB_REST_KATEGORIE ) . '.',
			array( 'status' => 400 )
		);
	}

	// --- Idempotencja: czy wydarzenie o tym source_id już jest? ----------.
	$istnieje = get_posts(
		array(
			'post_type'      => 'pnb_wydarzenie',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_pnb_source_id',
					'value' => $source_id,
				),
			),
		)
	);
	// --- Pola opcjonalne -------------------------------------------------.
	$time        = isset( $dane['time'] ) ? sanitize_text_field( wp_unslash( $dane['time'] ) ) : '';
	$time_end    = isset( $dane['time_end'] ) ? sanitize_text_field( wp_unslash( $dane['time_end'] ) ) : '';
	$place       = isset( $dane['place'] ) ? sanitize_text_field( wp_unslash( $dane['place'] ) ) : "Cats'N'Board";
	$limit       = isset( $dane['limit'] ) ? (int) $dane['limit'] : 0;
	$description = isset( $dane['description'] ) ? wp_kses_post( wp_unslash( $dane['description'] ) ) : '';

	// --- Odwołanie u źródła: cancelled → trash (nie zostawiamy martwego wydarzenia z zapisami) --.
	$status_src = isset( $dane['status'] ) ? sanitize_key( $dane['status'] ) : '';
	if ( 'cancelled' === $status_src || 'canceled' === $status_src ) {
		if ( ! empty( $istnieje ) ) {
			wp_trash_post( (int) $istnieje[0] );
			return new WP_REST_Response( array( 'status' => 'cancelled', 'post_id' => (int) $istnieje[0] ), 200 );
		}
		return new WP_REST_Response( array( 'status' => 'cancelled-unknown' ), 200 );
	}

	// --- UPDATE: wydarzenie już istnieje → zaktualizuj (organizator zmienił godzinę/miejsce/opis) --.
	// Wcześniej furtka była CREATE-ONLY: zmiany u źródła NIGDY nie docierały do WP (stała wersja na zawsze).
	if ( ! empty( $istnieje ) ) {
		$post_id  = (int) $istnieje[0];
		$przejete = (bool) get_post_meta( $post_id, '_pnb_locked', true );

		// Admin RĘCZNIE przejął to wydarzenie → chronimy jego TREŚĆ (tytuł+opis+kategoria), ale FAKTY
		// ze źródła (data/godzina/miejsce/cena/zwroty) DALEJ aktualizujemy — bo to fakty, nie edycja.
		// Odwołanie (cancelled) obsłużone wyżej PRZED lockiem = odwołane i tak trafia do kosza.
		if ( $przejete ) {
			// NIE ruszamy post_title / post_content (treść admina). Aktualizujemy tylko meta-fakty.
			pnb_rest_zapisz_pola( $post_id, $dane, $date, $time, $time_end, $place, $limit, $category, true );
			return new WP_REST_Response( array( 'status' => 'locked-facts-updated', 'post_id' => $post_id ), 200 );
		}

		$upd = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $description,
				'post_status'  => 'publish', // gdyby było w trash (re-aktywacja odwołanego), wróć do publish
			),
			true
		);
		if ( is_wp_error( $upd ) ) {
			return new WP_Error( 'pnb_update_padl', 'Nie udało się zaktualizować wydarzenia.', array( 'status' => 500 ) );
		}
		pnb_rest_zapisz_pola( $post_id, $dane, $date, $time, $time_end, $place, $limit, $category );
		if ( function_exists( 'pnb_pl_auto_po_zapisie' ) ) {
			pnb_pl_auto_po_zapisie( get_post( $post_id ) );
		}
		return new WP_REST_Response( array( 'status' => 'updated', 'post_id' => $post_id ), 200 );
	}

	// --- CREATE: nowe wydarzenie -----------------------------------------.
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'pnb_wydarzenie',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $description,
			'post_author'  => 0,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'pnb_zapis_padl', 'Nie udało się zapisać wydarzenia.', array( 'status' => 500 ) );
	}
	update_post_meta( $post_id, '_pnb_source_id', $source_id );
	pnb_rest_zapisz_pola( $post_id, $dane, $date, $time, $time_end, $place, $limit, $category );

	if ( function_exists( 'pnb_pl_auto_po_zapisie' ) ) {
		pnb_pl_auto_po_zapisie( get_post( $post_id ) );
	}

	return new WP_REST_Response(
		array(
			'status'  => 'created',
			'post_id' => (int) $post_id,
		),
		201
	);
}

/**
 * Zapisuje meta + zdjęcie wydarzenia. Wspólne dla CREATE i UPDATE (żeby zmiany u źródła
 * docierały do WP tą samą ścieżką co pierwszy zapis). Wszystkie wartości walidowane whitelistą.
 */
function pnb_rest_zapisz_pola( $post_id, $dane, $date, $time, $time_end, $place, $limit, $category, $tylko_fakty = false ) {
	// $tylko_fakty=true (wydarzenie przejęte przez admina) → aktualizujemy fakty ze źródła (data/miejsce/
	// cena/zwroty), ale NIE ruszamy zdjęcia (admin mógł je świadomie zmienić/usunąć).
	// Link do oryginału (Eventbrite) — front pokaże „Zobacz na Eventbrite" zamiast formularza zapisu.
	$source_url = isset( $dane['source_url'] ) ? esc_url_raw( wp_unslash( $dane['source_url'] ) ) : '';
	if ( $source_url ) {
		update_post_meta( $post_id, '_pnb_source_url', $source_url );
	}
	if ( ! empty( $dane['address'] ) ) {
		update_post_meta( $post_id, '_pnb_event_address', sanitize_text_field( wp_unslash( $dane['address'] ) ) );
	}
	if ( ! empty( $dane['lat'] ) && ! empty( $dane['lng'] ) ) {
		update_post_meta( $post_id, '_pnb_event_lat', (float) $dane['lat'] );
		update_post_meta( $post_id, '_pnb_event_lng', (float) $dane['lng'] );
	}
	if ( ! empty( $dane['price'] ) ) {
		update_post_meta( $post_id, '_pnb_event_price', sanitize_text_field( wp_unslash( $dane['price'] ) ) );
	}
	// Refund: whitelist (jak w edytorze ręcznym kalendarz.php) — furtka NIE zapisuje surowych śmieci.
	if ( ! empty( $dane['refund'] ) ) {
		$refund_ok = array( 'no_refunds', 'refund_30', 'refund_7', 'refund_1' );
		$refund    = sanitize_key( $dane['refund'] );
		if ( in_array( $refund, $refund_ok, true ) ) {
			update_post_meta( $post_id, '_pnb_event_refund', $refund );
		}
	}
	if ( ! empty( $dane['highlights'] ) && is_array( $dane['highlights'] ) ) {
		// Whitelist kluczy ORAZ wartości (age/parking/location_type — inaczej strona ich nie wyświetli).
		$age_ok  = array( 'all_ages', 'under_16_with_guardian', 'under_18_with_guardian', 'over_18', 'over_21' );
		$park_ok = array( 'free', 'paid', 'no' );
		$loc_ok  = array( 'in_person', 'online' );
		$src     = $dane['highlights'];
		$hl      = array();
		if ( ! empty( $src['age'] ) && in_array( $src['age'], $age_ok, true ) ) {
			$hl['age'] = $src['age'];
		}
		if ( ! empty( $src['parking'] ) && in_array( $src['parking'], $park_ok, true ) ) {
			$hl['parking'] = $src['parking'];
		}
		if ( ! empty( $src['location_type'] ) && in_array( $src['location_type'], $loc_ok, true ) ) {
			$hl['location_type'] = $src['location_type'];
		}
		if ( ! empty( $src['duration_min'] ) ) {
			$hl['duration_min'] = absint( $src['duration_min'] );
		}
		if ( ! empty( $src['door_time'] ) ) {
			$hl['door_time'] = sanitize_text_field( (string) $src['door_time'] );
		}
		if ( $hl ) {
			update_post_meta( $post_id, '_pnb_event_highlights', $hl );
		}
	}

	update_post_meta( $post_id, '_pnb_event_date', $date );
	update_post_meta( $post_id, '_pnb_event_time', $time );
	update_post_meta( $post_id, '_pnb_event_time_end', $time_end );
	update_post_meta( $post_id, '_pnb_event_place', $place );
	update_post_meta( $post_id, '_pnb_event_limit', $limit );
	update_post_meta( $post_id, '_pnb_event_cat', $category );

	// Zdjęcie (opcjonalne). NIE ruszamy gdy: (a) przejęte przez admina (tylko_fakty), (b) admin świadomie
	// usunął zdjęcie (_pnb_img_removed — inaczej scraper by je przywracał w kółko), (c) już ma miniaturę.
	if ( ! $tylko_fakty
		&& ! get_post_meta( $post_id, '_pnb_img_removed', true )
		&& isset( $dane['image_url'] ) && '' !== trim( (string) $dane['image_url'] )
		&& ! has_post_thumbnail( $post_id ) ) {
		$att = pnb_rest_pobierz_obrazek( (string) $dane['image_url'], $post_id );
		if ( $att ) {
			set_post_thumbnail( $post_id, $att );
		}
	}
}

/**
 * Walidacja daty: dokładny format Y-m-d ORAZ realna data w kalendarzu.
 *
 * @param string $date Kandydat na datę.
 * @return bool
 */
function pnb_rest_data_ok( $date ) {
	$d = DateTime::createFromFormat( 'Y-m-d', $date );
	return $d && $d->format( 'Y-m-d' ) === $date;
}

/**
 * Pobiera obrazek z zewnętrznego URL do Media Library i zwraca ID załącznika.
 *
 * Bezpieczeństwo: URL pochodzi ze scrapowanej (obcej) strony, więc nie ufamy mu.
 * - esc_url_raw + wymóg http(s) i rozszerzenia graficznego,
 * - download_url (przez media_sideload_image) używa wp_safe_remote_get → blokuje adresy lokalne (anty-SSRF),
 * - dedup po URL (_pnb_original_img_url): ten sam obrazek nie pobiera się dwa razy,
 * - błąd/martwy link → zwraca 0, wydarzenie zostaje bez zdjęcia (nie wywala furtki).
 *
 * @param string $url     URL obrazka ze źródła.
 * @param int    $post_id Post do którego dowiązać załącznik.
 * @return int ID załącznika lub 0 gdy się nie udało.
 */
function pnb_rest_pobierz_obrazek( $url, $post_id ) {
	$url = esc_url_raw( trim( $url ) );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return 0;
	}
	// NIE wymagamy rozszerzenia w URL — nowoczesne CDN (Eventbrite img.evbuc.com)
	// serwują obrazek bez rozszerzenia (auto-format). Realną ochronę przejmuje
	// wp_check_filetype_and_ext() wewnątrz media_handle_sideload (sprawdza treść
	// pliku PO pobraniu, po magic-bytes) + wp_attachment_is_image() niżej.
	// wp_safe_remote_get w download_url blokuje adresy lokalne/prywatne (anty-SSRF).

	// Dedup: czy ten URL już pobrany jako załącznik?
	$juz = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_pnb_original_img_url',
					'value' => $url,
				),
			),
		)
	);
	if ( ! empty( $juz ) ) {
		return (int) $juz[0];
	}

	// Funkcje media dostępne tylko po załadowaniu plików admina.
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// NIE używamy media_sideload_image — ono wnioskuje nazwę pliku z URL, a CDN typu
	// Eventbrite (img.evbuc.com/https%3A%2F%2F...) daje URL z zagnieżdżonym adresem bez
	// czystego rozszerzenia → WP odrzuca ("Invalid image URL") mimo że plik to poprawny JPEG.
	// Zamiast tego: pobieramy sami (download_url) i nadajemy WŁASNĄ nazwę z .jpg,
	// potem media_handle_sideload z jawną nazwą.

	// Krótszy timeout niż domyślne 300s — furtka synchroniczna, nie może wisieć.
	$skroc = function ( $r ) {
		$r['timeout'] = 20;
		return $r;
	};
	add_filter( 'http_request_args', $skroc );
	$tmp = download_url( $url, 20 );
	remove_filter( 'http_request_args', $skroc );

	if ( is_wp_error( $tmp ) ) {
		return 0;
	}

	// Sprawdź realny typ pobranego pliku (magic-bytes) — bezpieczeństwo, nie ufamy URL.
	$typ = wp_check_filetype_and_ext( $tmp, 'obraz.jpg' );
	$mime = (string) ( $typ['type'] ?? '' );
	if ( 0 !== strpos( $mime, 'image/' ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	// Nadaj czystą nazwę z rozszerzeniem pasującym do realnego typu.
	$ext  = (string) ( $typ['ext'] ?: 'jpg' );
	$file = array(
		'name'     => 'eventbrite-' . wp_generate_password( 8, false ) . '.' . $ext,
		'tmp_name' => $tmp,
	);
	$att = media_handle_sideload( $file, $post_id );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return 0;
	}

	// Defense-in-depth: upewnij się że to obraz (a nie inny dozwolony typ).
	if ( ! wp_attachment_is_image( (int) $att ) ) {
		wp_delete_attachment( (int) $att, true );
		return 0;
	}

	update_post_meta( (int) $att, '_pnb_original_img_url', $url );
	return (int) $att;
}
