<?php
/* Moduł GALERIA: premium grid (wzór: galeria-premium.html) + wybór zdjęć z biblioteki mediów. */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ==== USTAWIENIA (Settings API: sanityzacja zdjęć/strony). Menu-panel USUNIĘTY — galeria edytowana
 *      przez BLOK Gutenberg pnb/galeria, nie przez osobny ekran admina. Opcje zostają (render je czyta). ==== */

add_action( 'admin_init', function () {
	register_setting( 'pnb_galeria', 'pnb_galeria_zdjecia', array(
		'type'              => 'array',
		'sanitize_callback' => 'pnb_galeria_sanityzuj_ids',
		'default'           => array(),
	) );
	register_setting( 'pnb_galeria', 'pnb_galeria_strona', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 0,
	) );
} );

function pnb_galeria_sanityzuj_ids( $wartosc ) {
	if ( is_string( $wartosc ) ) {
		$wartosc = explode( ',', $wartosc );
	}
	if ( ! is_array( $wartosc ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'absint', $wartosc ) ) );
}

/* ==== FRONT: shortcode + auto-wstawienie po stronie (lekcja: buildery nie renderują shortcode) ==== */

add_shortcode( 'pnb_galeria', 'pnb_galeria_render' );

add_filter( 'the_content', 'pnb_galeria_auto_wstaw' );
function pnb_galeria_auto_wstaw( $tresc ) {
	static $zrobione = false; // motywy wołają the_content wielokrotnie — doklejamy RAZ
	$strona = (int) get_option( 'pnb_galeria_strona', 0 );
	if ( $zrobione || ! $strona || ! is_page( $strona ) || ! in_the_loop() || ! is_main_query() ) {
		return $tresc;
	}
	if ( has_shortcode( $tresc, 'pnb_galeria' ) ) {
		return $tresc; // ktoś wstawił ręcznie — nie dublujemy
	}
	// NOWE: jeśli strona ma nasz blok Gutenberg pnb/galeria → to ON renderuje taśmę. Nie doklejamy drugi raz.
	$post_biezacy = get_post();
	if ( $post_biezacy && has_block( 'pnb/galeria', $post_biezacy ) ) {
		return $tresc;
	}
	$zrobione = true;
	// PODMIANA, nie doklejenie: jeśli strona ma "starą" galerię (blok motywu .cnb-gallery
	// albo klasyczną .gallery/.masonry), UKRYWAMY ją CSS-em i pokazujemy naszą. CSS (nie
	// kasowanie z bazy) = po deaktywacji pluginu blok wraca sam → strona jak przed wpięciem.
	$ukryj = '<style id="pnb-galeria-podmiana">.cnb-gallery,.wp-block-catsnboard-gallery,'
		. 'section.gallery .masonry{display:none !important}</style>';
	return $ukryj . $tresc . pnb_galeria_render();
}

/* AUTO-WYKRYCIE strony galerii (audyt 2026-07-05: opcja pnb_galeria_strona była czytana w 3 miejscach,
 * ale NIC jej nie zapisywało po usunięciu panelu → FOUC-fix i CSS pełnej sceny nigdy nie startowały
 * na świeżej instalacji). Wzorzec identyczny jak Events (kalendarz.php). */
add_action( 'save_post_page', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
		return;
	}
	if ( has_block( 'pnb/galeria', $post ) ) {
		update_option( 'pnb_galeria_strona', (int) $post_id );
	} elseif ( (int) get_option( 'pnb_galeria_strona', 0 ) === (int) $post_id ) {
		delete_option( 'pnb_galeria_strona' );
	}
}, 10, 2 );

add_action( 'admin_init', function () {
	if ( get_option( 'pnb_galeria_strona' ) ) {
		return;
	}
	$strony = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids' ) );
	foreach ( $strony as $sid ) {
		if ( has_block( 'pnb/galeria', $sid ) ) {
			update_option( 'pnb_galeria_strona', (int) $sid );
			break;
		}
	}
} );

/* Assety w <head> gdy wiadomo że galeria będzie (FOUC-fix); render woła je drugi raz jako fallback.
 * Priorytet 20 (PO motywie, domyślnie 10): pnb_galeria_zapewnij_gsap() musi widzieć czy motyw już
 * zarejestrował własny GSAP — inaczej załadowalibyśmy drugą kopię (konflikt scroll-tickera, taśma stoi). */
