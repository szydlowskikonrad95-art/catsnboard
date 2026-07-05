<?php
/**
 * Footer — 4 columns, lightbox markup, wp_footer().
 *
 * @package catsnboard
 */
?>
<!-- ═══ FOOTER ═══ -->
<footer>
  <div class="wrap">
    <div class="fcols">
      <div class="fcol">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand" data-c>
          <?php echo catsnboard_paw_svg( 'paw' ); // phpcs:ignore ?>
          <b>Cats 'N' Board</b>
        </a>
        <p><?php echo esc_html( catsnboard_txt( 'footer.about', 'A warm second home for cats in Żoliborz, Warsaw. Boarding, daycare and gentle care by people who truly love cats.' ) ); ?></p>
      </div>
      <?php
      // etykiety menu w stopce: ta sama mapa co menu główne (pnb_menu_etykieta), z fallbackiem gdy plugin nieaktywny
      $m = function_exists( 'pnb_menu_etykieta' ) ? 'pnb_menu_etykieta' : function ( $s ) { return $s; };
      ?>
      <div class="fcol">
        <h4><?php echo esc_html( catsnboard_txt( 'footer.col.menu', 'Menu' ) ); ?></h4>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" data-c><?php echo esc_html( $m( 'Home' ) ); ?></a>
        <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" data-c><?php echo esc_html( $m( 'Services' ) ); ?></a>
        <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" data-c><?php echo esc_html( $m( 'Pricing' ) ); ?></a>
        <a href="<?php echo esc_url( home_url( '/our-staff/' ) ); ?>" data-c><?php echo esc_html( $m( 'Our Team' ) ); ?></a>
        <?php if ( get_page_by_path( 'gallery' ) ) : // sekcja wtyczki — link tylko gdy strona istnieje ?>
        <a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>" data-c><?php echo esc_html( $m( 'Gallery' ) ); ?></a>
        <?php endif; ?>
        <?php if ( get_page_by_path( 'events' ) ) : ?>
        <a href="<?php echo esc_url( home_url( '/events/' ) ); ?>" data-c><?php echo esc_html( $m( 'Events' ) ); ?></a>
        <?php endif; ?>
      </div>
      <div class="fcol">
        <h4><?php echo esc_html( catsnboard_txt( 'footer.col.facilities', 'Facilities' ) ); ?></h4>
        <a href="<?php echo esc_url( home_url( '/our-location/' ) ); ?>" data-c><?php echo esc_html( $m( 'Our Facilities' ) ); ?></a>
        <a href="<?php echo esc_url( home_url( '/our-location/' ) ); ?>" data-c><?php echo esc_html( $m( 'Location' ) ); ?></a>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" data-c><?php echo esc_html( $m( 'Contact' ) ); ?></a>
      </div>
      <div class="fcol">
        <h4><?php echo esc_html( catsnboard_txt( 'footer.getintouch', 'Get in touch' ) ); ?></h4>
        <?php
        $tel   = catsnboard_kontakt( 'tel' );
        $email = catsnboard_kontakt( 'email' );
        $adres = catsnboard_kontakt( 'adres' );
        ?>
        <?php if ( '' !== $tel ) : ?>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ); ?>" data-c><?php echo esc_html( $tel ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== $email ) : ?>
        <a href="mailto:<?php echo esc_attr( sanitize_email( $email ) ); ?>" data-c><?php echo esc_html( $email ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== $adres ) : ?>
        <p><?php echo esc_html( $adres ); ?></p>
        <?php endif; ?>
        <div class="socials">
          <a href="#" aria-label="Facebook" data-c><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/></svg></a>
          <a href="#" aria-label="Instagram" data-c><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg></a>
          <a href="#" aria-label="TikTok" data-c><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M16.5 3c.3 2.1 1.6 3.8 3.5 4.2v2.7c-1.3.1-2.6-.3-3.7-1v6.1c0 3.3-2.7 5.9-5.9 5.5-2.6-.3-4.6-2.5-4.6-5.1 0-3 2.6-5.4 5.6-5v2.8c-.4-.1-.8-.2-1.2-.1-1.2.1-2.1 1.2-1.9 2.4.1 1.1 1.1 1.9 2.2 1.8 1.2-.1 2-1.1 2-2.3V3h3.9z"/></svg></a>
        </div>
      </div>
    </div>
    <?php $fbot = array_filter( array( $adres, $tel, $email ) ); ?>
    <div class="fbot">© <?php echo esc_html( wp_date( 'Y' ) ); ?> Cats 'N' Board<?php echo $fbot ? ' · ' . esc_html( implode( ' · ', $fbot ) ) : ''; ?></div>
  </div>
</footer>

<!-- LIGHTBOX (used by the gallery) -->
<div class="lightbox" id="lightbox" aria-hidden="true">
  <button class="lb-close" id="lbClose" aria-label="Close">×</button>
  <button class="lb-nav prev" id="lbPrev" aria-label="Previous">‹</button>
  <img id="lbImg" src="" alt="" />
  <button class="lb-nav next" id="lbNext" aria-label="Next">›</button>
</div>

<?php wp_footer(); ?>
</body>
</html>
