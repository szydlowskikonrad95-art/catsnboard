<?php
/**
 * Render bloku `pnb/galeria` na froncie.
 *
 * Blok trzyma zdjęcia w atrybucie imageIds i teksty hero w atrybutach. Front = GOTOWA taśma kinowa
 * (pnb_galeria_render() z modules/galeria.php) — nie duplikujemy jej. Spięcie jednego źródła:
 * zdjęcia bloku mają pierwszeństwo; jeśli blok ma imageIds, używamy ich (bez trwałego nadpisywania opcji,
 * żeby nie mieszać z panelem admina — podajemy pulę bezpośrednio przez filtr).
 *
 * @var array    $attributes Atrybuty bloku.
 * @var string   $content    Treść wewnętrzna (nieużywana).
 * @var WP_Block $block      Instancja bloku.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a = wp_parse_args(
	$attributes,
	array(
		'heroEyebrow'   => 'Gallery',
		'heroTitle'     => 'Our days in frames.',
		'heroHint'      => 'Scroll — photos ride. Click — a frame grows.',
		'heroImageId'   => 0,
		'hintKeep'      => '— keep scrolling —',
		'midTitle'      => 'Moments that <em>stay</em>.',
		'ctaTitle'      => 'They look even better <em>in person</em>.',
		'ctaLead'       => 'Come say hi — your cat will love it here.',
		'ctaBtn'        => 'Book a visit',
		'imageIds'      => array(),
		'momentsIds'    => array(),
	)
);

$ids_bloku     = array_values( array_filter( array_map( 'absint', (array) $a['imageIds'] ) ) );
$moments_bloku = array_values( array_filter( array_map( 'absint', (array) $a['momentsIds'] ) ) );

// Zdjęcia TAŚMY: blok ma pierwszeństwo. Podajemy je do renderu przez filtr (bez trwałego zapisu opcji —
// panel admina i blok to dwa wejścia do tej samej galerii; blok wygrywa tylko na SWOJEJ stronie).
if ( $ids_bloku ) {
	$override = function () use ( $ids_bloku ) {
		return $ids_bloku;
	};
	add_filter( 'pnb_galeria_zrodlo_zdjec', $override );
}

// Zdjęcia RZEK „Moments that stay": OSOBNY zestaw (momentsIds). Pusty = render zrobi fallback na taśmę,
// więc filtr podpinamy TYLKO gdy blok faktycznie ma osobne zdjęcia Moments.
$override_moments = null;
if ( $moments_bloku ) {
	$override_moments = function () use ( $moments_bloku ) {
		return $moments_bloku;
	};
	add_filter( 'pnb_galeria_moments_zdjec', $override_moments );
}

// Teksty z bloku → nadpisują domyślne w renderze (przez ten sam filtr co panel Teksty).
// Gwiazdki *…* od RichText (italic) zamieniamy na <em>…</em> — spójnie z panelem Teksty (pnb_zloz_em).
$em = function ( $t ) {
	return function_exists( 'pnb_zloz_em' ) ? pnb_zloz_em( $t ) : $t;
};
$teksty_bloku = array(
	'gallery.hero.eyebrow'   => $a['heroEyebrow'],
	'gallery.hero.title'     => $a['heroTitle'],
	'gallery.hint.top'       => $a['heroHint'],
	'gallery.hint.keep'      => $a['hintKeep'],
	'gallery.mid.title'      => $em( $a['midTitle'] ),
	'gallery.cta.title'      => $em( $a['ctaTitle'] ),
	'gallery.cta.lead'       => $a['ctaLead'],
	'gallery.cta.btn'        => $a['ctaBtn'],
);
$override_txt = function ( $wart, $klucz ) use ( $teksty_bloku ) {
	return isset( $teksty_bloku[ $klucz ] ) && '' !== $teksty_bloku[ $klucz ] ? $teksty_bloku[ $klucz ] : $wart;
};
add_filter( 'pnb_txt_wynik', $override_txt, 10, 2 );

// Zdjęcie hero galerii z bloku → przez filtr renderu. 0 = bez zdjęcia (gradient jak dotąd).
$ghero_id = (int) $a['heroImageId'];
$override_ghero = null;
if ( $ghero_id > 0 ) {
	$override_ghero = function () use ( $ghero_id ) {
		return $ghero_id;
	};
	add_filter( 'pnb_galeria_hero_id', $override_ghero );
}

$html = function_exists( 'pnb_galeria_render' ) ? pnb_galeria_render() : '';

if ( $override_ghero ) {
	remove_filter( 'pnb_galeria_hero_id', $override_ghero );
}

// sprzątanie filtrów (żeby nie wyciekły na inne bloki/render na tej samej stronie)
if ( $ids_bloku ) {
	remove_filter( 'pnb_galeria_zrodlo_zdjec', $override );
}
if ( $override_moments ) {
	remove_filter( 'pnb_galeria_moments_zdjec', $override_moments );
}
remove_filter( 'pnb_txt_wynik', $override_txt, 10 );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'pnb-galeria-blok' ) );
echo '<div ' . $wrapper . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pnb_galeria_render escapuje wewnątrz
