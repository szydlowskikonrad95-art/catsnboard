<?php
/**
 * Importer wydarzeń — automat W PLUGINIE (zastępuje osobny scraper Python).
 *
 * Czyta wydarzenia z Eventbrite (ukryte API JSON), normalizuje, dodaje/aktualizuje jako CPT
 * pnb_wydarzenie — bezpośrednio, bez furtki REST/hasła/config.env. Odpala się przez WP-Cron
 * co interwał (domyślnie 10 min). Cała logika przeniesiona 1:1 ze scrapera Python.
 *
 * Dlaczego w pluginie a nie Python: klient ma shared hosting (LiteSpeed), nie ma gdzie chodzić
 * proces Python. Eventbrite to zwykłe JSON API (bez przeglądarki), więc PHP (wp_remote_get) wystarcza.
 * Potwierdzone w docs WP + jak robią to Feedzy/WP RSS Aggregator (WP-Cron dla importu feedów).
 *
 * @package pnb-blocks
 */

defined( 'ABSPATH' ) || exit;

// Konfiguracja (klient ustawia SOURCE_URL w panelu; reszta stała).
const PNB_IMP_API_EVENTS = 'https://www.eventbrite.com/api/v3/destination/events/';
const PNB_IMP_EXPAND     = 'image,primary_venue,primary_organizer,ticket_availability';
const PNB_IMP_BATCH       = 20;   // ile ID na jedno zapytanie do API
const PNB_IMP_MAX_NEW     = 5;    // ile NOWYCH dodać w jednym cyklu (nie zalewać)
const PNB_IMP_UA          = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

/* ============================ WP-CRON: harmonogram ============================ */

// Własny interwał „co 10 minut".
add_filter( 'cron_schedules', function ( $sched ) {
	$sched['pnb_co_10_min'] = array( 'interval' => 600, 'display' => __( 'Every 10 minutes (Pawsnboard importer)', 'pnb-toolkit' ) );
	return $sched;
} );

// Zaplanuj przy aktywacji, usuń przy dezaktywacji (wołane z pnb-blocks.php).
function pnb_importer_zaplanuj() {
	if ( ! wp_next_scheduled( 'pnb_importer_cykl' ) ) {
		wp_schedule_event( time() + 60, 'pnb_co_10_min', 'pnb_importer_cykl' );
	}
}
function pnb_importer_odplanuj() {
	$ts = wp_next_scheduled( 'pnb_importer_cykl' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'pnb_importer_cykl' );
	}
}

// Hook cronu → jeden cykl.
add_action( 'pnb_importer_cykl', 'pnb_importer_jeden_cykl' );

/* ============================ GŁÓWNY CYKL ============================ */

/**
 * Jeden przebieg: czytaj źródło → dedup → dodaj/aktualizuj → wygasanie → metryki.
 * Odporny: błąd źródła/sieci nie wywala (łapiemy, spróbujemy w następnym cyklu).
 */