add_action( 'wp_enqueue_scripts', function () {
	$strona = (int) get_option( 'pnb_galeria_strona', 0 );
	$bedzie = $strona && is_page( $strona );
	if ( ! $bedzie && is_singular() ) {
		$post   = get_post();
		$bedzie = $post && ( has_shortcode( $post->post_content, 'pnb_galeria' ) || has_block( 'pnb/galeria', $post ) );
	}
	if ( $bedzie ) {
		pnb_galeria_zaladuj_assety();
	}
}, 20 );

/* Buduje nagłówek „słowa-schodki" z DOWOLNEGO tekstu (tłumaczonego): każde słowo w masce,
   ostatnie $ile_akc słów dostaje klasę akcentu (pnb-akc = koral). Znak końcowy (kropka) zostaje przy
   ostatnim słowie. Dzięki temu nagłówek działa po EN i PL bez hardkodowania słów. */
function pnb_galeria_slowa_schodki( $tekst, $ile_akc = 2 ) {
	$slowa = preg_split( '/\s+/', trim( $tekst ) );
	if ( empty( $slowa ) ) {
		return '';
	}
	$n     = count( $slowa );
	$prog  = max( 0, $n - $ile_akc ); // od tego indeksu słowa są akcentowane
	$out   = '';
	foreach ( $slowa as $i => $slowo ) {
		$akc   = ( $i >= $prog ) ? ' pnb-akc' : '';
		$spacja = ( $i < $n - 1 ) ? ' ' : '';
		$out  .= '<span class="pnb-wm"><span class="pnb-wi' . $akc . '">' . esc_html( $slowo ) . '</span></span>' . $spacja;
	}
	return $out;
}

/* Buduje pulę kadrów (src/full/cap) z listy ID załączników. Wspólne dla taśmy i rzek „Moments"
   (dwa niezależne zestawy zdjęć). $dzien = fallback podpisów, gdy załącznik nie ma własnego. */
function pnb_galeria_zbuduj_pule( $ids, $dzien ) {
	$pula = array();
	$licznik_dzien = max( 1, count( $dzien ) );
	foreach ( array_values( $ids ) as $i => $id ) {
		$img  = wp_get_attachment_image_url( $id, 'large' );
		$full = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $img ) {
			continue;
		}
		$cap    = wp_get_attachment_caption( $id );
		// srcset: WYDAJNOŚĆ — bez tego przeglądarka ciągnie 'large' (~1024px) nawet na telefonie i w rzekach
		// (kadr ~200-300px). Z srcset wybierze najmniejszy pasujący wariant. sizes dobiera render/JS per sekcja.
		$srcset = wp_get_attachment_image_srcset( $id, 'large' );
		$pula[] = array(
			'src'    => $img,
			'full'   => $full ? $full : $img,
			'cap'    => $cap ? $cap : $dzien[ $i % $licznik_dzien ],
			'srcset' => $srcset ? $srcset : '',
		);
	}
	return $pula;
}

/* Pula DEMO ze zdjęć motywu — „mozaika nigdy nie jest zepsuta": gdy klient jeszcze nie wybrał
   zdjęć, taśma jedzie na kotach demo (te same pliki co prosta galeria motywu). Na obcym motywie
   (brak plików) zwraca pustkę i galeria zachowuje się jak dotąd. */
function pnb_galeria_pula_demo( $dzien ) {
	$katalog = get_template_directory() . '/assets/img/';
	$baza    = get_template_directory_uri() . '/assets/img/';
	$pliki   = array(
		'kot-1.jpg', 'kot-6.jpg', 'kot-3.jpg', 'kot-8.jpg', 'kot-2.jpg', 'kot-15.jpg',
		'kot-4.jpg', 'kot-11.jpg', 'kot-7.jpg', 'kot-13.jpg', 'kot-10.jpg', 'kot-16.jpg',
	);
	$licznik = max( 1, count( $dzien ) );
	$pula    = array();
	foreach ( $pliki as $i => $plik ) {
		if ( ! file_exists( $katalog . $plik ) ) {
			continue;
		}
		$pula[] = array(
			'src'    => $baza . $plik,
			'full'   => $baza . $plik,
			'cap'    => $dzien[ $i % $licznik ],
			'srcset' => '',
		);
	}
	return $pula;
}

