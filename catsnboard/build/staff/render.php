<?php
/**
 * Dynamic render for the `catsnboard/staff` block.
 *
 * Renders the WHOLE "Our Team" subpage (page hero + team member grid), 1:1 with
 * page-our-staff.php, but the hero texts come from block attributes instead of
 * catsnboard_txt(). Animations are handled by the theme's site-wide
 * assets/js/main.js (already enqueued) — this markup carries the same
 * classes/IDs (.page-hero, .splitw, [data-rev], [data-c], .team, .tgrid,
 * .member) so GSAP + reveals pick it up on the front. The 12 team members
 * render from the theme (catsnboard_team), exactly like the classic template.
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
		'heroEyebrow' => 'The people your cat will love',
		'heroTitle'   => 'Best Animal Aunties & Uncles',
		'heroLead'    => 'Calm, patient and genuinely cat-obsessed — meet the team who will look after your companion like their own.',
	)
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-staff' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo wp_kses( $a['heroTitle'], array( 'em' => array() ) ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
  </div>
</section>

<section class="team">
  <div class="wrap">
    <div class="tgrid" id="tgrid">
      <?php foreach ( catsnboard_team() as $m ) : ?>
        <div class="member" data-c data-rev><span class="ava"><img class="graded" src="<?php echo esc_url( catsnboard_img( $m[1] ) ); ?>" alt="<?php echo esc_attr( $m[0] ); ?>" /></span><b><?php echo esc_html( $m[0] ); ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</div>
