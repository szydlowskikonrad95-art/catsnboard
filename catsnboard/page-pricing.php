<?php
/**
 * Template for the "Pricing" subpage (slug: pricing).
 * Simple card pricing — Boarding / Daycare / Sitting (example prices, fiction).
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "Pricing" block (catsnboard/pricing), let the
 * block render the whole subpage (its markup is 1:1 with the fallback below, but
 * texts and prices come from the editor). Otherwise fall through to the classic
 * hardcoded layout — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/pricing', get_queried_object() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	get_footer();
	return;
}

$plans = array(
	array(
		'title'    => catsnboard_txt( 'pricing.daycare.title', 'Daycare' ),
		'price'    => '49 zł',
		'unit'     => catsnboard_txt( 'pricing.unit.day', '/ day' ),
		'featured' => false,
		'feats'    => array( catsnboard_txt( 'pricing.daycare.f1', 'Supervised play & rest' ), catsnboard_txt( 'pricing.daycare.f2', 'Fresh food & water' ), catsnboard_txt( 'pricing.daycare.f3', 'Sunny window spots' ), catsnboard_txt( 'pricing.daycare.f4', 'Daily photo update' ) ),
	),
	array(
		'title'    => catsnboard_txt( 'pricing.boarding.title', 'Boarding' ),
		'price'    => '89 zł',
		'unit'     => catsnboard_txt( 'pricing.unit.night', '/ night' ),
		'featured' => true,
		'feats'    => array( catsnboard_txt( 'pricing.boarding.f1', 'Cosy private room' ), catsnboard_txt( 'pricing.boarding.f2', 'Two play sessions daily' ), catsnboard_txt( 'pricing.boarding.f3', 'Litter & grooming care' ), catsnboard_txt( 'pricing.boarding.f4', 'Photo & message updates' ) ),
	),
	array(
		'title'    => catsnboard_txt( 'pricing.sitting.title', 'Cat sitting' ),
		'price'    => '59 zł',
		'unit'     => catsnboard_txt( 'pricing.unit.visit', '/ visit' ),
		'featured' => false,
		'feats'    => array( catsnboard_txt( 'pricing.sitting.f1', 'Care in your own home' ), catsnboard_txt( 'pricing.sitting.f2', 'Feeding & litter' ), catsnboard_txt( 'pricing.sitting.f3', 'Play & cuddles' ), catsnboard_txt( 'pricing.sitting.f4', 'Plant watering on request' ) ),
	),
);
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'pricing.hero.eyebrow', 'Simple, honest prices' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'pricing.hero.title', 'Pricing' ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'pricing.hero.lead', 'Transparent rates with no surprises. Longer stays and multiple cats get a friendly discount — just ask.' ) ); ?></p>
  </div>
</section>

<section class="pricing-sec">
  <div class="wrap">
    <div class="pgrid">
      <?php foreach ( $plans as $p ) : ?>
        <div class="pcard<?php echo $p['featured'] ? ' featured' : ''; ?>" data-c data-rev>
          <div class="ptitle"><?php echo esc_html( $p['title'] ); ?></div>
          <div class="pprice"><?php echo esc_html( $p['price'] ); ?> <small><?php echo esc_html( $p['unit'] ); ?></small></div>
          <ul class="pfeat">
            <?php foreach ( $p['feats'] as $f ) : ?>
              <li><?php echo esc_html( $f ); ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c><?php echo esc_html( catsnboard_txt( 'pricing.book', 'Book now →' ) ); ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
get_footer();