/* Render wg WZORCA galerii premium działu (przedszkole v3, PASS 90, akcept klienta):
   TAŚMA KINOWA (scroll pionowy = kadry jadą w bok) + RZEKI PRZECIWBIEŻNE + LIGHTBOX z kadru. */
function pnb_galeria_render() {
	$ids = pnb_galeria_sanityzuj_ids( get_option( 'pnb_galeria_zdjecia', array() ) );
	// Filtr źródła: blok Gutenberg (pnb/galeria) podaje SWOJE zdjęcia na swojej stronie (blok wygrywa).
	// Panel admina dalej działa jak wcześniej (gdy nikt nie filtruje). Zwrot = lista ID.
	$ids = pnb_galeria_sanityzuj_ids( apply_filters( 'pnb_galeria_zrodlo_zdjec', $ids ) );

	// narracyjne podpisy dnia kota — fallback gdy załącznik nie ma własnego podpisu.
	// Edytowalne w panelu Teksty (zakładka Galeria → Podpis zdjęcia 1..8).
	$dzien = array(
		pnb_txt( 'gallery.caption.1', 'Morning hello' ),
		pnb_txt( 'gallery.caption.2', 'Playtime' ),
		pnb_txt( 'gallery.caption.3', 'Sunbath nap' ),
		pnb_txt( 'gallery.caption.4', 'Bird watching' ),
		pnb_txt( 'gallery.caption.5', "Lunch o'clock" ),
		pnb_txt( 'gallery.caption.6', 'Grooming' ),
		pnb_txt( 'gallery.caption.7', 'Evening cuddles' ),
		pnb_txt( 'gallery.caption.8', 'Back with family' ),
	);

	// Rzeki „Moments that stay": OSOBNY zestaw zdjęć (momentsIds z bloku, przez filtr).
	// FALLBACK: gdy klient nie wybrał osobnych → rzeki biorą tę samą pulę co taśma (jak dotąd, brak pustej sekcji).
	$moments_ids = pnb_galeria_sanityzuj_ids( apply_filters( 'pnb_galeria_moments_zdjec', array() ) );

	// WYDAJNOŚĆ: prime cache załączników RAZ dla OBU zestawów (taśma + Moments) — bez tego każde
	// wp_get_attachment_* bije osobno do bazy (przy 20-50 zdjęciach = 60-150 zapytań). _prime_post_caches
	// ściąga posty+meta jednym zapytaniem → kolejne wp_get_attachment_* czytają z cache. (WP core od 6.1.)
	$wszystkie_ids = array_values( array_unique( array_merge( $ids, $moments_ids ) ) );
	if ( $wszystkie_ids && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( $wszystkie_ids, false, true ); // (ids, update_term_cache=false, update_meta_cache=true)
	}

	$pula         = pnb_galeria_zbuduj_pule( $ids, $dzien );
	$pula_moments = $moments_ids ? pnb_galeria_zbuduj_pule( $moments_ids, $dzien ) : array();
	if ( empty( $pula_moments ) ) {
		$pula_moments = $pula; // brak osobnych Moments → rzeki jak taśma
	}

	$demo_hint    = '';
	$galeria_demo = false;
	if ( empty( $pula ) ) {
		$galeria_demo = true;
		// bez wybranych zdjęć NIE pokazujemy pustki — taśma jedzie na kotach demo motywu
		// („mozaika nigdy nie jest zepsuta"); klient podmienia w edytorze („Choose photos").
		$pula = pnb_galeria_pula_demo( $dzien );
		if ( empty( $pula ) ) {
			// obcy motyw bez zdjęć demo — stare zachowanie: cicho dla gości, podpowiedź dla admina
			if ( current_user_can( 'manage_options' ) ) {
				return '<p style="padding:2rem;text-align:center;">'
					. esc_html__( 'PNB Gallery: edit this page and pick photos in the Gallery block (“Choose photos”) — the filmstrip comes alive.', 'pnb-toolkit' ) . '</p>';
			}
			return '';
		}
		$pula_moments = $pula;
		if ( current_user_can( 'manage_options' ) ) {
			$demo_hint = '<p class="pnb-mono" style="margin:0;padding:.7rem 1rem;text-align:center;font-size:.8rem;opacity:.75;">'
				. esc_html__( 'Demo photos from the theme — edit this page and use “Choose photos” to show your own.', 'pnb-toolkit' ) . '</p>';
		}
	}

	pnb_galeria_zaladuj_assety( $pula, $pula_moments );

	// Taśma = WSZYSTKIE zdjęcia klienta (decyzja projektowa 2026-07-05): edytor pokazuje „Choose photos (N)",
	// więc taśma na froncie też ma pokazać N — inaczej klient myśli że gubi zdjęcia (dodał 12, taśma 8).
	// Wcześniej: array_slice(...,0,8). Teraz cała pula. Rzeki „Moments" i tak dostają całość (JS).
	$tasma = $pula;

	// HERO galerii — nagłówek słowa-schodki (maski w PHP, JS animuje).
	// Nagłówek budowany ze STRINGU TŁUMACZONEGO (nie na sztywno!) — inaczej widoczne słowa zostają
	// po angielsku, a tłumaczy się tylko aria-label. Ostatnie 2 słowa = akcent koralowy (pnb-akc).
	// Teksty edytowalne w panelu Teksty (zakładka Galeria); slowa_schodki robi akcent na ostatnich 2 słowach.
	$naglowek = pnb_txt( 'gallery.hero.title', 'Our days in frames.' );
	// Zdjęcie hero galerii (opcjonalne) — blok Gutenberg podaje przez filtr pnb_galeria_hero_id. 0 = bez zdjęcia
	// (gradient jak dotąd). Gdy jest → tło-zdjęcie + welon dla czytelności (klasa has-img).
	$ghero_id  = (int) apply_filters( 'pnb_galeria_hero_id', 0 );
	$ghero_img = $ghero_id ? wp_get_attachment_image_url( $ghero_id, 'full' ) : '';
	if ( ! $ghero_img && $galeria_demo && file_exists( get_template_directory() . '/assets/img/kot-7.jpg' ) ) {
		// tryb demo: hero też dostaje kota z motywu (żaden gość nie ogląda gołego gradientu)
		$ghero_img = get_template_directory_uri() . '/assets/img/kot-7.jpg';
	}
	$out  = $demo_hint . '<section class="pnb-ghero' . ( $ghero_img ? ' has-img' : '' ) . '">';
	if ( $ghero_img ) {
		$out .= '<div class="pnb-ghero-img" aria-hidden="true"><img src="' . esc_url( $ghero_img ) . '" alt="" loading="eager"></div>';
		$out .= '<div class="pnb-ghero-veil" aria-hidden="true"></div>';
		$out .= '<div class="pnb-ghero-grain" aria-hidden="true"></div>'; // ziarno jak w hero kalendarza
	}
	// „Meow" (watermark) POKAZUJEMY tylko gdy NIE ma zdjęcia (na zdjęciu przeszkadzał — decyzja klienta).
	// Układ 1:1 jak hero kalendarza (.pnb-evh-in): kontener flex-column, order eyebrow→hint→h1 (nagłówek NA DOLE).
	$out .= '<div class="pnb-ghero-in">';
	$out .= '<span class="pnb-mono pnb-eyebrow-g">' . esc_html( pnb_txt( 'gallery.hero.eyebrow', 'Gallery' ) ) . '</span>';
	$out .= '<h1 class="pnb-gh1" aria-label="' . esc_attr( $naglowek ) . '">'
		. pnb_galeria_slowa_schodki( $naglowek, 2 )
		. '</h1>';
	$out .= '<div class="pnb-mono pnb-hint-top">' . esc_html( pnb_txt( 'gallery.hint.top', 'Scroll — photos ride. Click — a frame grows.' ) ) . '</div>';
	$out .= '</div>'; // .pnb-ghero-in
	$out .= '</section>';

	// TAŚMA KINOWA: sticky viewport, scroll pionowy przewija kadry POZIOMO.
	// GŁĘBIA = warstwy w różnych tempach: plamy (najwolniej) → watermark → kadry (najszybciej).
	$out .= '<section class="pnb-strip"><div class="pnb-strip-sticky">';
	$out .= '<div class="pnb-sblob pnb-sblob-a" aria-hidden="true"></div><div class="pnb-sblob pnb-sblob-b" aria-hidden="true"></div>';
	$out .= '<div class="pnb-strip-word" aria-hidden="true">' . esc_html__( 'Gallery', 'pnb-toolkit' ) . '</div>';
	$out .= '<div class="pnb-strip-count pnb-mono" id="pnbCount">01 / ' . esc_html( sprintf( '%02d', count( $tasma ) ) ) . '</div>';
	$out .= '<div class="pnb-strip-cap pnb-mono" id="pnbCapBar" aria-hidden="true"></div>'; // mobile: podpis aktywnego kadru przypięty do viewportu
	$out .= '<div class="pnb-track" id="pnbTrack">';
	foreach ( $tasma as $i => $el ) {
		$nr   = sprintf( '%02d', $i + 1 );
		$cap  = $nr . ' — ' . $el['cap'];
		$tall = ( $i % 2 ) ? '' : ' tall';
		// loading=eager (NIE lazy!): szerokość taśmy (scrollWidth) = suma szerokości kadrów. Przy lazy
		// kadry poza ekranem mają width=0 dopóki się nie doładują → ScrollTrigger liczy za mały zakres →
		// taśma NIE dojeżdża do ostatniego kadru (pusta przerwa). Eager = pełne wymiary od startu.
		// fetchpriority=low: ładujemy od razu, ale bez rywalizacji z hero. (Rzeki niżej zostają lazy.)
		// srcset+sizes: kadr taśmy ma wys. ~clamp(300..560px), szer. auto → na desktop ~40vw, mobile ~60vw.
		$srcset_attr = $el['srcset'] ? ' srcset="' . esc_attr( $el['srcset'] ) . '" sizes="(max-width:768px) 70vw, 40vw"' : '';
		$out .= '<figure class="pnb-shot' . $tall . '" data-cap="' . esc_attr( $cap ) . '" data-full="' . esc_url( $el['full'] ) . '">'
			. '<img src="' . esc_url( $el['src'] ) . '"' . $srcset_attr . ' alt="' . esc_attr( $el['cap'] ) . '" loading="eager" fetchpriority="low" decoding="async">'
			. '<figcaption class="pnb-mono">' . esc_html( $cap ) . '</figcaption></figure>';
	}
	$out .= '</div><div class="pnb-strip-hint pnb-mono">' . esc_html( pnb_txt( 'gallery.hint.keep', '— keep scrolling —' ) ) . '</div></div></section>';

	// RZEKI PRZECIWBIEŻNE (JS wypełnia z puli ×2, hover = pauza)
	$out .= '<section class="pnb-rivers">';
	$out .= '<h2 class="pnb-gh2">' . wp_kses( pnb_txt( 'gallery.mid.title', 'Moments that <em>stay</em>.' ), array( 'em' => array() ) ) . '</h2>';
	$out .= '<div class="pnb-river pnb-r1" id="pnbRiv1"></div><div class="pnb-river pnb-r2" id="pnbRiv2"></div>';
	$out .= '</section>';

	// CTA KOŃCOWE (z wzorca działu — strona-galeria nie może się urywać: prowadź gościa dalej)
	$kontakt = get_page_by_path( 'contact' );
	$cel     = $kontakt ? get_permalink( $kontakt ) : home_url( '/' );
	$out .= '<section class="pnb-gcta">';
	$out .= '<h2 class="pnb-gh2">' . wp_kses( pnb_txt( 'gallery.cta.title', 'They look even better <em>in person</em>.' ), array( 'em' => array() ) ) . '</h2>';
	$out .= '<p class="pnb-gcta-lead">' . esc_html( pnb_txt( 'gallery.cta.lead', 'Come say hi — your cat will love it here.' ) ) . '</p>';
	$out .= '<a class="pnb-gcta-btn" href="' . esc_url( $cel ) . '">' . esc_html( pnb_txt( 'gallery.cta.btn', 'Book a visit' ) ) . ' →</a>';
	$out .= '</section>';

	return $out;
}

