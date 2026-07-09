<?php
/**
 * Cats'N'Board theme functions.
 *
 * @package catsnboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup.
 */
function catsnboard_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'menus' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'catsnboard' ),
		)
	);
}
add_action( 'after_setup_theme', 'catsnboard_setup' );

/**
 * Enqueue styles and scripts.
 */
function catsnboard_assets() {
	// Fonty LOKALNIE (Varela Round + Poppins, licencja OFL) — paczka działa offline, bez fonts.googleapis.com.
	wp_enqueue_style(
		'catsnboard-fonts',
		get_template_directory_uri() . '/assets/fonts/fonts.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	// Main theme stylesheet (contains the whole CSS from the source).
	wp_enqueue_style(
		'catsnboard-style',
		get_stylesheet_uri(),
		array( 'catsnboard-fonts' ),
		wp_get_theme()->get( 'Version' )
	);

	// Lenis + GSAP + ScrollTrigger LOKALNIE (paczka offline; Lenis MIT + LICENSE, GSAP no-charge z notą — CREDITS.md).
	$lib = get_template_directory_uri() . '/assets/lib/';
	wp_enqueue_script(
		'lenis',
		$lib . 'lenis.min.js',
		array(),
		'1.1.13',
		true
	);
	wp_enqueue_script(
		'gsap',
		$lib . 'gsap.min.js',
		array(),
		'3.12.5',
		true
	);
	wp_enqueue_script(
		'gsap-scrolltrigger',
		$lib . 'ScrollTrigger.min.js',
		array( 'gsap' ),
		'3.12.5',
		true
	);

	// Theme JS (Lenis clock, reveals, split words, lightbox, cursor, menu).
	wp_enqueue_script(
		'catsnboard-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array( 'lenis', 'gsap', 'gsap-scrolltrigger' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'catsnboard_assets' );

/**
 * Helper: URL of a theme image asset.
 *
 * @param string $file File name inside assets/img/.
 * @return string
 */
function catsnboard_img( $file ) {
	return get_template_directory_uri() . '/assets/img/' . ltrim( $file, '/' );
}

/**
 * Editable text — bridge to PNB Toolkit's pnb_txt() (panel edytuje, wersja PL tłumaczy).
 * Motyw działa BEZ pluginu: gdy pnb_txt nie istnieje, zwracamy domyślny tekst z kodu.
 * Zwraca SUROWY tekst — escapuj w miejscu (esc_html / wp_kses).
 *
 * @param string $key      klucz semantyczny (np. 'home.hero.title')
 * @param string $default  domyślny tekst (EN)
 * @return string
 */
function catsnboard_txt( $key, $default = '' ) {
	return function_exists( 'pnb_txt' ) ? pnb_txt( $key, $default ) : $default;
}

/**
 * Dane kontaktowe strony — klient edytuje w Wygląd → Dostosuj → „Dane kontaktowe".
 * Zero zmyślonych domyślnych: dopóki pole puste, sekcja się NIE pokazuje (zasada „nic nie zmyślamy").
 * Kolejność źródeł: Customizer → słownik pnb_teksty (wartości ustawione dawnym panelem) → pusto.
 *
 * @param string $co 'tel' | 'email' | 'adres' | 'mapka' (krótka etykieta przy pinezce)
 * @return string
 */
function catsnboard_kontakt( $co ) {
	$mapa = array(
		'tel'   => array( 'catsnboard_tel', 'contact.phone' ),
		'email' => array( 'catsnboard_email', 'contact.email' ),
		'adres' => array( 'catsnboard_adres', 'contact.address' ),
		'mapka' => array( 'catsnboard_mapka', 'contact.maplabel' ),
	);
	if ( ! isset( $mapa[ $co ] ) ) {
		return '';
	}
	$wartosc = trim( (string) get_theme_mod( $mapa[ $co ][0], '' ) );
	if ( '' === $wartosc ) {
		$wartosc = trim( (string) catsnboard_txt( $mapa[ $co ][1], '' ) );
	}
	return $wartosc;
}

/** Sekcja „Dane kontaktowe" w Customizerze (Wygląd → Dostosuj). */
add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'catsnboard_kontakt', array(
		'title'    => 'Dane kontaktowe / Contact details',
		'priority' => 30,
	) );
	$pola = array(
		'catsnboard_tel'   => array( 'Telefon / Phone', 'sanitize_text_field' ),
		'catsnboard_email' => array( 'E-mail', 'sanitize_email' ),
		'catsnboard_adres' => array( 'Adres / Address', 'sanitize_text_field' ),
		'catsnboard_mapka' => array( 'Etykieta przy mapce / Map label', 'sanitize_text_field' ),
	);
	foreach ( $pola as $id => $pole ) {
		$wp_customize->add_setting( $id, array(
			'default'           => '',
			'sanitize_callback' => $pole[1],
		) );
		$wp_customize->add_control( $id, array(
			'section' => 'catsnboard_kontakt',
			'label'   => $pole[0],
			'type'    => 'text',
		) );
	}
} );

