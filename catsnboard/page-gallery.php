<?php
/**
 * Template for the "Gallery" subpage (slug: gallery).
 * Full mosaic gallery of cats + lightbox (lightbox markup is in footer.php).
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "Gallery" block (catsnboard/gallery), let the
 * block render the whole subpage (its markup is 1:1 with the fallback below, but
 * hero text + every photo come from the editor). Otherwise fall through to the
 * classic hardcoded layout — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/gallery', get_queried_object() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	get_footer();
	return;
}

// Full 12-image mosaic (same layout classes as the source).
$shots = array(
	array( 'kot-1.jpg', 'Cat with toy', 'big' ),
	array( 'kot-6.jpg', 'Cat portrait', 'tall' ),
	array( 'kot-3.jpg', 'Cat relaxing', '' ),
	array( 'kot-8.jpg', 'Cat playing', '' ),
	array( 'kot-2.jpg', 'Cat looking up', 'wide' ),
	array( 'kot-15.jpg', 'Cat resting', '' ),
	array( 'kot-4.jpg', 'Cat by window', 'tall' ),
	array( 'kot-11.jpg', 'Curious cat', '' ),
	array( 'kot-7.jpg', 'Sleepy cat', 'wide' ),
	array( 'kot-13.jpg', 'Cat on blanket', '' ),
	array( 'kot-10.jpg', 'Cat close up', 'big' ),
	array( 'kot-16.jpg', 'Playful kitten', '' ),
);
?>

<?php if ( ! function_exists( 'pnb_galeria_render' ) ) : ?>
<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev>Happy guests</span>
    <h1 class="round splitw">Gallery</h1>
    <p data-rev>A few of the calm, curious and very spoiled cats who have stayed with us. Tap any photo to view it larger.</p>
  </div>
</section>
<?php endif; ?>

<?php if ( function_exists( 'pnb_galeria_render' ) ) : ?>
  <?php
  // PLUGIN AKTYWNY = symulacja wdrożenia u klienta: galeria pluginu PODMIENIA starą.
  // the_content w pętli — plugin dokleja się filtrem jak na obcym motywie (test "trafia w miejsce").
  while ( have_posts() ) :
    the_post();
    the_content();
  endwhile;
  ?>
<?php else : ?>
<section class="gallery">
  <div class="wrap">
    <div class="masonry" id="masonry">
      <?php foreach ( $shots as $s ) : ?>
        <figure class="<?php echo esc_attr( $s[2] ); ?>" data-c data-g><img class="graded" src="<?php echo esc_url( catsnboard_img( $s[0] ) ); ?>" alt="<?php echo esc_attr( $s[1] ); ?>" /></figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
get_footer();
