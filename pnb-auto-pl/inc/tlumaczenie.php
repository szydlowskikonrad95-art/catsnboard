<?php
/*
 * ORKIESTRACJA TŁUMACZENIA — "przetłumacz RAZ przyciskiem" (serce prostej wersji).
 *
 * PRZEPŁYW (bez loopbacku! — audyt: wp_remote_get do siebie = deadlock na tanim hostingu):
 * 1. Admin klika „Przetłumacz witrynę" → JS w JEGO przeglądarce pobiera każdą podstronę
 *    fetch(url, {credentials:'omit'}) = strona renderuje się jak dla GOŚCIA (czysty HTML, bez admin-bara).
 * 2. JS POSTuje HTML do admin-ajax (nonce + manage_options) → TEN kod tnie na segmenty,
 *    tłumaczy braki batchem przez Claude, zapisuje pary do słownika.
 * 3. Front (front.php) tylko podmienia gotowe pary. Admin czeka z paskiem postępu — gość NIGDY.
 *
 * Pary linków (href → href?lang=pl) też lądują w słowniku (block_type='link') — jeden mechanizm podmiany.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* AJAX: przetłumacz jedną stronę (JS wysyła url + zrzucony HTML). */
add_action( 'wp_ajax_pnb_pl_tlumacz_strone', function () {
	check_ajax_referer( 'pnb_pl_tlumacz', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'blad' => 'Brak uprawnień.' ), 403 );
	}

	$url  = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
	$html = (string) wp_unslash( $_POST['html'] ?? '' ); // surowy HTML strony — tylko czytamy, nie wykonujemy
	if ( '' === $html || strlen( $html ) < 200 ) {
		wp_send_json_error( array( 'blad' => 'Pusty HTML strony.' ) );
	}

	try {
		$raport = pnb_pl_przetlumacz_html_strony( $html );
		$raport['url'] = $url;
		wp_send_json_success( $raport );
	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'blad' => 'Wyjątek: ' . $e->getMessage() ) );
	}
} );

/**
 * Przetłumacz segmenty jednej strony: wytnij → sprawdź słownik → braki batchem → zapisz.
 *
 * @return array raport: segmenty/z_cache/przetlumaczone/pominiete/limit_wyczerpany/znaki_dzis
 */
function pnb_pl_przetlumacz_html_strony( $html ) {
	$segmenty = pnb_pl_wytnij_segmenty( $html );
	$teksty   = array_map( function ( $s ) { return $s['tekst']; }, $segmenty );
	$typy     = array();
	foreach ( $segmenty as $s ) {
		$typy[ $s['tekst'] ] = $s['typ'];
	}

	// co już mamy (batch lookup jak TP)
	$gotowe = pnb_pl_pobierz_wiele( $teksty );

	// BEZPIECZNIK ANTY-PING-PONG (audyt 2026-07-05): jeśli złapany „oryginał" jest już CZYIMŚ
	// TŁUMACZENIEM (np. „Kontakt" = translated od „Contact"), to znaczy że łapiemy przetłumaczoną
	// stronę — NIE tłumaczymy tego (inaczej powstaje wsteczna para PL→EN i strtr×2 wraca do EN).
	global $wpdb;
	$przetlumaczone_wartosci = array_flip( array_filter( (array) $wpdb->get_col(
		'SELECT translated FROM ' . pnb_pl_tabela_slownika() . ' WHERE status != 0' // phpcs:ignore WordPress.DB.PreparedSQL
	) ) );

	$braki = array();
	foreach ( $teksty as $t ) {
		if ( isset( $gotowe[ pnb_pl_hash( $t ) ] ) ) {
			continue;
		}
		if ( isset( $przetlumaczone_wartosci[ pnb_pl_normalizuj( $t ) ] ) || isset( $przetlumaczone_wartosci[ $t ] ) ) {
			continue; // to nasz własny polski output — pomijamy
		}
		$braki[] = $t;
	}

	// tłumacz braki batchem (z limitem dziennym w środku)
	$nowe = empty( $braki ) ? array() : pnb_pl_tlumacz_batch( $braki );
	foreach ( $nowe as $orig => $pl ) {
		$typ = $typy[ $orig ] ?? '';
		// atrybut/meta: tłumaczenie NIE może mieć cudzysłowu ani tagów (rozwaliłoby attr="...")
		if ( 0 === strpos( $typ, 'atrybut:' ) || 0 === strpos( $typ, 'meta:' ) ) {
			$pl = wp_strip_all_tags( $pl );
			if ( false !== strpos( $pl, '"' ) || false !== strpos( $pl, '<' ) ) {
				continue; // odrzut → zostaje EN (bezpieczniej)
			}
		}
		pnb_pl_zapisz_segment( $orig, $pl, $typ );
	}

	// pary linków tej strony → słownik (block_type='link'; wewnętrzne, biała lista w segmentacja.php)
	$linki = pnb_pl_pary_linkow( $html );
	foreach ( $linki as $z => $na ) {
		pnb_pl_zapisz_segment_doslowny( $z, $na, 'link' );
	}

	return array(
		'segmenty'         => count( $teksty ),
		'z_cache'          => count( $gotowe ),
		'przetlumaczone'   => count( $nowe ),
		'pominiete'        => count( $braki ) - count( $nowe ),
		'linki'            => count( $linki ),
		'limit_wyczerpany' => pnb_pl_limit_wyczerpany(),
		'znaki_dzis'       => pnb_pl_licznik_dzis(),
	);
}

