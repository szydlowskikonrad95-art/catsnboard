<?php
/**
 * Generic page fallback.
 *
 * WordPress automatically picks page-{slug}.php for the branded subpages
 * (services, gallery, our-staff, our-location, contact, pricing). Any OTHER
 * page uses this template: a shared page-hero + the page's editor content.
 *
 * @package catsnboard
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
	  <div class="wrap">
	    <h1 class="round splitw"><?php the_title(); ?></h1>
	  </div>
	</section>

	<section class="location">
	  <div class="wrap">
	    <div class="page-content" style="max-width:70ch;margin:0 auto;">
	      <?php
			the_content();
			wp_link_pages();
			?>
	    </div>
	  </div>
	</section>
	<?php
endwhile;

get_footer();
