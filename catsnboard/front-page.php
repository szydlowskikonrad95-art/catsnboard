<?php
/**
 * Front page (Home) — teasers only: hero + training bar + services preview
 * (button -> /services/) + gallery preview (few cats -> /gallery/) + CTA.
 * NOT the whole site — each full section lives on its own subpage.
 *
 * @package catsnboard
 */

get_header();

/*
 * If the Home page uses the editable "Home" block (catsnboard/home), let the
 * block render the whole page (its markup is 1:1 with the fallback below, but
 * texts/images come from the editor). Otherwise fall through to the classic
 * hardcoded teasers — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/home', get_queried_object() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	get_footer();
	return;
}
?>

<!-- ═══ HERO ═══ -->
<header class="hero" id="top">
  <div class="hero-copy">
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'home.hero.eyebrow', 'Check in with us!' ) ); ?></span>
    <h1 class="round splitw"><?php echo wp_kses( catsnboard_txt( 'home.hero.title', 'Cats, kittens, <em>all welcome!</em>' ), array( 'em' => array() ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'home.hero.lead', 'A warm, calm second home for your cat — boarding, daycare and gentle care in Żoliborz, Warsaw.' ) ); ?></p>
    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( catsnboard_txt( 'home.hero.cta', 'Contact us! →' ) ); ?></a>
  </div>
  <div class="hero-photo">
    <img class="graded" id="heroImg" src="<?php echo esc_url( catsnboard_img( 'kot-hero.jpg' ) ); ?>" alt="A calm tabby cat looking into the camera" />
  </div>
</header>

<?php catsnboard_training_bar(); ?>

<!-- ═══ SERVICES PREVIEW (8 tiles, button -> /services/) ═══ -->
<section class="services" id="services">
  <div class="wrap">
    <div class="head">
      <h2 class="round splitw"><?php echo esc_html( catsnboard_txt( 'home.services.title', 'Our services' ) ); ?></h2>
    </div>
    <div class="sgrid">
      <?php foreach ( catsnboard_services() as $svc ) : ?>
        <div class="svc" data-c data-rev><span class="ic"><?php echo $svc[0]; // phpcs:ignore ?></span><b><?php echo esc_html( $svc[1] ); ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="cta-row"><a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( catsnboard_txt( 'home.services.cta', 'Read more →' ) ); ?></a></div>
  </div>
</section>

<!-- ═══ GALLERY PREVIEW (few cats -> /gallery/) ═══ -->
<section class="gallery" id="gallery">
  <div class="wrap">
    <div class="head">
      <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'home.gallery.eyebrow', 'Happy guests' ) ); ?></span>
      <h2 class="round splitw"><?php echo esc_html( catsnboard_txt( 'home.gallery.title', 'Gallery' ) ); ?></h2>
    </div>
    <div class="masonry" id="masonry">
      <figure class="big" data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-1.jpg' ) ); ?>" alt="Cat with toy" /></figure>
      <figure class="tall" data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-6.jpg' ) ); ?>" alt="Cat portrait" /></figure>
      <figure data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-3.jpg' ) ); ?>" alt="Cat relaxing" /></figure>
      <figure data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-8.jpg' ) ); ?>" alt="Cat playing" /></figure>
      <figure class="wide" data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-2.jpg' ) ); ?>" alt="Cat looking up" /></figure>
      <figure data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( 'kot-15.jpg' ) ); ?>" alt="Cat resting" /></figure>
    </div>
    <?php if ( get_page_by_path( 'gallery' ) ) : // przycisk tylko gdy strona galerii istnieje (sekcja wtyczki) ?>
    <div class="cta-row" style="text-align:center;margin-top:clamp(28px,4vw,44px);"><a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( catsnboard_txt( 'home.gallery.cta', 'See the full gallery →' ) ); ?></a></div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="final" id="contact">
  <?php echo catsnboard_paw_svg( 'paw-wm a' ); // phpcs:ignore ?>
  <?php echo catsnboard_paw_svg( 'paw-wm b' ); // phpcs:ignore ?>
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'home.final.eyebrow', "Let's talk about your cat" ) ); ?></span>
    <h2 class="round splitw"><?php echo esc_html( catsnboard_txt( 'home.final.title', 'Please call or write!' ) ); ?></h2>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'home.final.lead', "Whether it's a weekend getaway or full-time care, we'd love to hear about your cat. Reach out and we'll find the purr-fect plan." ) ); ?></p>
    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( catsnboard_txt( 'home.hero.cta', 'Contact us! →' ) ); ?></a>
  </div>
</section>

<?php
get_footer();