/**
 * Szkielet strony przy WŁĄCZENIU motywu — klient wgrywa motyw i od razu ma
 * edytowalne podstrony (blok w treści strony = edycja klikaniem w edytorze).
 * Idempotentnie: strona o danym slugu już istnieje → nie ruszamy jej.
 * Gallery/Events dostają bloki wtyczki „PNB Galeria i Wydarzenia" — ożywają
 * po jej włączeniu (kolejność instalacji bez znaczenia).
 */
add_action( 'after_switch_theme', 'catsnboard_szkielet_stron' );
function catsnboard_szkielet_stron() {
	$strony = array(
		'home'         => array( 'Home', '<!-- wp:catsnboard/home /-->' ),
		'services'     => array( 'Services', '<!-- wp:catsnboard/services /-->' ),
		'pricing'      => array( 'Pricing', '<!-- wp:catsnboard/pricing /-->' ),
		'our-staff'    => array( 'Our Staff', '<!-- wp:catsnboard/staff /-->' ),
		'our-location' => array( 'Our Location', '<!-- wp:catsnboard/location /-->' ),
		'contact'      => array( 'Contact', '<!-- wp:catsnboard/contact /-->' ),
		'about-us'     => array( 'About us', '<!-- wp:catsnboard/about /-->' ),
		'gallery'      => array( 'Gallery', '<!-- wp:catsnboard/gallery /-->' ),
	);
	// Gallery dostaje PROSTĄ galerię motywu (mozaika) — wtyczka „PNB Galeria
	// i Wydarzenia" podmienia ją przy aktywacji na premium. Events NIE powstaje
	// tu celowo — kalendarz istnieje dopiero z wtyczką.
	foreach ( $strony as $slug => $dane ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $dane[0],
			'post_name'    => $slug,
			'post_content' => $dane[1],
		) );
	}
	// strona główna statyczna → Home (tylko gdy klient nie ma już własnej ustawionej)
	if ( 'page' !== get_option( 'show_on_front' ) ) {
		$home = get_page_by_path( 'home' );
		if ( $home ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home->ID );
		}
	}
	// ładne adresy (/services/ zamiast ?p=5) tylko gdy klient ma surowe domyślne
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	flush_rewrite_rules();
}

/**
 * Reusable paw SVG.
 *
 * @param string $class CSS class.
 * @return string
 */
function catsnboard_paw_svg( $class = 'paw' ) {
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 48 48" fill="var(--coral)" aria-hidden="true"><ellipse cx="24" cy="30" rx="12" ry="10"/><ellipse cx="11" cy="20" rx="4.4" ry="6"/><ellipse cx="19" cy="13" rx="4.4" ry="6"/><ellipse cx="29" cy="13" rx="4.4" ry="6"/><ellipse cx="37" cy="20" rx="4.4" ry="6"/></svg>';
}

/**
 * Fallback menu when no 'primary' menu is assigned — links to the real subpages
 * by slug so the theme works out of the box.
 */
