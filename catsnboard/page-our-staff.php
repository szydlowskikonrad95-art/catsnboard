<?php
/**
 * Template for the "Our Team" subpage (slug: our-staff, menu label "Our Team").
 * Full team section — 12 members (opiekun-1..12 + invented names).
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "Our Team" block (catsnboard/staff), let the
 * block render the whole subpage (its markup is 1:1 with the fallback below, but
 * the hero texts come from the editor). Otherwise fall through to the classic
 * hardcoded layout — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/staff', get_queried_object() ) ) {
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
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'staff.hero.eyebrow', 'The people your cat will love' ) ); ?></span>
    <h1 class="round splitw"><?php echo wp_kses( catsnboard_txt( 'staff.hero.title', 'Best Animal Aunties & Uncles' ), array( 'em' => array() ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'staff.hero.lead', 'Calm, patient and genuinely cat-obsessed — meet the team who will look after your companion like their own.' ) ); ?></p>
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

<?php
get_footer();
