<?php
/**
 * Dynamic render for the `catsnboard/home` block.
 *
 * Renders the WHOLE Home page (hero + training + services + gallery + CTA),
 * 1:1 with front-page.php, but text/images come from block attributes instead
 * of catsnboard_txt() / hardcoded files. Animations are handled by the theme's
 * site-wide assets/js/main.js (already enqueued) — this markup carries the same
 * classes/IDs (.splitw, [data-rev], [data-c], [data-g], #heroImg, #trainImg,
 * .svc, .masonry figure) so GSAP + the lightbox pick it up on the front.
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
		'heroEyebrow'    => 'Check in with us!',
		'heroTitle'      => 'Cats, kittens, <em>all welcome!</em>',
		'heroLead'       => 'A warm, calm second home for your cat — boarding, daycare and gentle care in Żoliborz, Warsaw.',
		'heroCta'        => 'Contact us! →',
		'heroImageUrl'   => '',
		'heroImageAlt'   => 'A calm tabby cat looking into the camera',
		'servicesTitle'  => 'Our services',
		'servicesCta'    => 'Read more →',
		'galleryEyebrow' => 'Happy guests',
		'galleryTitle'   => 'Gallery',
		'galleryCta'     => 'See the full gallery →',
		'galleryImages'  => array(),
		'finalEyebrow'   => "Let's talk about your cat",
		'finalTitle'     => 'Please call or write!',
		'finalLead'      => "Whether it's a weekend getaway or full-time care, we'd love to hear about your cat. Reach out and we'll find the purr-fect plan.",
		'finalCta'       => 'Contact us! →',
	)
);

// Hero image: attribute URL, else theme default (keeps it 1:1 out of the box).
$hero_src = ! empty( $a['heroImageUrl'] ) ? $a['heroImageUrl'] : catsnboard_img( 'kot-hero.jpg' );

// Gallery: 6 masonry slots with fixed layout classes. Client-set images override
// theme defaults slot-by-slot; empty slots keep the original cat photos so the
// mosaic is never broken.
$gallery_defaults = array(
	array( 'file' => 'kot-1.jpg',  'cls' => 'big',  'alt' => 'Cat with toy' ),
	array( 'file' => 'kot-6.jpg',  'cls' => 'tall', 'alt' => 'Cat portrait' ),
	array( 'file' => 'kot-3.jpg',  'cls' => '',     'alt' => 'Cat relaxing' ),
	array( 'file' => 'kot-8.jpg',  'cls' => '',     'alt' => 'Cat playing' ),
	array( 'file' => 'kot-2.jpg',  'cls' => 'wide', 'alt' => 'Cat looking up' ),
	array( 'file' => 'kot-15.jpg', 'cls' => '',     'alt' => 'Cat resting' ),
);
$gallery_set = is_array( $a['galleryImages'] ) ? array_values( $a['galleryImages'] ) : array();

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-home' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<!-- ═══ HERO ═══ -->
<header class="hero" id="top">
  <div class="hero-copy">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo wp_kses( $a['heroTitle'], array( 'em' => array() ) ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['heroCta'] ); ?></a>
  </div>
  <div class="hero-photo">
    <img class="graded" id="heroImg" src="<?php echo esc_url( $hero_src ); ?>" alt="<?php echo esc_attr( $a['heroImageAlt'] ); ?>" />
  </div>
</header>

<?php catsnboard_training_bar(); ?>

<!-- ═══ SERVICES PREVIEW (8 tiles, button -> /services/) ═══ -->
<section class="services" id="services">
  <div class="wrap">
    <div class="head">
      <h2 class="round splitw"><?php echo esc_html( $a['servicesTitle'] ); ?></h2>
    </div>
    <div class="sgrid">
      <?php foreach ( catsnboard_services() as $svc ) : ?>
        <div class="svc" data-c data-rev><span class="ic"><?php echo $svc[0]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG icon from theme ?></span><b><?php echo esc_html( $svc[1] ); ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="cta-row"><a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['servicesCta'] ); ?></a></div>
  </div>
</section>

<!-- ═══ GALLERY PREVIEW (few cats -> /gallery/) ═══ -->
<section class="gallery" id="gallery">
  <div class="wrap">
    <div class="head">
      <span class="eyebrow" data-rev><?php echo esc_html( $a['galleryEyebrow'] ); ?></span>
      <h2 class="round splitw"><?php echo esc_html( $a['galleryTitle'] ); ?></h2>
    </div>
    <div class="masonry" id="masonry">
      <?php
      foreach ( $gallery_defaults as $i => $def ) :
	      $img = isset( $gallery_set[ $i ] ) && is_array( $gallery_set[ $i ] ) ? $gallery_set[ $i ] : array();
	      $src = ! empty( $img['url'] ) ? $img['url'] : catsnboard_img( $def['file'] );
	      $alt = ! empty( $img['alt'] ) ? $img['alt'] : $def['alt'];
	      $cls_attr = $def['cls'] ? ' class="' . esc_attr( $def['cls'] ) . '"' : '';
	      ?>
      <figure<?php echo $cls_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?> data-c data-g><img class="graded" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $alt ); ?>" /></figure>
      <?php endforeach; ?>
    </div>
    <?php if ( get_page_by_path( 'gallery' ) ) : // przycisk tylko gdy strona galerii istnieje (sekcja wtyczki) ?>
    <div class="cta-row" style="text-align:center;margin-top:clamp(28px,4vw,44px);"><a href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['galleryCta'] ); ?></a></div>
    <?php endif; ?>
  </div>
</section>

<!-- ═══ CTA ═══ -->
<section class="final" id="contact">
  <?php echo catsnboard_paw_svg( 'paw-wm a' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG ?>
  <?php echo catsnboard_paw_svg( 'paw-wm b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme SVG ?>
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['finalEyebrow'] ); ?></span>
    <h2 class="round splitw"><?php echo esc_html( $a['finalTitle'] ); ?></h2>
    <p data-rev><?php echo esc_html( $a['finalLead'] ); ?></p>
    <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn" data-c data-rev><?php echo esc_html( $a['finalCta'] ); ?></a>
  </div>
</section>

</div>