/* Zapewnia DOKŁADNIE JEDNĄ kopię GSAP+ScrollTrigger na stronie i zwraca handle-zależności dla naszego JS.
 * Jeśli motyw/wtyczka już zarejestrowały GSAP (typowe handle: gsap, gsap-js, gsap-scrolltrigger,
 * scrolltrigger...) — używamy ich (zero drugiej kopii = zero konfliktu scroll-tickera). W przeciwnym razie
 * ładujemy własne pnb-gsap*. Zwraca tablicę handli do wpięcia jako dependency galeria-front.js. */
function pnb_galeria_zapewnij_gsap() {
	// Szukamy CUDZEGO gsap-core wśród już zarejestrowanych/zaenqueue'owanych skryptów (po src).
	$obcy_gsap = pnb_galeria_znajdz_handle_po_src( array( '/gsap.min.js', '/gsap.js', '/gsap-core' ), 'pnb-' );
	$obcy_st   = pnb_galeria_znajdz_handle_po_src( array( 'scrolltrigger' ), 'pnb-' );

	if ( $obcy_gsap && $obcy_st ) {
		// Strona MA już GSAP+ScrollTrigger (np. motyw) → NIE ładujemy drugiej kopii. Wpinamy się pod nie.
		return array( $obcy_gsap, $obcy_st );
	}

	// Brak (lub niepełny) obcy GSAP → ładujemy WŁASNY (offline; licencja no-charge — nota w pliku, CREDITS.md).
	wp_enqueue_script( 'pnb-gsap', PNB_TOOLKIT_URL . 'assets/lib/gsap.min.js', array(), '3.12.5', true );
	wp_enqueue_script( 'pnb-gsap-scrolltrigger', PNB_TOOLKIT_URL . 'assets/lib/ScrollTrigger.min.js', array( 'pnb-gsap' ), '3.12.5', true );
	return array( 'pnb-gsap-scrolltrigger' );
}

