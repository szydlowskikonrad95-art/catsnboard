<?php
/**
 * MODUŁ — rejestracja bloku Gutenberg `pnb/wydarzenia`.
 *
 * Blok BEZ builda (czysty JS). Edytor: teksty hero + panel zarządzania wydarzeniami (HTML z PHP).
 * Front: render.php woła gotową galerię wydarzeń (pnb_kalendarz_render). Wzór 1:1 z blok-galeria.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Skrypt + styl edytora, rejestracja bloku. */
add_action( 'init', function () {
	$sciezka = PNB_TOOLKIT_DIR . 'blocks/wydarzenia/editor.js';
	$url     = plugins_url( 'blocks/wydarzenia/editor.js', PNB_TOOLKIT_DIR . 'pnb-blocks.php' );
	wp_register_script(
		'pnb-wydarzenia-blok-editor',
		$url,
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		file_exists( $sciezka ) ? (string) filemtime( $sciezka ) : PNB_TOOLKIT_VERSION,
		true
	);

	// Tłumaczenia JS edytora: WordPress ładuje languages/pnb-toolkit-pl_PL-{md5(editor.js)}.json.
	// Bez pliku JSON napisy zostają po angielsku (default) — sam wpis wystarcza jako infrastruktura i18n.
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'pnb-wydarzenia-blok-editor', 'pnb-toolkit', PNB_TOOLKIT_DIR . 'languages' );
	}

	$css_sciezka = PNB_TOOLKIT_DIR . 'blocks/wydarzenia/editor.css';
	$css_url     = plugins_url( 'blocks/wydarzenia/editor.css', PNB_TOOLKIT_DIR . 'pnb-blocks.php' );
	wp_register_style(
		'pnb-wydarzenia-blok-editor',
		$css_url,
		array(),
		file_exists( $css_sciezka ) ? (string) filemtime( $css_sciezka ) : PNB_TOOLKIT_VERSION
	);

	register_block_type(
		PNB_TOOLKIT_DIR . 'blocks/wydarzenia',
		array(
			'editor_script'   => 'pnb-wydarzenia-blok-editor',
			'editor_style'    => 'pnb-wydarzenia-blok-editor',
			'render_callback' => 'pnb_wydarzenia_blok_render',
		)
	);
} );

/* Render front — deleguje do render.php bloku (który woła gotową galerię pnb_kalendarz_render). */
function pnb_wydarzenia_blok_render( $attributes, $content, $block ) {
	ob_start();
	include PNB_TOOLKIT_DIR . 'blocks/wydarzenia/render.php';
	return ob_get_clean();
}

/* Localize: przekaż HTML panelu zarządzania wydarzeniami (lista + „Dodaj" + „Edytuj") do edytora.
 * Panel to głównie lista do odczytu + linki (klik → ekrany WP), więc statyczny HTML w podglądzie wystarcza. */
add_action( 'enqueue_block_editor_assets', function () {
	$post = get_post();
	if ( ! $post || ! has_block( 'pnb/wydarzenia', $post ) ) {
		return;
	}
	$panel = '';
	if ( function_exists( 'pnb_wydarzenia_panel_wbudowany' ) ) {
		// ⚠️ KRYTYCZNE (debug 404 „item doesn't exist"): panel woła WP_Query + the_post(), co NADPISUJE
		// globalny $post. Ten hook biegnie ZANIM edytor (post.php) używa $post → wp_reset_postdata()
		// w panelu przywraca $post do GŁÓWNEGO zapytania (puste w admin) → post.php widzi !$post → 404.
		// Ratunek: zachowaj i PRZYWRÓĆ globalny $post ręcznie wokół panelu (WP docs: backup/restore $post).
		global $post;
		$zachowany = $post;
		ob_start();
		pnb_wydarzenia_panel_wbudowany();
		$panel = ob_get_clean();
		$post = $zachowany; // przywróć stronę edytowaną (156), nie zostaw stanu z WP_Query panelu
		if ( $post ) {
			setup_postdata( $post );
		}
	}
	// domyślne zdjęcie hero dla podglądu w edytorze. KOLEJNOŚĆ (2026-07-09):
	// stała/filtr → stałe tło z panelu (wybór klienta) → brak (sam welon marki).
	// ⚠️ NIE bierzemy featured wydarzenia (plakaty scrapowane mają własny tekst → nachodzi na hero).
	$hero_default = '';
	$hero_id = defined( 'PNB_EVENTS_HERO_ID' ) ? (int) PNB_EVENTS_HERO_ID : 0;
	$hero_id = (int) apply_filters( 'pnb_kalendarz_hero_id', $hero_id );
	if ( ! $hero_id ) {
		$hero_id = (int) get_option( 'pnb_events_hero_id', 0 ); // stałe tło z panelu
	}
	if ( $hero_id ) {
		$hero_default = wp_get_attachment_image_url( $hero_id, 'large' );
	}

	wp_add_inline_script(
		'pnb-wydarzenia-blok-editor',
		'window.pnbWydarzeniaBlok = ' . wp_json_encode( array(
			'panel'       => $panel,
			'heroDefault' => $hero_default ? $hero_default : '',
		) ) . ';',
		'before'
	);
} );