function pnb_importer_jeden_cykl() {
	$source = trim( (string) get_option( 'pnb_importer_source_url', '' ) );
	if ( '' === $source ) {
		return; // klient nie ustawił źródła — nic nie robimy (nie błąd)
	}

	// CIRCUIT BREAKER: jeśli źródło padało pod rząd (np. Eventbrite wywala 500/ban), przestajemy walić
	// co 10 min — odczekujemy dłużej z każdą porażką. Chroni przed łomotaniem martwego/blokującego źródła.
	$breaker_do = (int) get_option( 'pnb_importer_breaker_do', 0 );
	if ( $breaker_do > time() ) {
		pnb_importer_log( sprintf( '⏸️ Circuit breaker: źródło padało — czekam jeszcze %d min zanim spróbuję.',
			(int) ceil( ( $breaker_do - time() ) / 60 ) ) );
		return;
	}

	$stat = array( 'pobrane' => 0, 'nowe' => 0, 'dodane' => 0, 'zaktualizowane' => 0, 'bledy' => 0, 'wygasle' => 0 );

	// 1. Czytaj CAŁE źródło (lekko — bez dociągania pełnych danych; pełne tylko dla nowych niżej).
	$wydarzenia = pnb_importer_pobierz_wydarzenia( $source, false );
	if ( is_wp_error( $wydarzenia ) ) {
		// Porażka źródła → zwiększ licznik i otwórz breaker na coraz dłużej (10→20→40→max 120 min).
		$porazki = (int) get_option( 'pnb_importer_porazki', 0 ) + 1;
		update_option( 'pnb_importer_porazki', $porazki );
		$przerwa = min( 120, 10 * pow( 2, min( $porazki, 4 ) ) ); // 20,40,80,120,120 min
		update_option( 'pnb_importer_breaker_do', time() + $przerwa * 60 );
		pnb_importer_log( sprintf( 'Źródło nie odpowiada (%d. raz): %s — breaker na %d min.',
			$porazki, $wydarzenia->get_error_message(), $przerwa ) );
		return;
	}
	$stat['pobrane'] = count( $wydarzenia );

	// ALERT 0: pusto = możliwa awaria/zmiana źródła/ban. NIE czyścimy strony (0 ≠ „wszystko usunięte").
	// 0 wyników pod rząd też otwiera breaker (jeśli API się zmieniło, nie wal co 10 min bez sensu).
	// UWAGA: reset breakera robimy DOPIERO gdy realnie mamy wyniki (niżej) — nie tutaj, bo pusto to porażka.
	if ( empty( $wydarzenia ) ) {
		$porazki = (int) get_option( 'pnb_importer_porazki', 0 ) + 1;
		update_option( 'pnb_importer_porazki', $porazki );
		if ( $porazki >= 3 ) { // dopiero po 3 pustych z rzędu (jednorazowe puste = może chwilowe)
			$przerwa = min( 120, 10 * pow( 2, min( $porazki - 2, 4 ) ) );
			update_option( 'pnb_importer_breaker_do', time() + $przerwa * 60 );
		}
		pnb_importer_log( sprintf( '⚠️ Źródło zwróciło 0 wydarzeń (%d. raz) — możliwa zmiana API. NIE czyszczę strony.', $porazki ) );
		return;
	}

	// Realnie mamy wyniki → źródło zdrowe → reset breakera i licznika porażek.
	update_option( 'pnb_importer_porazki', 0 );
	update_option( 'pnb_importer_breaker_do', 0 );

	// PRÓG SPADKU: nagły spadek (22→3) = podejrzana zmiana API → alarm + pomiń wygasanie.
	$ostatnia = (int) get_option( 'pnb_importer_ostatnia_liczba', 0 );
	$podejrzany_spadek = ( $ostatnia >= 10 && count( $wydarzenia ) < $ostatnia * 0.3 );
	update_option( 'pnb_importer_ostatnia_liczba', count( $wydarzenia ) );

	// DEAD LETTER QUEUE: wydarzenia które padły ≥5× — pomijamy (nie próbujemy w kółko). Widoczne w panelu.
	$martwe = get_option( 'pnb_importer_dead', array() );
	if ( ! is_array( $martwe ) ) { $martwe = array(); }

	// 2+3. Dedup + zapis. Limit = ile NOWYCH na cykl (nie zalewać).
	foreach ( $wydarzenia as $w ) {
		$sid = $w['source_id'];
		// Dead Letter: to wydarzenie padło za dużo razy → nie próbujemy więcej (do resetu w panelu).
		if ( isset( $martwe[ $sid ] ) && $martwe[ $sid ]['proby'] >= 5 ) {
			continue;
		}

		$status = pnb_importer_status( $w );
		if ( 'znane' === $status ) {
			continue; // już jest, bez zmian
		}
		if ( 'nowe' === $status && $stat['nowe'] >= PNB_IMP_MAX_NEW ) {
			break; // limit nowych — resztę weźmiemy w następnym cyklu
		}

		// Dla NOWEGO dociągnij pełne dane (opis+galeria+good-to-know). Błąd → zostaje skrót.
		if ( 'nowe' === $status ) {
			$stat['nowe']++;
			pnb_importer_wzbogac( $w );
		}

		$wynik = pnb_importer_zapisz_wydarzenie( $w );
		if ( 'created' === $wynik ) {
			$stat['dodane']++;
			unset( $martwe[ $sid ] ); // sukces → wyczyść ewentualne wcześniejsze porażki
		} elseif ( in_array( $wynik, array( 'updated', 'locked-facts', 'exists' ), true ) ) {
			$stat['zaktualizowane']++;
			unset( $martwe[ $sid ] );
		} else {
			$stat['bledy']++;
			// Dead Letter: licz porażki. Po 5 → wydarzenie trafia do „martwych", nie próbujemy dalej.
			$proby = isset( $martwe[ $sid ] ) ? $martwe[ $sid ]['proby'] + 1 : 1;
			$martwe[ $sid ] = array( 'proby' => $proby, 'tytul' => $w['title'], 'kiedy' => current_time( 'mysql' ) );
			if ( $proby >= 5 ) {
				pnb_importer_log( sprintf( '☠️ Dead Letter: „%s" padło 5× — przestaję próbować (sprawdź w panelu).', $w['title'] ) );
			}
		}
	}
	update_option( 'pnb_importer_dead', $martwe, false );

	// WYGASANIE: wydarzenia zniknięte ze źródła → do kosza (pomijamy przy podejrzanym spadku).
	if ( ! $podejrzany_spadek ) {
		$aktualne = array();
		foreach ( $wydarzenia as $w ) {
			if ( ! empty( $w['source_id'] ) ) {
				$aktualne[] = $w['source_id'];
			}
		}
		$stat['wygasle'] = pnb_importer_sprzataj_wygasle( $aktualne );
	}

	// MONITORING: zapisz metryki (ekran stanu w panelu + żółty pasek gdy stanie).
	update_option( 'pnb_scraper_status', array(
		'ostatni_sync' => current_time( 'mysql' ),
		'pobrane'      => $stat['pobrane'],
		'nowe'         => $stat['nowe'],
		'wyslane'      => $stat['dodane'],
		'juz_jest'     => $stat['zaktualizowane'],
		'odrzucone'    => 0,
		'bledy'        => $stat['bledy'],
		'wygasle'      => $stat['wygasle'],
		'spadek_alert' => $podejrzany_spadek,
		'zapisano'     => current_time( 'mysql' ),
	) );

	pnb_importer_log( sprintf(
		'Cykl: pobrane=%d nowe=%d dodane=%d zaktualizowane=%d wygasłe=%d błędy=%d',
		$stat['pobrane'], $stat['nowe'], $stat['dodane'], $stat['zaktualizowane'], $stat['wygasle'], $stat['bledy']
	) );
}

