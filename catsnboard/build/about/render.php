<?php
/**
 * Dynamic render for the `catsnboard/about` block.
 *
 * Renders the WHOLE About subpage (page hero + "our story" section with an
 * illustrated values trio), 1:1 with the branded subpages' look, but the texts
 * come from block attributes instead of catsnboard_txt(). Animations are handled
 * by the theme's site-wide assets/js/main.js (already enqueued) — this markup
 * carries the same classes/IDs (.page-hero, .location, .lgrid, .splitw,
 * [data-rev], [data-c], .svc) so GSAP + reveals pick it up on the front, exactly
 * like page-services.php / page-our-location.php.
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
		'heroEyebrow'  => 'Nice to meet you',
		'heroTitle'    => 'About us',
		'heroLead'     => 'A small, cat-obsessed team giving your companion a calm, loving second home in the heart of Żoliborz.',
		'storyEyebrow' => 'Our story',
		'storyTitle'   => 'Made by people who truly love cats',
		'storyP1'      => 'Cats\'N\'Board started with a simple idea: cats deserve a holiday spot as gentle and unhurried as they are. No cages, no stress — just sunny rooms, cosy nooks and people who understand the feline point of view.',
		'storyP2'      => 'Every guest is welcomed at their own pace. We keep things calm and predictable, learn each cat\'s little routines, and send you photos so you never miss a whisker while you\'re away.',
		'valuesTitle'  => 'What we care about',
		'value1Title'  => 'Calm first',
		'value1Text'   => 'A quiet, unhurried space where shy cats can settle in on their own terms.',
		'value2Title'  => 'Genuine care',
		'value2Text'   => 'Patient, cat-obsessed carers who treat every guest like their own companion.',
		'value3Title'  => 'Always in touch',
		'value3Text'   => 'Regular photos and updates, so you always know your cat is happy and safe.',
		'ctaText'      => 'Come and meet us →',
	)
);

// Three value cards reuse the theme's .svc reveal (slide-in) + paw icon.
$values = array(
	array( $a['value1Title'], $a['value1Text'] ),
	array( $a['value2Title'], $a['value2Text'] ),
	array( $a['value3Title'], $a['value3Text'] ),
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-about' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<!-- ═══ PAGE HERO ═══ -->
<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( $a['heroTitle'] ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
  </div>
</section>

<!-- ═══ OUR STORY (text + illustrated values) ═══ -->
<section class="location">
  <div class="wrap">
    <div class="lgrid">
      <div>
        <span class="eyebrow" data-rev><?php echo esc_html( $a['storyEyebrow'] ); ?></span>
        <h2 class="round splitw"><?php echo esc_html( $a['storyTitle'] ); ?></h2>
        <p data-rev><?php echo esc_html( $a['storyP1'] ); ?></p>
        <p data-rev><?php echo esc_html( $a['storyP2'] ); ?></p>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['ctaText'] ); ?></a>
      </div>
      <div class="cnb-about-values">
        <span class="eyebrow" data-rev><?php echo esc_html( $a['valuesTitle'] ); ?></span>
        <div class="sgrid cnb-values-grid">
          <?php foreach ( $values as $v ) : ?>
            <div class="svc" data-c data-rev>
              <span class="ic"><?php echo catsnboard_paw_svg( 'paw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG ?></span>
              <span class="cnb-value-copy"><b><?php echo esc_html( $v[0] ); ?></b><small><?php echo esc_html( $v[1] ); ?></small></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

</div>
