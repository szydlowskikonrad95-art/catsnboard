<?php
/**
 * Dynamic render for the `catsnboard/services` block.
 *
 * Renders the WHOLE Services subpage (page hero + 8-tile services grid +
 * kitten training bar), 1:1 with page-services.php, but the texts come from
 * block attributes instead of catsnboard_txt(). Animations are handled by the
 * theme's site-wide assets/js/main.js (already enqueued) — this markup carries
 * the same classes/IDs (.page-hero, .splitw, [data-rev], [data-c], .svc) so
 * GSAP + reveals pick it up on the front. The 8 service tiles and the training
 * bar render from the theme (catsnboard_services / catsnboard_training_bar),
 * exactly like the classic template.
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
		'heroEyebrow' => 'What we offer',
		'heroTitle'   => 'Our services',
		'heroLead'    => 'Everything your cat needs under one roof — boarding, daycare, sitting, gentle training and simple care, always at your cat\'s pace.',
		'servicesCta' => 'See the pricing →',
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-services' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( $a['heroTitle'] ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
  </div>
</section>

<section class="services">
  <div class="wrap">
    <div class="sgrid">
      <?php foreach ( catsnboard_services() as $svc ) : ?>
        <div class="svc" data-c data-rev><span class="ic"><?php echo $svc[0]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG icon from theme ?></span><b><?php echo esc_html( $svc[1] ); ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="cta-row"><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['servicesCta'] ); ?></a></div>
  </div>
</section>

<?php catsnboard_training_bar(); ?>

</div>