/* Znajduje handle zarejestrowanego/zaenqueue'owanego skryptu, którego src zawiera którykolwiek z fragmentów.
 * Pomija handle zaczynające się od $wyklucz_prefix (żeby nie znaleźć własnych pnb-*). Zwraca handle lub ''. */
function pnb_galeria_znajdz_handle_po_src( $fragmenty, $wyklucz_prefix = '' ) {
	$wp_scripts = wp_scripts();
	if ( ! $wp_scripts || empty( $wp_scripts->registered ) ) {
		return '';
	}
	foreach ( $wp_scripts->registered as $handle => $dane ) {
		if ( '' !== $wyklucz_prefix && 0 === strpos( $handle, $wyklucz_prefix ) ) {
			continue;
		}
		$src = isset( $dane->src ) ? strtolower( (string) $dane->src ) : '';
		if ( '' === $src ) {
			continue;
		}
		foreach ( $fragmenty as $frag ) {
			if ( false !== strpos( $src, strtolower( $frag ) ) ) {
				return $handle;
			}
		}
	}
	return '';
}

/* ==== ASSETY: ładowane tylko gdy galeria realnie renderuje (zero narzutu na resztę strony) ==== */

function pnb_galeria_zaladuj_assety( $pula = array(), $pula_moments = array() ) {
	// CDN z pinem wersji; przed paczką dla klienta → zbundlować lokalnie (RUBRYKA, ograniczenie v1).
	// Prefiks pnb- w handlach: generyczne 'gsap' mogłaby nadpisać inna wtyczka starszą wersją.
	wp_enqueue_style( 'pnb-fonty', PNB_TOOLKIT_URL . 'assets/fonts/fonts.css', array(), PNB_TOOLKIT_VERSION ); // fonty LOKALNIE (OFL), offline
	wp_enqueue_style( 'pnb-galeria', PNB_TOOLKIT_URL . 'assets/css/galeria.css', array(), PNB_TOOLKIT_VERSION );
	$strona = (int) get_option( 'pnb_galeria_strona', 0 );
	if ( $strona && is_page( $strona ) ) {
		// strona galerii = pełna scena: chowamy dublujący tytuł strony motywu
		wp_add_inline_style( 'pnb-galeria', '.page-id-' . $strona . ' .entry-title{display:none}'
			. '.page-id-' . $strona . ' .entry-content{margin:0}'
			. '.page-id-' . $strona . ' .site-content,.page-id-' . $strona . ' #content{padding-top:0;margin-top:0}' );
	}
	// ⚠️ JEDNA KOPIA GSAP na stronie (KRYTYCZNE): dwie kopie ScrollTrigger się GRYZĄ — druga przejmuje
	// globalny scroll-ticker, przez co triggery pierwszej nie dostają update'ów (taśma STOI / nie dojeżdża).
	// Dlatego: jeśli motyw/inna wtyczka JUŻ dostarcza GSAP+ScrollTrigger — używamy ICH (nie ładujemy drugiej
	// kopii). Nasz galeria-front.js działa na window.gsap czyjkolwiek (GSAP 3.x API stabilne). Fallback:
	// gdy strona nie ma GSAP — ładujemy własny (offline; licencja no-charge, nota w CREDITS.md).
	$gsap_dep = pnb_galeria_zapewnij_gsap();
	wp_enqueue_script( 'pnb-galeria-front', PNB_TOOLKIT_URL . 'assets/js/galeria-front.js', $gsap_dep, PNB_TOOLKIT_VERSION, true );
	if ( $pula ) {
		// pool = taśma (górna karuzela), momentsPool = rzeki „Moments" (osobny zestaw; fallback = pool).
		wp_localize_script( 'pnb-galeria-front', 'pnbGaleriaData', array(
			'pool'        => $pula,
			'momentsPool' => $pula_moments ? $pula_moments : $pula,
		) );
	}
	// priorytet 5: markup MUSI wydrukować się przed skryptami stopki (wp_print_footer_scripts = 20),
	// inaczej galeria-front.js nie znajdzie #pnbLb i lightbox nigdy nie ożyje
	add_action( 'wp_footer', 'pnb_galeria_lightbox_markup', 5 );
}

