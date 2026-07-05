<?php
/**
 * Template for the "About us" subpage (slug: about-us).
 *
 * WordPress auto-selects this template for the About page instead of the generic
 * page.php, exactly like page-services.php is used for Services. It lets the
 * editable "About" block (catsnboard/about) render the whole subpage — hero +
 * "our story" + values — so its texts come from the editor. Without the block
 * set, it falls through to a classic hardcoded layout so the theme still works.
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "About" block, let the block render the whole
 * subpage (its markup carries the same .page-hero / .location / .svc classes as
 * the fallback, but the texts come from the editor). Rendering the_content()
 * here — rather than through page.php — avoids page.php's extra title-only hero,
 * so the block's own hero is the only one shown.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/about', get_queried_object() ) ) {
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
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'about.hero.eyebrow', 'Nice to meet you' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'about.hero.title', 'About us' ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'about.hero.lead', 'A small, cat-obsessed team giving your companion a calm, loving second home in the heart of Żoliborz.' ) ); ?></p>
  </div>
</section>

<section class="location">
  <div class="wrap">
    <div class="page-content" style="max-width:70ch;margin:0 auto;">
      <?php
		while ( have_posts() ) :
			the_post();
			the_content();
			wp_link_pages();
		endwhile;
		?>
    </div>
  </div>
</section>

<?php
get_footer();
