<?php
/**
 * Minimalny szablon singla wydarzenia — SIATKA BEZPIECZEŃSTWA pluginu.
 * Używany TYLKO gdy motyw nie ma żadnego single*.php (WP spadł na index.php,
 * a tam the_excerpt() — filtr the_content nie miałby gdzie zadziałać).
 * Cały layout buduje filtr the_content (pnb_kalendarz_render_single) — tu goła pętla.
 *
 * @package pnb-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	the_content();
endwhile;

get_footer();
