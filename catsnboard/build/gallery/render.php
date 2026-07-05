<?php
/**
 * Dynamic render for the `catsnboard/gallery` block.
 *
 * Renders the WHOLE Gallery subpage (hero + full 12-image mosaic), 1:1 with
 * page-gallery.php, but the hero texts and every photo come from block
 * attributes instead of catsnboard_txt() / hardcoded files. Animations +
 * lightbox are handled by the theme's site-wide assets/js/main.js (already
 * enqueued) — this markup carries the same classes/IDs (.splitw, [data-rev],
 * #masonry, .masonry figure[data-c][data-g], img.graded) so GSAP and the
 * lightbox pick it up on the front.
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
		'heroEyebrow'   => 'Happy guests',
		'heroTitle'     => 'Gallery',
		'heroLead'      => 'A few of the calm, curious and very spoiled cats who have stayed with us. Tap any photo to view it larger.',
		'galleryImages' => array(),
	)
);

// Full 12-image mosaic. Fixed layout classes (same as page-gallery.php). Client
// images override theme defaults slot-by-slot; empty slots keep the original cat
// photos so the mosaic is never broken.
$gallery_defaults = array(
	array( 'file' => 'kot-1.jpg',  'cls' => 'big',  'alt' => 'Cat with toy' ),
	array( 'file' => 'kot-6.jpg',  'cls' => 'tall', 'alt' => 'Cat portrait' ),
	array( 'file' => 'kot-3.jpg',  'cls' => '',     'alt' => 'Cat relaxing' ),
	array( 'file' => 'kot-8.jpg',  'cls' => '',     'alt' => 'Cat playing' ),
	array( 'file' => 'kot-2.jpg',  'cls' => 'wide', 'alt' => 'Cat looking up' ),
	array( 'file' => 'kot-15.jpg', 'cls' => '',     'alt' => 'Cat resting' ),
	array( 'file' => 'kot-4.jpg',  'cls' => 'tall', 'alt' => 'Cat by window' ),
	array( 'file' => 'kot-11.jpg', 'cls' => '',     'alt' => 'Curious cat' ),
	array( 'file' => 'kot-7.jpg',  'cls' => 'wide', 'alt' => 'Sleepy cat' ),
	array( 'file' => 'kot-13.jpg', 'cls' => '',     'alt' => 'Cat on blanket' ),
	array( 'file' => 'kot-10.jpg', 'cls' => 'big',  'alt' => 'Cat close up' ),
	array( 'file' => 'kot-16.jpg', 'cls' => '',     'alt' => 'Playful kitten' ),
);
$gallery_set = is_array( $a['galleryImages'] ) ? array_values( $a['galleryImages'] ) : array();

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-gallery' ) );
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

<!-- ═══ FULL GALLERY (12-image mosaic + lightbox) ═══ -->
<section class="gallery">
  <div class="wrap">
    <div class="masonry" id="masonry">
      <?php
      foreach ( $gallery_defaults as $i => $def ) :
	      $img      = isset( $gallery_set[ $i ] ) && is_array( $gallery_set[ $i ] ) ? $gallery_set[ $i ] : array();
	      $src      = ! empty( $img['url'] ) ? $img['url'] : catsnboard_img( $def['file'] );
	      $alt      = ! empty( $img['alt'] ) ? $img['alt'] : $def['alt'];
	      $cls_attr = $def['cls'] ? ' class="' . esc_attr( $def['cls'] ) . '"' : '';
	      ?>
      <figure<?php echo $cls_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?> data-c data-g><img class="graded" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" /></figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

</div>
