<?php
/**
 * MODUŁ — rejestracja bloku Gutenberg `pnb/galeria`.
 *
 * Blok BEZ builda (czysty JS: window.wp.* + createElement) — plugin nie ma npm/wp-scripts.
 * Edytor: teksty hero + zarządzanie zdjęciami. Front: render.php woła gotową taśmę (pnb_galeria_render).
 * Rejestracja z katalogu blocks/galeria (block.json), skrypt edytora podpięty ręcznie (editorScript
 * w block.json wymaga asset.php z builda — którego nie ma).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Kategoria „catsnboard" dla naszych bloków (spójnie z motywem; jeśli motyw już ją dodał, WP scali). */
add_filter( 'block_categories_all', function ( $kat ) {
	foreach ( $kat as $k ) {
		if ( isset( $k['slug'] ) && 'catsnboard' === $k['slug'] ) {
			return $kat; // już jest (motyw dodał) — nie dubluj
		}
	}
	array_unshift( $kat, array(
		'slug'  => 'catsnboard',
		'title' => __( "Cats'N'Board", 'pnb-toolkit' ),
		'icon'  => 'pets',
	) );
	return $kat;
} );

/* Skrypt edytora — czysty JS, zależności z rdzenia WP (wp-blocks, wp-element, wp-block-editor…). */
add_action( 'init', function () {
	$sciezka = PNB_TOOLKIT_DIR . 'blocks/galeria/editor.js';
	$url     = plugins_url( 'blocks/galeria/editor.js', PNB_TOOLKIT_DIR . 'pnb-blocks.php' );
	wp_register_script(
		'pnb-galeria-blok-editor',
		$url,
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		file_exists( $sciezka ) ? (string) filemtime( $sciezka ) : PNB_TOOLKIT_VERSION,
		true
	);

	// Tłumaczenia JS edytora: WordPress ładuje languages/pnb-toolkit-pl_PL-{md5(editor.js)}.json.
	// Bez pliku JSON napisy zostają po angielsku (default) — sam wpis wystarcza jako infrastruktura i18n.
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'pnb-galeria-blok-editor', 'pnb-toolkit', PNB_TOOLKIT_DIR . 'languages' );
	}

	// styl edytora (podgląd hero/kafelków w blokowym edytorze)
	$css_sciezka = PNB_TOOLKIT_DIR . 'blocks/galeria/editor.css';
	$css_url     = plugins_url( 'blocks/galeria/editor.css', PNB_TOOLKIT_DIR . 'pnb-blocks.php' );
	wp_register_style(
		'pnb-galeria-blok-editor',
		$css_url,
		array(),
		file_exists( $css_sciezka ) ? (string) filemtime( $css_sciezka ) : PNB_TOOLKIT_VERSION
	);

	// rejestracja bloku z metadanych (render.php dołączony przez block.json? nie — podajemy render_callback)
	register_block_type(
		PNB_TOOLKIT_DIR . 'blocks/galeria',
		array(
			'editor_script'   => 'pnb-galeria-blok-editor',
			'editor_style'    => 'pnb-galeria-blok-editor',
			'render_callback' => 'pnb_galeria_blok_render',
		)
	);
} );

/* Render front — deleguje do render.php bloku (który woła gotową taśmę pnb_galeria_render). */
function pnb_galeria_blok_render( $attributes, $content, $block ) {
	ob_start();
	include PNB_TOOLKIT_DIR . 'blocks/galeria/render.php';
	return ob_get_clean();
}

/* Localize: przekaż do JS edytora URL-e miniatur ZAPISANYCH zdjęć (żeby kafelki + licznik pokazały się
 * od razu przy otwarciu edytora, bez wybierania na nowo). Klucz = ID załącznika, wartość = URL miniatury.
 *
 * ⚠️ NAPRAWA (zgłoszenie: „sekcji zdjęć nie da się edytować"): używamy wp_add_inline_script(...,'before')
 * jak w blok-wydarzenia.php — NIE wp_localize_script. Skrypt edytora bloku jest ładowany przez mechanizm
 * bloków (editor_script z register_block_type); dane wstrzyknięte inline PRZED skryptem docierają pewnie,
 * podczas gdy kolejka localize przy tym trybie ładowania bywa pomijana → window.pnbGaleriaBlok = undefined
 * → media = {} (kafelki bez miniatur). Zbieramy ID zarówno z bloku w treści, jak i z opcji pnb_galeria_zdjecia
 * (fallback: gdyby atrybut jeszcze się nie zdeserializował), żeby na 100% mieć URL-e wszystkich zdjęć. */
add_action( 'enqueue_block_editor_assets', function () {
	$post = get_post();
	if ( ! $post || ! has_block( 'pnb/galeria', $post ) ) {
		return;
	}

	// 1) ID z atrybutów bloku (źródło główne — to je edytuje klient).
	//    ⚠️ OBA zestawy: taśma (imageIds) ORAZ Moments (momentsIds) — bez momentsIds podgląd
	//    sekcji Moments pokazywał puste kafelki „#72" zamiast miniaturek (bug wykryty w testach).
	$ids = array();
	foreach ( parse_blocks( $post->post_content ) as $b ) {
		if ( 'pnb/galeria' === $b['blockName'] ) {
			foreach ( array( 'imageIds', 'momentsIds' ) as $klucz ) {
				if ( ! empty( $b['attrs'][ $klucz ] ) ) {
					foreach ( (array) $b['attrs'][ $klucz ] as $id ) {
						$ids[] = (int) $id;
					}
				}
			}
		}
	}
	// 2) Fallback: opcja pnb_galeria_zdjecia (ta sama pula co render) — na wypadek pustego atrybutu.
	if ( empty( $ids ) ) {
		$opcja = get_option( 'pnb_galeria_zdjecia', array() );
		if ( function_exists( 'pnb_galeria_sanityzuj_ids' ) ) {
			$opcja = pnb_galeria_sanityzuj_ids( $opcja );
		}
		$ids = array_map( 'intval', (array) $opcja );
	}

	$media = array();
	foreach ( array_unique( $ids ) as $id ) {
		$url = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( $url ) {
			$media[ $id ] = $url;
		}
	}

	wp_add_inline_script(
		'pnb-galeria-blok-editor',
		'window.pnbGaleriaBlok = ' . wp_json_encode( array( 'media' => (object) $media ) ) . ';',
		'before'
	);
} );
