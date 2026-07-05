<?php
/**
 * Render bloku `pnb/wydarzenia` na froncie.
 *
 * Front = GOTOWA galeria wydarzeń (pnb_kalendarz_render() z modules/kalendarz.php). Teksty hero z bloku
 * nadpisują domyślne przez filtr pnb_txt_wynik (ten sam co panel Teksty). Nie duplikujemy renderu.
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
		'heroEyebrow' => "Cats'N'Board · what's on",
		'heroTitle'   => 'Save the date.',
		'heroLead1'   => 'Adoption days, cat classes and open doors at the pension.',
		'heroLead2'   => 'Sign up below — spots are limited.',
		'heroImageId' => 0,
		'mapEyebrow'  => 'Where to find us',
		'mapTitle'    => 'Come say <em>hi</em>.',
		'mapLead'     => 'All events happen at the pension — a quiet street in the heart of Żoliborz.',
		'mapAddress'  => '',
		'mapLabel'    => '',
	)
);

// Teksty z bloku → nadpisują domyślne w renderze (przez ten sam filtr co panel Teksty).
$teksty_bloku = array(
	'events.hero.eyebrow' => $a['heroEyebrow'],
	'events.hero.title'   => $a['heroTitle'],
	'events.hero.lead1'   => $a['heroLead1'],
	'events.hero.lead2'   => $a['heroLead2'],
	'events.map.eyebrow'  => $a['mapEyebrow'],
	'events.map.title'    => $a['mapTitle'],
	'events.map.lead'     => $a['mapLead'],
	'events.map.address'  => $a['mapAddress'],
	'events.map.label'    => $a['mapLabel'],
);
$override_txt = function ( $wart, $klucz ) use ( $teksty_bloku ) {
	return isset( $teksty_bloku[ $klucz ] ) && '' !== $teksty_bloku[ $klucz ] ? $teksty_bloku[ $klucz ] : $wart;
};
add_filter( 'pnb_txt_wynik', $override_txt, 10, 2 );

// Zdjęcie hero z bloku → przez istniejący filtr renderu (pnb_kalendarz_hero_id). 0 = zostaw domyślne.
$hero_id = (int) $a['heroImageId'];
$override_hero = null;
if ( $hero_id > 0 ) {
	$override_hero = function () use ( $hero_id ) {
		return $hero_id;
	};
	add_filter( 'pnb_kalendarz_hero_id', $override_hero );
}

$html = function_exists( 'pnb_kalendarz_render' ) ? pnb_kalendarz_render() : '';

remove_filter( 'pnb_txt_wynik', $override_txt, 10 );
if ( $override_hero ) {
	remove_filter( 'pnb_kalendarz_hero_id', $override_hero );
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'pnb-wydarzenia-blok' ) );
echo '<div ' . $wrapper . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pnb_kalendarz_render escapuje wewnątrz
