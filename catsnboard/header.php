<?php
/**
 * Header — <head>, nav bar and slide-in menu panel.
 *
 * @package catsnboard
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php /* Fonty ładowane lokalnie (assets/fonts/) — preconnect do Google usunięty, paczka działa offline. */ ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="cur" id="cur" aria-hidden="true"></div>

<!-- ═══ NAV ═══ -->
<nav>
  <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand" data-c>
    <?php echo catsnboard_paw_svg( 'paw' ); // phpcs:ignore ?>
    <b>Cats 'N' Board</b>
  </a>
  <div class="nav-mid">
    <?php
    $h_tel   = catsnboard_kontakt( 'tel' );
    $h_email = catsnboard_kontakt( 'email' );
    ?>
    <?php if ( '' !== $h_tel ) : ?>
    <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $h_tel ) ); ?>" data-c>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.98.36 1.93.7 2.83a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.83.7A2 2 0 0 1 22 16.92z"/></svg>
      <?php echo esc_html( $h_tel ); ?>
    </a>
    <?php endif; ?>
    <?php if ( '' !== $h_email ) : ?>
    <a href="mailto:<?php echo esc_attr( sanitize_email( $h_email ) ); ?>" data-c>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
      <?php echo esc_html( $h_email ); ?>
    </a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <?php
    // Przełącznik języka na pasku (widoczny bez otwierania menu). Milczy gdy PL wyłączony / WPML rządzi.
    if ( function_exists( 'pnb_lang_switch_html' ) ) {
        echo pnb_lang_switch_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapuje w środku
    }
    ?>
    <div class="socials">
      <a href="#" aria-label="Facebook" data-c><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/></svg></a>
      <a href="#" aria-label="Instagram" data-c><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg></a>
      <a href="#" aria-label="TikTok" data-c><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 3c.3 2.1 1.6 3.8 3.5 4.2v2.7c-1.3.1-2.6-.3-3.7-1v6.1c0 3.3-2.7 5.9-5.9 5.5-2.6-.3-4.6-2.5-4.6-5.1 0-3 2.6-5.4 5.6-5v2.8c-.4-.1-.8-.2-1.2-.1-1.2.1-2.1 1.2-1.9 2.4.1 1.1 1.1 1.9 2.2 1.8 1.2-.1 2-1.1 2-2.3V3h3.9z"/></svg></a>
    </div>
    <button class="burger" id="burger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
</nav>

<div class="menu-scrim" id="menuScrim"></div>
<?php
/*
 * Slide-in panel from the right (~30vw). Menu items are REAL subpage URLs
 * (wp_nav_menu -> 'primary'), NOT anchors. If no menu is assigned in the
 * admin, we fall back to a slug-based menu so the theme works immediately.
 */
if ( has_nav_menu( 'primary' ) ) {
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => 'div',
			'container_id'   => 'menu',
			'container_class' => 'menu',
			'items_wrap'     => '%3$s',
			'depth'          => 1,
			'link_before'    => '',
		)
	);
} else {
	echo '<div class="menu" id="menu">';
	$items = array(
		'/'              => 'Home',
		'/services/'     => 'Services',
		'/pricing/'      => 'Pricing',
		'/our-staff/'    => 'Our Team',
		'/our-location/' => 'Our Facilities',
	);
	// sekcje wtyczki tylko gdy ich strony istnieją (bez wtyczki brak Gallery/Events)
	if ( get_page_by_path( 'gallery' ) ) {
		$items['/gallery/'] = 'Gallery';
	}
	if ( get_page_by_path( 'events' ) ) {
		$items['/events/'] = 'Events';
	}
	$items['/contact/'] = 'Contact';
	foreach ( $items as $path => $label ) {
		echo '<a href="' . esc_url( home_url( $path ) ) . '" data-c>' . esc_html( $label ) . '</a>';
	}
	// przełącznik PL/EN także w menu fallbackowym (filtr wp_nav_menu_items go nie łapie)
	if ( function_exists( 'pnb_lang_switch_html' ) ) {
		$sw = pnb_lang_switch_html();
		if ( $sw ) {
			echo '<div class="menu-lang">' . $sw . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pnb_lang_switch_html escapuje w środku
		}
	}
	echo '</div>';
}
?>
