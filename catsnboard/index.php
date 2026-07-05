<?php
/**
 * Main index fallback (blog listing / archives / search).
 *
 * @package catsnboard
 */

get_header();
?>

<section class="page-hero">
  <div class="wrap">
    <h1 class="round splitw"><?php echo esc_html( wp_get_document_title() ); ?></h1>
  </div>
</section>

<section class="location">
  <div class="wrap" style="max-width:80ch;">
    <?php if ( have_posts() ) : ?>
      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?> style="margin-bottom:48px;">
          <h2 class="round" style="font-size:1.8rem;margin-bottom:10px;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <div class="post-excerpt"><?php the_excerpt(); ?></div>
        </article>
      <?php endwhile; ?>
      <div class="cta-row" style="margin-top:32px;"><?php posts_nav_link(); ?></div>
    <?php else : ?>
      <p>Nothing here yet.</p>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
