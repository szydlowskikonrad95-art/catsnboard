<?php
/**
 * Template for the "Services" subpage (slug: services).
 * Full "Our services" section (8 tiles) + kitten training bar.
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "Services" block (catsnboard/services), let the
 * block render the whole subpage (its markup is 1:1 with the fallback below, but
 * texts come from the editor). Otherwise fall through to the classic hardcoded
 * layout — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/services', get_queried_object() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	get_footer();
	return;
}
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'services.hero.eyebrow', 'What we offer' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'services.hero.title', 'Our services' ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'services.hero.lead', 'Everything your cat needs under one roof — boarding, daycare, sitting, gentle training and simple care, always at your cat\'s pace.' ) ); ?></p>
  </div>
</section>

<section class="services">
  <div class="wrap">
    <div class="sgrid">
      <?php foreach ( catsnboard_services() as $svc ) : ?>
        <div class="svc" data-c data-rev><span class="ic"><?php echo $svc[0]; // phpcs:ignore ?></span><b><?php echo esc_html( $svc[1] ); ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="cta-row"><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( catsnboard_txt( 'services.cta', 'See the pricing →' ) ); ?></a></div>
  </div>
</section>

<?php catsnboard_training_bar(); ?>

<?php
get_footer();