/**
 * Zapis DOSŁOWNY (bez normalizacji whitespace) — dla par linków href="..." które muszą
 * być bajt w bajt. Zwykłe segmenty idą przez pnb_pl_zapisz_segment (z normalizacją).
 */
function pnb_pl_zapisz_segment_doslowny( $original, $translated, $block_type = 'link' ) {
	global $wpdb;
	$tabela = pnb_pl_tabela_slownika();
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $tabela (hash, original, translated, status, block_type, zmieniono)
		 VALUES (%s, %s, %s, %d, %s, %s)
		 ON DUPLICATE KEY UPDATE translated = VALUES(translated), zmieniono = VALUES(zmieniono)", // phpcs:ignore WordPress.DB.PreparedSQL
		md5( (string) $original ),
		(string) $original,
		(string) $translated,
		PNB_PL_STATUS_MASZYNA,
		$block_type,
		current_time( 'mysql' )
	) );
	pnb_pl_wyczysc_cache_par();
}

/* ===== WYKRYWANIE ZMIAN (klient edytował → PL nieaktualne) ===== */

/* Po zapisie publicznej treści: oznacz stronę jako "PL do odświeżenia". BEZ auto-tłumaczenia
 * (audyt: wp_cron zawodny; decyzja: admin odświeża przyciskiem — pełna kontrola kosztu). */
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! is_object( $post ) || 'publish' !== $post->post_status ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'page', 'post', 'pnb_wydarzenie' ), true ) ) {
		return;
	}
	$stale = get_option( 'pnb_pl_nieaktualne', array() );
	if ( ! is_array( $stale ) ) {
		$stale = array();
	}
	$stale[ (string) $post_id ] = get_the_title( $post_id ) ?: ( 'post ' . $post_id );
	update_option( 'pnb_pl_nieaktualne', $stale, false );
}, 10, 2 );

/** Czyści listę "nieaktualne" (po ponownym tłumaczeniu w adminie). */
function pnb_pl_wyczysc_nieaktualne() {
	delete_option( 'pnb_pl_nieaktualne' );
}

/* ===== AUTO-TŁUMACZENIE SERWEROWE PO ZAPISIE (GŁÓWNY mechanizm) =====
 * Przeglądarkowy watcher zawodził (stare karty). To odpala się W SAMYM zapisie (rest_after_insert
 * = Gutenberg „Zapisz"), na serwerze: renderuje treść TEGO posta (do_blocks → HTML z heroTitle itd.),
 * tnie na segmenty, tłumaczy TYLKO nowe (reszta z cache). Zapis trwa +1-3 s — akceptowalne dla autora.
 * try/catch: zapis NIGDY nie może paść przez tłumaczenie. */
add_action( 'init', function () {
	foreach ( array( 'page', 'post', 'pnb_wydarzenie' ) as $typ ) {
		add_action( "rest_after_insert_{$typ}", 'pnb_pl_auto_po_zapisie', 20 );
	}
} );

/* Wydarzenia (CPT pnb_wydarzenie) mają wyłączony Gutenberg (show_in_rest=false) → rest_after_insert
 * NIGDY nie strzela dla nich (audyt 2026-07-05: nowe wydarzenie zostawało EN w wersji PL).
 * Klasyczny edytor → save_post. Guard: gdyby CPT kiedyś dostał REST, nie tłumaczymy 2×. */
