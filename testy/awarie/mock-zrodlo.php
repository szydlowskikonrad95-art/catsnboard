<?php
/**
 * ATRAPA ŹRÓDŁA do testów awarii importera (router dla `php -S`).
 *
 * Uruchom na maszynie z WordPressem (test-awarie.php mówi kiedy):
 *   php -S 127.0.0.1:9999 testy/awarie/mock-zrodlo.php
 *
 * Ścieżki:
 *   /pad   → HTTP 500 (źródło padło — test circuit breakera)
 *   /pusta → HTTP 200 bez wydarzeń (test ochrony przed pustym źródłem)
 *   /malo  → HTTP 200 z 2 wydarzeniami (test alertu podejrzanego spadku);
 *            treść czyta z /tmp/pnb-mock-malo.html — generuje ją test-awarie.php
 *            z PRAWDZIWYCH source_id obecnych w bazie testowego WordPressa.
 */

$u = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

if ( false !== strpos( $u, 'pad' ) ) {
	http_response_code( 500 );
	echo 'awaria zrodla (celowa — test breakera)';
	exit;
}
if ( false !== strpos( $u, 'pusta' ) ) {
	echo '<html><body>lista bez wydarzen (celowo pusta — test ochrony)</body></html>';
	exit;
}
if ( false !== strpos( $u, 'malo' ) ) {
	$plik = sys_get_temp_dir() . '/pnb-mock-malo.html';
	echo file_exists( $plik ) ? file_get_contents( $plik ) : '<html><body>najpierw odpal test-awarie.php (generuje ten plik)</body></html>';
	exit;
}
echo 'mock zrodla pnb — uzyj /pad /pusta /malo';
