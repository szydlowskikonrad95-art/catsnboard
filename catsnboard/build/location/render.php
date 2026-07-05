<?php
/**
 * Dynamic render for the `catsnboard/location` block.
 *
 * Renders the WHOLE "Our Facilities / Our Location" subpage (page-hero +
 * location section with the illustrated map), 1:1 with page-our-location.php,
 * but text comes from block attributes instead of catsnboard_txt().
 *
 * The MAP is a decorative inline SVG (streets, route, pin) styled by the theme
 * (.map / .streets / .pin / .lab in style.css). It is kept 1:1 and hardcoded —
 * only its label (.lab) and the address line (.addr) are editable text.
 *
 * Animations are handled by the theme's site-wide assets/js/main.js (already
 * enqueued): this markup carries the same classes ([data-rev], .splitw) so the
 * reveal + split-words animations pick it up on the front.
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
		'heroEyebrow' => 'Come and visit',
		'heroTitle'   => 'Our facilities',
		'heroLead'    => 'Sunny rooms, cosy nooks and plenty of window sills — a calm holiday home in the heart of Żoliborz.',
		'secEyebrow'  => 'Come and visit',
		'secTitle'    => 'Our location',
		'secP1'       => "You'll find us on a quiet street in the heart of Żoliborz — sunny rooms, cosy nooks and plenty of window sills for sunbathing.",
		'secP2'       => 'Drop by for a tour, meet the team and let your cat sniff out its new holiday home.',
		'address'     => '',
		'mapLabel'    => '',
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-location' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( $a['heroTitle'] ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
  </div>
</section>

<section class="location">
  <div class="wrap">
    <div class="lgrid">
      <div>
        <span class="eyebrow" data-rev><?php echo esc_html( $a['secEyebrow'] ); ?></span>
        <h2 class="round splitw"><?php echo esc_html( $a['secTitle'] ); ?></h2>
        <p data-rev><?php echo esc_html( $a['secP1'] ); ?></p>
        <p data-rev><?php echo esc_html( $a['secP2'] ); ?></p>
        <?php if ( '' !== trim( $a['address'] ) ) : ?>
        <div class="addr" data-rev><?php echo esc_html( $a['address'] ); ?></div>
        <?php endif; ?>
      </div>
      <div class="map" data-rev>
        <svg class="streets" viewBox="0 0 400 275" preserveAspectRatio="none" aria-hidden="true">
          <rect class="park" x="24" y="24" width="96" height="66" rx="14"/>
          <rect class="park" x="290" y="180" width="86" height="70" rx="14"/>
          <path class="road big" d="M0 92 C130 84 240 70 400 66"/>
          <path class="road big" d="M0 188 C120 196 250 210 400 202"/>
          <path class="road sm" d="M110 0 C102 90 96 190 92 275"/>
          <path class="road sm" d="M272 0 C282 90 292 190 300 275"/>
          <path class="road sm" d="M0 140 C120 132 180 200 400 150"/>
          <path class="route" d="M300 200 C240 175 210 150 200 121"/>
        </svg>
        <svg class="pin" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.6" fill="#fff"/></svg>
        <?php if ( '' !== trim( $a['mapLabel'] ) ) : ?>
        <div class="lab"><?php echo esc_html( $a['mapLabel'] ); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

</div>