add_action( 'save_post_pnb_wydarzenie', function ( $post_id, $post ) {
	static $juz_bylo = array();
	if ( isset( $juz_bylo[ $post_id ] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$juz_bylo[ $post_id ] = true;
	pnb_pl_auto_po_zapisie( $post );
}, 20, 2 );

/** Tłumaczy zmienioną treść posta zaraz po zapisie (serwerowo, bez przeglądarki/crona/loopbacku). */
function pnb_pl_auto_po_zapisie( $post ) {
	try {
		if ( ! is_object( $post ) || 'publish' !== $post->post_status ) {
			return;
		}
		// render treści posta jak na froncie (bloki dynamiczne wykonują render.php → pełny HTML z tekstami)
		$GLOBALS['post'] = $post; // niektóre bloki czytają global $post
		setup_postdata( $post );
		// wpautop: treść z klasycznego edytora (wydarzenia) to goły tekst bez <p> — bez tagów
		// blokowych segmentacja by jej nie złapała. Dla treści blokowej wpautop jest neutralne.
		$html = '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>' . wpautop( do_blocks( (string) $post->post_content ) );
		// wydarzenia: MIEJSCE (meta) też pokazuje się na karcie — tłumaczymy od razu razem z resztą
		if ( 'pnb_wydarzenie' === $post->post_type ) {
			$miejsce = trim( (string) get_post_meta( $post->ID, '_pnb_event_place', true ) );
			if ( '' !== $miejsce ) {
				$html .= '<p>' . esc_html( $miejsce ) . '</p>';
			}
		}
		wp_reset_postdata();
		if ( strlen( trim( $html ) ) < 20 ) {
			return;
		}
		pnb_pl_przetlumacz_html_strony( $html ); // segmenty → cache → tylko NOWE do Claude → słownik
	} catch ( \Throwable $e ) {
		// nic — zapis ważniejszy niż tłumaczenie; braki dotłumaczy przycisk/następny zapis
	}
}

/* ===== AUTO-TŁUMACZENIE PO ZAPISIE (edytor — dodatkowa notka wizualna) =====
 * Wymaganie z testów: zmiana treści ma się dotłumaczyć SAMA po zapisie.
 * Bez crona/loopbacku (zawodne — audyty): po udanym zapisie w Gutenbergu PRZEGLĄDARKA ADMINA
 * w tle robi to co przycisk: pobiera stronę jak gość → POST do ajax → dotłumaczenie zmienionych
 * segmentów (reszta z cache). Admin dostaje notkę „polska wersja zaktualizowana". */
add_action( 'admin_print_footer_scripts', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base ) {
		return; // tylko ekran edycji wpisu/strony
	}
	$ajax  = esc_js( admin_url( 'admin-ajax.php' ) );
	$nonce = esc_js( wp_create_nonce( 'pnb_pl_tlumacz' ) );
	?>
	<script>
	(function(){
		if(!window.wp||!wp.data||!wp.data.select('core/editor')){return;}
		var bylZapis=false, wToku=false;
		wp.data.subscribe(function(){
			var s=wp.data.select('core/editor');
			var zapisuje=s.isSavingPost()&&!s.isAutosavingPost();
			if(zapisuje){bylZapis=true;return;}
			if(!bylZapis||wToku){return;}
			bylZapis=false;
			if(s.didPostSaveRequestSucceed&&!s.didPostSaveRequestSucceed()){return;}
			var url=s.getPermalink();if(!url){return;}
			wToku=true;
			// jak gość (bez cookies) → świeży HTML → dotłumacz zmienione segmenty
			fetch(url,{credentials:'omit'}).then(function(r){return r.text();}).then(function(html){
				return fetch('<?php echo $ajax; // phpcs:ignore ?>',{method:'POST',
					headers:{'Content-Type':'application/x-www-form-urlencoded'},
					body:new URLSearchParams({action:'pnb_pl_tlumacz_strone',nonce:'<?php echo $nonce; // phpcs:ignore ?>',url:url,html:html})});
			}).then(function(r){return r.json();}).then(function(j){
				wToku=false;
				if(j.success&&wp.data.dispatch('core/notices')){
					wp.data.dispatch('core/notices').createNotice('success',
						'🌍 Polska wersja zaktualizowana ('+j.data.przetlumaczone+' nowych tłumaczeń)',
						{isDismissible:true});
				}
			}).catch(function(){wToku=false;});
		});
	})();
	</script>
	<?php
} );

/* Notka w adminie gdy PL nieaktualne. */
add_action( 'admin_notices', function () {
	$stale = get_option( 'pnb_pl_nieaktualne', array() );
	if ( empty( $stale ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$link = esc_url( admin_url( 'options-general.php?page=pnb-auto-pl' ) );
	echo '<div class="notice notice-warning"><p><strong>PNB Auto PL:</strong> '
		. esc_html( sprintf( _n( '%d strona zmieniona po ostatnim tłumaczeniu', '%d stron(y) zmienione po ostatnim tłumaczeniu', count( $stale ), 'pnb-auto-pl' ), count( $stale ) ) )
		. ' (' . esc_html( implode( ', ', array_slice( array_values( $stale ), 0, 5 ) ) ) . ') — '
		. '<a href="' . $link . '">' . esc_html__( 'przetłumacz ponownie', 'pnb-auto-pl' ) . '</a>.</p></div>';
} );