/* ============================ POBIERANIE ZE ŹRÓDŁA ============================ */

/**
 * Pobiera wydarzenia z Eventbrite: listing → event_ids → szczegóły z API → nasz format.
 * @param string $listing_url URL listingu (kocie wydarzenia).
 * @param bool   $pelne       Czy dociągać pełny opis+galerię (dla nowych). Tu domyślnie false.
 * @return array|WP_Error Lista wydarzeń w naszym formacie, albo WP_Error przy awarii źródła.
 */
function pnb_importer_pobierz_wydarzenia( $listing_url, $pelne = false ) {
	$ids = pnb_importer_event_ids( $listing_url );
	if ( is_wp_error( $ids ) ) {
		return $ids;
	}
	if ( empty( $ids ) ) {
		return array();
	}
	$surowe = pnb_importer_szczegoly( $ids );
	if ( is_wp_error( $surowe ) ) {
		return $surowe;
	}
	$out = array();
	foreach ( $surowe as $ev ) {
		$nasz = pnb_importer_na_format( $ev );
		if ( null === $nasz ) {
			continue;
		}
		if ( $pelne ) {
			pnb_importer_wzbogac( $nasz );
		}
		$out[] = $nasz;
	}
	return $out;
}

/** Pobiera stronę listingu i wyciąga unikalne event_ids (kolejność zachowana). */
function pnb_importer_event_ids( $listing_url ) {
	$r = pnb_importer_get( $listing_url );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$html = wp_remote_retrieve_body( $r );
	$out  = array();
	$seen = array();
	if ( preg_match_all( '/"eventbrite_event_id":"?(\d{10,})"?/', $html, $m1 ) ) {
		foreach ( $m1[1] as $id ) {
			if ( ! isset( $seen[ $id ] ) ) { $seen[ $id ] = 1; $out[] = $id; }
		}
	}
	if ( preg_match_all( '#/e/[a-z0-9-]+-tickets-(\d{10,})#', $html, $m2 ) ) {
		foreach ( $m2[1] as $id ) {
			if ( ! isset( $seen[ $id ] ) ) { $seen[ $id ] = 1; $out[] = $id; }
		}
	}
	return $out;
}