function catsnboard_default_menu() {
	$items = array(
		'/'             => 'Home',
		'/services/'    => 'Services',
		'/pricing/'     => 'Pricing',
		'/our-staff/'   => 'Our Team',
		'/our-location/' => 'Our Facilities',
	);
	// sekcje wtyczki tylko gdy ich strony istnieją (bez wtyczki brak Gallery/Events w menu)
	if ( get_page_by_path( 'gallery' ) ) {
		$items['/gallery/'] = 'Gallery';
	}
	if ( get_page_by_path( 'events' ) ) {
		$items['/events/'] = 'Events';
	}
	$items['/contact/'] = 'Contact';
	echo '<div class="menu">';
	foreach ( $items as $path => $label ) {
		echo '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</div>';
}

/**
 * Renders one of the 8 service tiles (icon SVG + label). Shared by front-page
 * and page-services.
 *
 * @return array List of [svg, label] pairs.
 */
function catsnboard_services() {
	return array(
		array( '<svg viewBox="0 0 24 24"><path d="M3 9l9-6 9 6v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21V12h6v9"/></svg>', catsnboard_txt( 'services.tile.boarding', 'Boarding' ) ),
		array( '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></svg>', catsnboard_txt( 'services.tile.daycare', 'Daycare' ) ),
		array( '<svg viewBox="0 0 24 24"><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><circle cx="12" cy="15" r="2"/></svg>', catsnboard_txt( 'services.tile.sitting', 'Cat sitting in your home' ) ),
		array( '<svg viewBox="0 0 24 24"><path d="M9 22V12h6v10"/><path d="M2 10.6 12 3l10 7.6"/><circle cx="19" cy="6" r="2.4"/></svg>', catsnboard_txt( 'services.tile.visits', 'Home visits' ) ),
		array( '<svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="5"/><path d="M8.5 6.5 7 3M15.5 6.5 17 3M9.5 15.5 8 18M14.5 15.5 16 18"/></svg>', catsnboard_txt( 'services.tile.play', 'Play sessions' ) ),
		array( '<svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></svg>', catsnboard_txt( 'services.tile.relocation', 'Animal relocation' ) ),
		array( '<svg viewBox="0 0 24 24"><path d="M22 10 12 3 2 10"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg>', catsnboard_txt( 'services.tile.socialising', 'Kitten socialising' ) ),
		array( '<svg viewBox="0 0 24 24"><path d="M18 2l4 4-9 9-4 1 1-4z"/><path d="M2 22l6-2-4-4z"/></svg>', catsnboard_txt( 'services.tile.medical', 'Simple medical procedures' ) ),
	);
}

/**
 * The 12 team members (invented names + cat avatars).
 *
 * @return array List of [name, avatar-file].
 */
function catsnboard_team() {
	return array(
		array( 'Ola', 'opiekun-1.jpg' ), array( 'Tomek', 'opiekun-2.jpg' ),
		array( 'Kasia', 'opiekun-3.jpg' ), array( 'Marek', 'opiekun-4.jpg' ),
		array( 'Ania', 'opiekun-5.jpg' ), array( 'Bartek', 'opiekun-6.jpg' ),
		array( 'Julia', 'opiekun-7.jpg' ), array( 'Paweł', 'opiekun-8.jpg' ),
		array( 'Magda', 'opiekun-9.jpg' ), array( 'Filip', 'opiekun-10.jpg' ),
		array( 'Natalia', 'opiekun-11.jpg' ), array( 'Zosia', 'opiekun-12.jpg' ),
	);
}

/**
 * Register the custom "catsnboard" block category so the Home block is easy to
 * find in the inserter.
 *
 * @param array $categories Existing block categories.
 * @return array
 */
function catsnboard_block_categories( $categories ) {
	return array_merge(
		array(
			array(
				'slug'  => 'catsnboard',
				'title' => __( "Cats'N'Board", 'catsnboard' ),
				'icon'  => null,
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'catsnboard_block_categories' );

/**
 * Register theme blocks (built with @wordpress/scripts into /build).
 * The "Home" block renders the whole front page from editable attributes;
 * the "Services" and "Contact" blocks do the same for their subpages.
 */
function catsnboard_register_blocks() {
	foreach ( array( 'home', 'services', 'contact', 'about', 'pricing', 'staff', 'location', 'gallery' ) as $block ) {
		$dir = get_template_directory() . '/build/' . $block;
		if ( is_dir( $dir ) ) {
			register_block_type( $dir );
		}
	}
}
add_action( 'init', 'catsnboard_register_blocks' );

/**
 * Passes the theme's image base URL to the `catsnboard/gallery` block editor
 * script, so empty mosaic slots can preview the theme's default cat photos
 * (same ones render.php falls back to on the front) instead of a plain grey
 * placeholder. Handle is WP core's auto-generated one for a block.json
 * "editorScript" field: `{namespace}-{block}-editor-script`
 * (see generate_block_asset_handle() in wp-includes/blocks.php).
 */
function catsnboard_gallery_editor_assets() {
	wp_localize_script(
		'catsnboard-gallery-editor-script',
		'catsnboardGallery',
		array(
			'imgBase' => get_template_directory_uri() . '/assets/img/',
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'catsnboard_gallery_editor_assets' );

/**
 * Renders the training bar section (shared by front-page + page-services).
 */
function catsnboard_training_bar() {
	?>
	<section class="training">
	  <div class="wrap">
	    <div class="tgr">
	      <div>
	        <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'training.eyebrow', 'Communicate with your cat, play and relax' ) ); ?></span>
	        <h3 class="round splitw"><?php echo esc_html( catsnboard_txt( 'training.title', 'Kitten training' ) ); ?></h3>
	        <p data-rev><?php echo esc_html( catsnboard_txt( 'training.lead', 'Gentle kitten training, individual consultations and calm socialisation sessions — helping your cat feel safe, curious and happy.' ) ); ?></p>
	        <ul class="pts">
	          <li data-rev><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( catsnboard_txt( 'training.pt1', 'One-to-one consultations at your pace' ) ); ?></li>
	          <li data-rev><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( catsnboard_txt( 'training.pt2', 'Calm, positive socialisation sessions' ) ); ?></li>
	          <li data-rev><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg><?php echo esc_html( catsnboard_txt( 'training.pt3', 'Confidence-building play & enrichment' ) ); ?></li>
	        </ul>
	      </div>
	      <div class="tpaw" data-rev>
	        <img class="graded" id="trainImg" src="<?php echo esc_url( catsnboard_img( 'kot-9.jpg' ) ); ?>" alt="A calm cat during a gentle training session" />
	        <span class="badge"><?php echo catsnboard_paw_svg( '' ); // phpcs:ignore ?></span>
	      </div>
	    </div>
	  </div>
	</section>
	<?php
}

/**
 * META DESCRIPTION + OPEN GRAPH (SEO/social) — dodane 2026-07-09 (audyt: brak na wszystkich stronach).
 * Motyw sam generuje (bez pluginu SEO). Opis per typ strony; OG dla ładnego udostępniania na FB/social.
 * Priorytet 5 na wp_head — po title-tag, przed resztą.
 */
function catsnboard_meta_seo() {
	$sep  = ' — ';
	$marka = get_bloginfo( 'name' );

	// Opis zależny od strony (front, wydarzenia, galeria, kontakt, reszta).
	if ( is_front_page() ) {
		$opis = catsnboard_txt( 'seo.home', 'Cats\'N\'Board — a warm, calm second home for your cat in Żoliborz, Warsaw. Boarding, daycare and gentle care by people who love cats.' );
	} elseif ( is_singular( 'pnb_wydarzenie' ) ) {
		$op = wp_strip_all_tags( (string) get_post_field( 'post_content', get_the_ID() ) );
		$opis = $op ? mb_substr( trim( $op ), 0, 155 ) : catsnboard_txt( 'seo.events', 'Adoption days, cat classes and open days at Cats\'N\'Board.' );
	} elseif ( is_page() ) {
		$slug = get_post_field( 'post_name', get_the_ID() );
		$mapa = array(
			'gallery'  => 'See our cats at play, nap and cuddle time — the gallery of Cats\'N\'Board pension in Warsaw.',
			'events'   => 'Adoption days, cat classes and open days — upcoming events at Cats\'N\'Board.',
			'contact'  => 'Get in touch with Cats\'N\'Board — call or write to book boarding, daycare or a visit.',
			'services' => 'Boarding, daycare and gentle cat care at Cats\'N\'Board in Żoliborz, Warsaw.',
			'pricing'  => 'Transparent pricing for cat boarding and daycare at Cats\'N\'Board.',
		);
		$opis = isset( $mapa[ $slug ] ) ? $mapa[ $slug ] : catsnboard_txt( 'seo.home', $marka . ' — a warm second home for your cat in Warsaw.' );
	} else {
		$opis = catsnboard_txt( 'seo.home', $marka . ' — a warm second home for your cat in Warsaw.' );
	}
	$opis = trim( preg_replace( '/\s+/u', ' ', $opis ) );

	// Obrazek OG: featured jeśli jest, inaczej Site Icon / logo.
	$og_img = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$og_img = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	} elseif ( function_exists( 'get_site_icon_url' ) && get_site_icon_url() ) {
		$og_img = get_site_icon_url( 512 );
	}
	$tytul = wp_get_document_title();

	echo "\n<!-- SEO meta (catsnboard) -->\n";
	echo '<meta name="description" content="' . esc_attr( $opis ) . '">' . "\n";
	echo '<meta property="og:type" content="' . ( is_singular() && ! is_front_page() ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $tytul ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $opis ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( ( is_singular() || is_page() ) ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ) ) ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $marka ) . '">' . "\n";
	if ( $og_img ) {
		echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	} else {
		echo '<meta name="twitter:card" content="summary">' . "\n";
	}
}
add_action( 'wp_head', 'catsnboard_meta_seo', 5 );
