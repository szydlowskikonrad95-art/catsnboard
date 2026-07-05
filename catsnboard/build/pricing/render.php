<?php
/**
 * Dynamic render for the `catsnboard/pricing` block.
 *
 * Renders the WHOLE Pricing subpage (page hero + 3 pricing cards:
 * Daycare / Boarding[featured] / Sitting), 1:1 with page-pricing.php, but every
 * text AND price comes from block attributes instead of catsnboard_txt() /
 * hardcoded strings. Animations are handled by the theme's site-wide
 * assets/js/main.js (already enqueued) — this markup carries the same
 * classes/IDs (.page-hero, .splitw, [data-rev], [data-c], .pcard, .featured) so
 * GSAP + reveals pick it up on the front, exactly like the classic template.
 *
 * @package catsnboard
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$a = wp_parse_args(
	$attributes,
	array(
		'heroEyebrow'   => 'Simple, honest prices',
		'heroTitle'     => 'Pricing',
		'heroLead'      => 'Transparent rates with no surprises. Longer stays and multiple cats get a friendly discount — just ask.',
		'daycareTitle'  => 'Daycare',
		'daycarePrice'  => '49 zł',
		'daycareUnit'   => '/ day',
		'daycareF1'     => 'Supervised play & rest',
		'daycareF2'     => 'Fresh food & water',
		'daycareF3'     => 'Sunny window spots',
		'daycareF4'     => 'Daily photo update',
		'boardingTitle' => 'Boarding',
		'boardingPrice' => '89 zł',
		'boardingUnit'  => '/ night',
		'boardingF1'    => 'Cosy private room',
		'boardingF2'    => 'Two play sessions daily',
		'boardingF3'    => 'Litter & grooming care',
		'boardingF4'    => 'Photo & message updates',
		'sittingTitle'  => 'Cat sitting',
		'sittingPrice'  => '59 zł',
		'sittingUnit'   => '/ visit',
		'sittingF1'     => 'Care in your own home',
		'sittingF2'     => 'Feeding & litter',
		'sittingF3'     => 'Play & cuddles',
		'sittingF4'     => 'Plant watering on request',
		'bookLabel'     => 'Book now →',
	)
);

// Build the 3 cards from attributes so the markup loop stays 1:1 with
// page-pricing.php (Boarding is the featured/coral card).
$plans = array(
	array(
		'title'    => $a['daycareTitle'],
		'price'    => $a['daycarePrice'],
		'unit'     => $a['daycareUnit'],
		'featured' => false,
		'feats'    => array( $a['daycareF1'], $a['daycareF2'], $a['daycareF3'], $a['daycareF4'] ),
	),
	array(
		'title'    => $a['boardingTitle'],
		'price'    => $a['boardingPrice'],
		'unit'     => $a['boardingUnit'],
		'featured' => true,
		'feats'    => array( $a['boardingF1'], $a['boardingF2'], $a['boardingF3'], $a['boardingF4'] ),
	),
	array(
		'title'    => $a['sittingTitle'],
		'price'    => $a['sittingPrice'],
		'unit'     => $a['sittingUnit'],
		'featured' => false,
		'feats'    => array( $a['sittingF1'], $a['sittingF2'], $a['sittingF3'], $a['sittingF4'] ),
	),
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-pricing' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( $a['heroTitle'] ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
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
          <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c><?php echo esc_html( $a['bookLabel'] ); ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</div>
