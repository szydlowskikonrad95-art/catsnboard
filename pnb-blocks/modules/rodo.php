<?php
/* Moduł RODO: wpięcie zapisów gości (CPT pnb_zapis) w natywne narzędzia prywatności WordPressa.
   Dzięki temu żądanie „pokaż / usuń moje dane" obsługuje się z Narzędzia → Eksport/Usuwanie danych
   osobowych, po adresie e-mail — WordPress znajduje zapisy gościa we WSZYSTKICH wydarzeniach naraz
   (wcześniej trzeba było przeglądać każde wydarzenie osobno). Dane zapisu: post_title = imię,
   meta _pnb_zapis_email / _pnb_zapis_tel / _pnb_zapis_wydarzenie. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Zwraca ID zapisów gościa o danym e-mailu (dowolne wydarzenie). */
function pnb_rodo_zapisy_po_emailu( $email ) {
	return get_posts( array(
		'post_type'      => 'pnb_zapis',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array( array( 'key' => '_pnb_zapis_email', 'value' => $email ) ),
	) );
}

/* EKSPORTER: Narzędzia → Eksport danych osobowych → podaj e-mail. */
add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['pnb-zapisy'] = array(
		'exporter_friendly_name' => __( 'Zapisy na wydarzenia (Cats\'N\'Board)', 'pnb-toolkit' ),
		'callback'               => 'pnb_rodo_eksporter',
	);
	return $exporters;
} );

function pnb_rodo_eksporter( $email, $page = 1 ) {
	$dane = array();
	foreach ( pnb_rodo_zapisy_po_emailu( $email ) as $id ) {
		$event_id = (int) get_post_meta( $id, '_pnb_zapis_wydarzenie', true );
		$dane[] = array(
			'group_id'    => 'pnb-zapisy',
			'group_label' => __( 'Zapisy na wydarzenia', 'pnb-toolkit' ),
			'item_id'     => 'pnb-zapis-' . $id,
			'data'        => array(
				array( 'name' => __( 'Imię', 'pnb-toolkit' ), 'value' => get_the_title( $id ) ),
				array( 'name' => __( 'E-mail', 'pnb-toolkit' ), 'value' => get_post_meta( $id, '_pnb_zapis_email', true ) ),
				array( 'name' => __( 'Telefon', 'pnb-toolkit' ), 'value' => get_post_meta( $id, '_pnb_zapis_tel', true ) ),
				array( 'name' => __( 'Wydarzenie', 'pnb-toolkit' ), 'value' => $event_id ? get_the_title( $event_id ) : '—' ),
				array( 'name' => __( 'Data zapisu', 'pnb-toolkit' ), 'value' => get_the_date( 'Y-m-d H:i', $id ) ),
			),
		);
	}
	return array( 'data' => $dane, 'done' => true );
}

/* ERASER: Narzędzia → Usuwanie danych osobowych → podaj e-mail (kasuje zapisy gościa). */
add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['pnb-zapisy'] = array(
		'eraser_friendly_name' => __( 'Zapisy na wydarzenia (Cats\'N\'Board)', 'pnb-toolkit' ),
		'callback'             => 'pnb_rodo_eraser',
	);
	return $erasers;
} );

function pnb_rodo_eraser( $email, $page = 1 ) {
	$usuniete = 0;
	foreach ( pnb_rodo_zapisy_po_emailu( $email ) as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			$usuniete++;
		}
	}
	return array(
		'items_removed'  => $usuniete > 0,
		'items_retained' => false,
		'messages'       => $usuniete ? array( sprintf( __( 'Usunięto zapisów: %d', 'pnb-toolkit' ), $usuniete ) ) : array(),
		'done'           => true,
	);
}
