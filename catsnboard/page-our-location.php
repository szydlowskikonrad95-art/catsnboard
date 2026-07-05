<?php
/**
 * Template for the "Our Facilities" subpage (slug: our-location, menu "Our Facilities").
 * Full location section + illustrated map.
 *
 * @package catsnboard
 */

get_header();

/*
 * If this page uses the editable "Location" block (catsnboard/location), let the
 * block render the whole subpage (its markup is 1:1 with the fallback below,
 * including the illustrated map, but texts come from the editor). Otherwise fall
 * through to the classic hardcoded layout — so the theme still works with no
 * block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/location', get_queried_object() ) ) {
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
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'location.hero.eyebrow', 'Come and visit' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'location.hero.title', 'Our facilities' ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'location.hero.lead', 'Sunny rooms, cosy nooks and plenty of window sills — a calm holiday home in the heart of Żoliborz.' ) ); ?></p>
  </div>
</section>

<section class="location">
  <div class="wrap">
    <div class="lgrid">
      <div>
        <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'location.sec.eyebrow', 'Come and visit' ) ); ?></span>
        <h2 class="round splitw"><?php echo esc_html( catsnboard_txt( 'location.sec.title', 'Our location' ) ); ?></h2>
        <p data-rev><?php echo esc_html( catsnboard_txt( 'location.sec.p1', 'You\'ll find us on a quiet street in the heart of Żoliborz — sunny rooms, cosy nooks and plenty of window sills for sunbathing.' ) ); ?></p>
        <p data-rev><?php echo esc_html( catsnboard_txt( 'location.sec.p2', 'Drop by for a tour, meet the team and let your cat sniff out its new holiday home.' ) ); ?></p>
        <?php $l_adres = catsnboard_kontakt( 'adres' ); ?>
        <?php if ( '' !== $l_adres ) : ?>
        <div class="addr" data-rev><?php echo esc_html( $l_adres ); ?></div>
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
        <?php $l_mapka = catsnboard_kontakt( 'mapka' ); ?>
        <?php if ( '' !== $l_mapka ) : ?>
        <div class="lab"><?php echo esc_html( $l_mapka ); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php
get_footer();
