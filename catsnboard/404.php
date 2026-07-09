<?php
/**
 * 404 — strona nie znaleziona. Ładny fallback zamiast gołego index.php (audyt 2026-07-09).
 * Styl motywu (page-hero + wrap), sensowna treść, linki powrotu do kluczowych stron.
 *
 * @package catsnboard
 */

get_header();
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow"><?php echo esc_html( catsnboard_txt( 'e404.eyebrow', 'Error 404' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'e404.title', 'This page wandered off' ) ); ?></h1>
    <p class="lead" style="max-width:60ch;">
      <?php echo esc_html( catsnboard_txt( 'e404.lead', 'Like a curious cat, this page slipped away. It may have moved or never existed. Let\'s get you back on track.' ) ); ?>
    </p>
    <div class="cta-row" style="margin-top:28px;display:flex;gap:14px;flex-wrap:wrap;">
      <a class="btn btn-coral" href="<?php echo esc_url( home_url( '/' ) ); ?>">
        <?php echo esc_html( catsnboard_txt( 'e404.home', 'Back to home' ) ); ?> →
      </a>
      <?php
      // Linki do kluczowych stron JEŚLI istnieją (nie pokazuj martwych).
      $strony = array(
        'events'  => catsnboard_txt( 'e404.events', 'Events' ),
        'gallery' => catsnboard_txt( 'e404.gallery', 'Gallery' ),
        'contact' => catsnboard_txt( 'e404.contact', 'Contact' ),
      );
      foreach ( $strony as $slug => $etykieta ) {
        $p = get_page_by_path( $slug );
        if ( $p ) {
          echo '<a class="btn btn-ghost" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $etykieta ) . '</a>';
        }
      }
      ?>
    </div>
  </div>
</section>

<?php
get_footer();