/** Pobiera pełne dane wydarzeń z ukrytego API, paczkami po PNB_IMP_BATCH. Grzecznie (pauza). */
function pnb_importer_szczegoly( $event_ids ) {
	$wynik = array();
	foreach ( array_chunk( $event_ids, PNB_IMP_BATCH ) as $paczka ) {
		$url = add_query_arg( array(
			'event_ids' => implode( ',', $paczka ),
			'expand'    => PNB_IMP_EXPAND,
			'page_size' => 50,
		), PNB_IMP_API_EVENTS );
		$r = pnb_importer_get( $url );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$dane = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $dane ) || ! isset( $dane['events'] ) ) {
			return new WP_Error( 'pnb_zly_json', 'Zła odpowiedź JSON z API.' );
		}
		$wynik = array_merge( $wynik, $dane['events'] );
		// Grzeczność: pauza między paczkami (nie łomotać). Krótka — cron ma limit czasu PHP.
		if ( count( $event_ids ) > PNB_IMP_BATCH ) {
			sleep( 1 );
		}
	}
	return $wynik;
}

/** Jedno grzeczne zapytanie HTTP z nagłówkami przeglądarki i timeoutem 20s. */
function pnb_importer_get( $url ) {
	$r = wp_remote_get( $url, array(
		'timeout'    => 20,
		'headers'    => array( 'User-Agent' => PNB_IMP_UA, 'Accept' => 'application/json' ),
		'user-agent' => PNB_IMP_UA,
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$kod = wp_remote_retrieve_response_code( $r );
	if ( 200 !== (int) $kod ) {
		return new WP_Error( 'pnb_http', "HTTP $kod z źródła." );
	}
	return $r;
}

/* ============================ NORMALIZACJA (1:1 z Python) ============================ */

/** Surowe wydarzenie Eventbrite → nasz format. null gdy bez sensownych danych (pomijamy). */
function pnb_importer_na_format( $ev ) {
	$source_id = (string) ( $ev['id'] ?? $ev['eventbrite_event_id'] ?? '' );
	$title     = trim( (string) ( $ev['name'] ?? '' ) );
	$date      = trim( (string) ( $ev['start_date'] ?? '' ) );
	if ( '' === $source_id || '' === $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return null; // brak wymaganych → pomiń, nie wrzucaj śmieci
	}

	$venue   = is_array( $ev['primary_venue'] ?? null ) ? $ev['primary_venue'] : array();
	$miejsce = trim( (string) ( $venue['name'] ?? '' ) );
	$adres   = '';
	$lat     = '';
	$lng     = '';
	if ( is_array( $venue['address'] ?? null ) ) {
		$a      = $venue['address'];
		$czesci = array_filter( array( $a['address_1'] ?? '', $a['city'] ?? '', $a['region'] ?? '', $a['postal_code'] ?? '' ) );
		$adres  = trim( implode( ', ', $czesci ), ', ' );
		$lat    = (string) ( $a['latitude'] ?? '' );
		$lng    = (string) ( $a['longitude'] ?? '' );
	}
	if ( '' === $miejsce ) {
		$miejsce = ! empty( $ev['is_online_event'] ) ? 'Online' : "Cats'N'Board";
	}

	$obraz     = is_array( $ev['image'] ?? null ) ? $ev['image'] : array();
	$image_url = (string) ( $obraz['url'] ?? '' );

	// Cena z ticket_availability.
	$cena = '';
	$ta   = is_array( $ev['ticket_availability'] ?? null ) ? $ev['ticket_availability'] : array();
	if ( ! empty( $ta['is_free'] ) ) {
		$cena = 'free';
	} elseif ( is_array( $ta['minimum_ticket_price'] ?? null ) ) {
		$cena = trim( (string) ( $ta['minimum_ticket_price']['display'] ?? '' ) );
	}

	return array(
		'source_id'   => 'eventbrite-' . $source_id,
		'source_raw'  => $source_id, // do dociągania pełnej strony
		'title'       => pnb_importer_norm_tytul( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
		'date'        => $date,
		'time'        => trim( (string) ( $ev['start_time'] ?? '' ) ),
		'time_end'    => trim( (string) ( $ev['end_time'] ?? '' ) ),
		'place'       => html_entity_decode( $miejsce, ENT_QUOTES, 'UTF-8' ),
		'description' => html_entity_decode( trim( (string) ( $ev['summary'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ),
		'image_url'   => $image_url,
		'source_url'  => trim( (string) ( $ev['url'] ?? '' ) ),
		'address'     => $adres,
		'lat'         => $lat,
		'lng'         => $lng,
		'price'       => $cena,
		'category'    => 'other',
		'highlights'  => array(),
		'refund'      => '',
	);
}

/** Title Case TYLKO gdy tytuł CAŁY WIELKIMI (chroni skróty NYC/DJ i marki iPhone). 1:1 z Python. */
function pnb_importer_norm_tytul( $tytul ) {
	$tytul = trim( $tytul );
	if ( '' === $tytul ) {
		return $tytul;
	}
	$litery = preg_replace( '/[^\p{L}]/u', '', $tytul );
	if ( '' === $litery || mb_strtoupper( $litery, 'UTF-8' ) !== $litery ) {
		return $tytul; // nie całe wielkimi → normalny tytuł, nie ruszamy
	}
	$male  = array( 'a', 'an', 'the', 'and', 'or', 'but', 'for', 'nor', 'on', 'at', 'to', 'by', 'of', 'in', 'with', 'as', 'vs' );
	$slowa = explode( ' ', $tytul );
	$n     = count( $slowa );
	$out   = array();
	foreach ( $slowa as $i => $s ) {
		$rdzen = mb_strtolower( $s, 'UTF-8' );
		if ( $i > 0 && $i < $n - 1 && in_array( $rdzen, $male, true ) ) {
			$out[] = $rdzen;
		} else {
			$czesci = array_map(
				function ( $c ) { return mb_convert_case( $c, MB_CASE_TITLE, 'UTF-8' ); },
				explode( '-', $rdzen )
			);
			$out[] = implode( '-', $czesci );
		}
	}
	return implode( ' ', $out );
}

/** Wiek Eventbrite → klucz whitelisty strony. 1:1 z Python. */
function pnb_importer_mapuj_wiek( $raw ) {
	$t    = strtolower( trim( (string) $raw ) );
	$mapa = array(
		'all_ages' => 'all_ages', 'all ages' => 'all_ages',
		'18+' => 'over_18', 'over_18' => 'over_18', '18 and over' => 'over_18',
		'21+' => 'over_21', 'over_21' => 'over_21', '21 and over' => 'over_21',
		'under_16_with_guardian' => 'under_16_with_guardian',
		'under_18_with_guardian' => 'under_18_with_guardian',
	);
	return $mapa[ $t ] ?? '';
}

/** Refund Eventbrite → klucz whitelisty. 'custom'+validDays → najbliższy bucket. 1:1 z Python. */
function pnb_importer_mapuj_refund( $policy_type, $valid_days ) {
	$t     = strtolower( trim( (string) $policy_type ) );
	$znane = array( 'no_refunds', 'refund_30', 'refund_7', 'refund_1' );
	if ( in_array( $t, $znane, true ) ) {
		return $t;
	}
	if ( 'custom' === $t || '' === $t ) {
		$d = is_numeric( $valid_days ) ? (int) $valid_days : null;
		if ( null !== $d ) {
			if ( $d >= 30 ) { return 'refund_30'; }
			if ( $d >= 7 ) { return 'refund_7'; }
			if ( $d >= 1 ) { return 'refund_1'; }
		}
	}
	return '';
}

/** Czyści opis Eventbrite zachowując akapity i klikalne linki. 1:1 z Python. */
function pnb_importer_oczysc_opis( $html_opis ) {
	// <a href="X">tekst</a> → zostaw, napraw URL bez protokołu.
	$t = preg_replace_callback(
		'#<a\s+[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is',
		function ( $m ) {
			$url   = trim( $m[1] );
			$tekst = trim( $m[2] );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				$url = 'https://' . ltrim( $url, '/' );
			}
			return '<a href="' . $url . '" target="_blank" rel="noopener">' . ( $tekst ?: $url ) . '</a>';
		},
		$html_opis
	);
	$t = preg_replace( '#</p>\s*<p>#i', "\n\n", $t );
	$t = preg_replace( '#<br\s*/?>#i', "\n", $t );
	$t = preg_replace( '#</?p[^>]*>#i', "\n", $t );
	$t = preg_replace( '#<(?!/?a[\s>])[^>]+>#i', '', $t ); // usuń tagi oprócz <a>
	$t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
	$t = preg_replace( '#[ \t]+#', ' ', $t );
	$t = preg_replace( "#\n\s*\n\s*\n+#", "\n\n", $t );
	return trim( $t );
}

/** Dociąga pełny opis + galerię + good-to-know ze strony wydarzenia (__NEXT_DATA__). Wzbogaca W MIEJSCU. */
function pnb_importer_wzbogac( &$nasz ) {
	$sid = $nasz['source_raw'] ?? str_replace( 'eventbrite-', '', $nasz['source_id'] );
	$r   = wp_remote_get( "https://www.eventbrite.com/e/$sid", array(
		'timeout'    => 20,
		'user-agent' => PNB_IMP_UA,
	) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return; // nie udało się → zostaje skrót, nie wywala
	}
	$html = wp_remote_retrieve_body( $r );
	if ( ! preg_match( '#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m ) ) {
		return;
	}
	$data = json_decode( $m[1], true );
	$ctx  = $data['props']['pageProps']['context'] ?? null;
	if ( ! is_array( $ctx ) ) {
		return;
	}

	// Pełny opis.
	$opis_html = '';
	foreach ( ( $ctx['structuredContent']['modules'] ?? array() ) as $mod ) {
		if ( is_array( $mod ) && ! empty( $mod['text'] ) ) {
			$opis_html .= $mod['text'];
		}
	}
	if ( '' !== $opis_html ) {
		$czysty = pnb_importer_oczysc_opis( $opis_html );
		if ( mb_strlen( $czysty ) > mb_strlen( $nasz['description'] ) ) {
			$nasz['description'] = $czysty;
		}
	}

	// Galeria (pierwsze = główne zdjęcie).
	$obrazy = array();
	foreach ( ( $ctx['gallery']['images'] ?? array() ) as $img ) {
		if ( is_array( $img ) ) {
			$u = $img['url'] ?? $img['croppedLogoUrl940'] ?? $img['croppedLogoUrl600'] ?? '';
			if ( $u ) { $obrazy[] = $u; }
		}
	}
	if ( $obrazy ) {
		$nasz['image_url'] = $obrazy[0];
	}

	// Good to know + refund.
	$gtk = $ctx['goodToKnow'] ?? array();
	$h   = $gtk['highlights'] ?? array();
	if ( is_array( $h ) ) {
		$nasz['highlights'] = array(
			'age'           => pnb_importer_mapuj_wiek( $h['ageRestriction'] ?? '' ),
			'parking'       => $h['parking'] ?? '',
			'door_time'     => $h['doorTime'] ?? '',
			'location_type' => $h['locationType'] ?? '',
			'duration_min'  => $h['durationInMinutes'] ?? 0,
		);
	}
	if ( is_array( $gtk['refundPolicy'] ?? null ) ) {
		$nasz['refund'] = pnb_importer_mapuj_refund(
			$gtk['refundPolicy']['policyType'] ?? '',
			$gtk['refundPolicy']['validDays'] ?? null
		);
	}
}

/* ============================ DEDUP + ZAPIS (postmeta zamiast SQLite) ============================ */

/**
 * Znajduje istniejące wydarzenie: NAJPIERW po source_id, a jak nie ma — FALLBACK po hashu pól stałych
 * (title+date+place). Fallback ratuje gdy źródło zmieni source_id dla tego samego wydarzenia — inaczej
 * powstałby duplikat. Zwraca ID posta albo 0.
 */
function pnb_importer_znajdz( $w ) {
	// 1. Po source_id (najpewniej).
	$po_id = get_posts( array(
		'post_type'      => 'pnb_wydarzenie',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'meta_query'     => array( array( 'key' => '_pnb_source_id', 'value' => $w['source_id'] ) ), // phpcs:ignore
	) );
	if ( ! empty( $po_id ) ) {
		return (int) $po_id[0];
	}
	// 2. FALLBACK po hashu (to samo wydarzenie, zmieniony source_id).
	$po_hash = get_posts( array(
		'post_type'      => 'pnb_wydarzenie',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'meta_query'     => array( array( 'key' => '_pnb_hash_pol', 'value' => pnb_importer_hash( $w ) ) ), // phpcs:ignore
	) );
	if ( ! empty( $po_hash ) ) {
		// zaktualizuj source_id na nowy (żeby następnym razem trafić od razu po ID)
		update_post_meta( (int) $po_hash[0], '_pnb_source_id', $w['source_id'] );
		return (int) $po_hash[0];
	}
	return 0;
}

/** Status wydarzenia: 'nowe' | 'znane' | 'zmienione'. Dedup po source_id, fallback po hashu. */
function pnb_importer_status( $w ) {
	$id = pnb_importer_znajdz( $w );
	if ( ! $id ) {
		return 'nowe';
	}
	$hash_stary = get_post_meta( $id, '_pnb_hash_pol', true );
	$hash_nowy  = pnb_importer_hash( $w );
	return ( $hash_stary === $hash_nowy ) ? 'znane' : 'zmienione';
}

/** Hash z PÓL STAŁYCH (title+date+place) — wykrywa zmianę, odporny na zmienne śmieci. 1:1 z Python. */
function pnb_importer_hash( $w ) {
	$baza = strtolower( trim( $w['title'] ) ) . '|' . trim( $w['date'] ) . '|' . strtolower( trim( $w['place'] ) );
	return hash( 'sha256', $baza );
}

/**
 * Zapisuje wydarzenie do WP (create lub update). Bezpośrednio wp_insert_post/wp_update_post —
 * bez furtki REST. Cała logika lock/pola/zdjęcia z furtki, przeniesiona tutaj.
 * @return string 'created' | 'updated' | 'locked-facts' | 'error'
 */
function pnb_importer_zapisz_wydarzenie( $w ) {
	// Fallback dedup: znajdź po source_id LUB hashu (jak source_id się zmienił u źródła).
	$znaleziony = pnb_importer_znajdz( $w );
	$istnieje   = $znaleziony ? array( $znaleziony ) : array();

	$description = wp_kses_post( $w['description'] );

	// UPDATE.
	if ( ! empty( $istnieje ) ) {
		$post_id  = (int) $istnieje[0];
		$przejete = (bool) get_post_meta( $post_id, '_pnb_locked', true );
		if ( $przejete ) {
			// Chronimy treść admina — aktualizujemy tylko fakty (data/miejsce/cena).
			pnb_importer_zapisz_pola( $post_id, $w, true );
			update_post_meta( $post_id, '_pnb_hash_pol', pnb_importer_hash( $w ) );
			return 'locked-facts';
		}
		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => $w['title'],
			'post_content' => $description,
			'post_status'  => 'publish',
		) );
		pnb_importer_zapisz_pola( $post_id, $w, false );
		update_post_meta( $post_id, '_pnb_hash_pol', pnb_importer_hash( $w ) );
		if ( function_exists( 'pnb_pl_auto_po_zapisie' ) ) {
			pnb_pl_auto_po_zapisie( get_post( $post_id ) );
		}
		return 'updated';
	}

	// CREATE.
	$post_id = wp_insert_post( array(
		'post_type'    => 'pnb_wydarzenie',
		'post_status'  => 'publish',
		'post_title'   => $w['title'],
		'post_content' => $description,
		'post_author'  => 0,
	), true );
	if ( is_wp_error( $post_id ) ) {
		return 'error';
	}
	update_post_meta( $post_id, '_pnb_source_id', $w['source_id'] );
	pnb_importer_zapisz_pola( $post_id, $w, false );
	update_post_meta( $post_id, '_pnb_hash_pol', pnb_importer_hash( $w ) );
	if ( function_exists( 'pnb_pl_auto_po_zapisie' ) ) {
		pnb_pl_auto_po_zapisie( get_post( $post_id ) );
	}
	return 'created';
}

/** Zapisuje meta + zdjęcie. $tylko_fakty=true (przejęte) → nie rusza zdjęcia. Whitelisty jak w furtce. */
function pnb_importer_zapisz_pola( $post_id, $w, $tylko_fakty = false ) {
	if ( ! empty( $w['source_url'] ) ) {
		update_post_meta( $post_id, '_pnb_source_url', esc_url_raw( $w['source_url'] ) );
	}
	if ( ! empty( $w['address'] ) ) {
		update_post_meta( $post_id, '_pnb_event_address', sanitize_text_field( $w['address'] ) );
	}
	if ( ! empty( $w['lat'] ) && ! empty( $w['lng'] ) ) {
		update_post_meta( $post_id, '_pnb_event_lat', (float) $w['lat'] );
		update_post_meta( $post_id, '_pnb_event_lng', (float) $w['lng'] );
	}
	if ( ! empty( $w['price'] ) ) {
		update_post_meta( $post_id, '_pnb_event_price', sanitize_text_field( $w['price'] ) );
	}
	if ( ! empty( $w['refund'] ) ) {
		$refund_ok = array( 'no_refunds', 'refund_30', 'refund_7', 'refund_1' );
		if ( in_array( $w['refund'], $refund_ok, true ) ) {
			update_post_meta( $post_id, '_pnb_event_refund', $w['refund'] );
		}
	}
	if ( ! empty( $w['highlights'] ) && is_array( $w['highlights'] ) ) {
		$age_ok  = array( 'all_ages', 'under_16_with_guardian', 'under_18_with_guardian', 'over_18', 'over_21' );
		$park_ok = array( 'free', 'paid', 'no' );
		$loc_ok  = array( 'in_person', 'online' );
		$src     = $w['highlights'];
		$hl      = array();
		if ( ! empty( $src['age'] ) && in_array( $src['age'], $age_ok, true ) ) { $hl['age'] = $src['age']; }
		if ( ! empty( $src['parking'] ) && in_array( $src['parking'], $park_ok, true ) ) { $hl['parking'] = $src['parking']; }
		if ( ! empty( $src['location_type'] ) && in_array( $src['location_type'], $loc_ok, true ) ) { $hl['location_type'] = $src['location_type']; }
		if ( ! empty( $src['duration_min'] ) ) { $hl['duration_min'] = absint( $src['duration_min'] ); }
		if ( ! empty( $src['door_time'] ) ) { $hl['door_time'] = sanitize_text_field( (string) $src['door_time'] ); }
		if ( $hl ) { update_post_meta( $post_id, '_pnb_event_highlights', $hl ); }
	}

	update_post_meta( $post_id, '_pnb_event_date', $w['date'] );
	update_post_meta( $post_id, '_pnb_event_time', sanitize_text_field( $w['time'] ) );
	update_post_meta( $post_id, '_pnb_event_time_end', sanitize_text_field( $w['time_end'] ) );
	update_post_meta( $post_id, '_pnb_event_place', sanitize_text_field( $w['place'] ) );
	update_post_meta( $post_id, '_pnb_event_cat', 'other' );

	// Zdjęcie: nie ruszamy przy locku ani gdy admin usunął (img_removed) ani gdy już jest.
	if ( ! $tylko_fakty
		&& ! get_post_meta( $post_id, '_pnb_img_removed', true )
		&& ! empty( $w['image_url'] )
		&& ! has_post_thumbnail( $post_id )
		&& function_exists( 'pnb_rest_pobierz_obrazek' ) ) {
		$att = pnb_rest_pobierz_obrazek( $w['image_url'], $post_id );
		if ( $att ) {
			set_post_thumbnail( $post_id, $att );
		}
	}
}

/** Wygasanie: importowane wydarzenia spoza listy aktualnych → do kosza. Pomija _pnb_locked. */
function pnb_importer_sprzataj_wygasle( $aktualne ) {
	if ( empty( $aktualne ) ) {
		return 0;
	}
	$importowane = get_posts( array(
		'post_type'      => 'pnb_wydarzenie',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_pnb_source_id', 'compare' => 'EXISTS' ) ), // phpcs:ignore
	) );
	$set      = array_flip( $aktualne );
	$do_kosza = 0;
	foreach ( $importowane as $id ) {
		if ( get_post_meta( $id, '_pnb_locked', true ) ) {
			continue;
		}
		$sid = get_post_meta( $id, '_pnb_source_id', true );
		if ( $sid && ! isset( $set[ $sid ] ) ) {
			wp_trash_post( $id );
			$do_kosza++;
		}
	}
	return $do_kosza;
}

/* ============================ LOG (do pliku, z rotacją via WP) ============================ */

function pnb_importer_log( $msg ) {
	$linia = current_time( 'Y-m-d H:i:s' ) . '  ' . $msg;
	// Trzymamy ostatnie ~200 linii jako opcję (proste, bez pliku — działa na każdym hostingu).
	$log = get_option( 'pnb_importer_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	$log[] = $linia;
	if ( count( $log ) > 200 ) { $log = array_slice( $log, -200 ); }
	update_option( 'pnb_importer_log', $log, false );
}