/* Lightbox wg wzorca: klik = kadr rośnie; strzałki, Esc, klik tła; lenis.stop/start w JS. */
function pnb_galeria_lightbox_markup() {
	?>
	<div class="pnb-lb" id="pnbLb" aria-hidden="true">
		<span class="pnb-lb-x pnb-mono" id="pnbLbX"><?php esc_html_e( 'Close ✕', 'pnb-toolkit' ); ?></span>
		<span class="pnb-lb-nr pnb-mono" id="pnbLbNr"></span>
		<span class="pnb-lb-ar pnb-lb-prev" id="pnbLbP">←</span>
		<img id="pnbLbImg" src="" alt="">
		<span class="pnb-lb-ar pnb-lb-next" id="pnbLbN">→</span>
		<div class="pnb-lb-cap pnb-mono" id="pnbLbCap"></div>
	</div>
	<?php
}

/* ============================================================================
 * MARTWY KOD USUNIĘTY 2026-07-05 (v1.0.6). Galeria jest w pełni edytowana przez BLOK
 * Gutenberg pnb/galeria (zdjęcia w atrybucie imageIds → render czyta je przez filtr).
 * Usunięto osierocone po zdjęciu menu admina:
 *   - pnb_galeria_panel_wbudowany() — panel #pnb-galeria-grid nigdzie nie podpięty (edit_form_after_title
 *     nie był rejestrowany dla galerii), więc HTML nigdy się nie drukował.
 *   - admin_enqueue_scripts (galeria-admin.js / galeria-embed.js / galeria-admin.css) — ładowały się na
 *     ekranie edycji strony „Gallery", ale bez markupu panelu = bez efektu.
 *   - AJAX wp_ajax_pnb_galeria_zapisz — wołany WYŁĄCZNIE przez galeria-embed.js (osierocony); blok zapisuje
 *     zdjęcia w atrybucie posta, nie przez ten endpoint.
 *   - pliki assets/js/galeria-admin.js, assets/js/galeria-embed.js, assets/css/galeria-admin.css (skasowane).
 * ZOSTAWIONE świadomie: register_setting pnb_galeria_zdjecia / pnb_galeria_strona + pnb_galeria_sanityzuj_ids
 * (render i auto-wstawienie czytają te opcje).
 * ========================================================================== */
