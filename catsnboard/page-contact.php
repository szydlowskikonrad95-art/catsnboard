<?php
/**
 * Template for the "Contact" subpage (slug: contact).
 * Contact info (test phone/mail) + placeholder form (no backend — demo only).
 *
 * @package catsnboard
 */

get_header();

/*
 * If the Contact page uses the editable "Contact" block (catsnboard/contact),
 * let the block render the whole page (its markup is 1:1 with the fallback
 * below, but texts come from the editor). Otherwise fall through to the classic
 * hardcoded template — so the theme still works with no block set.
 */
if ( have_posts() && function_exists( 'has_block' ) && has_block( 'catsnboard/contact', get_queried_object() ) ) {
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
    <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'contactpg.hero.eyebrow', 'Let\'s talk about your cat' ) ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( catsnboard_txt( 'contactpg.hero.title', 'Please call or write!' ) ); ?></h1>
    <p data-rev><?php echo esc_html( catsnboard_txt( 'contactpg.hero.lead', 'Whether it\'s a weekend getaway or full-time care, reach out and we\'ll find the purr-fect plan.' ) ); ?></p>
  </div>
</section>

<section class="contact-sec">
  <div class="wrap">
    <div class="cgrid">
      <div class="cinfo">
        <span class="eyebrow" data-rev><?php echo esc_html( catsnboard_txt( 'contactpg.eyebrow', 'Get in touch' ) ); ?></span>
        <h2 class="round splitw"><?php echo esc_html( catsnboard_txt( 'contactpg.title', 'Say hello' ) ); ?></h2>
        <p data-rev><?php echo esc_html( catsnboard_txt( 'contactpg.lead', 'We usually reply within a day. Pop in for a tour any time — the kettle (and the treats) are always on.' ) ); ?></p>
        <?php
        $c_tel   = catsnboard_kontakt( 'tel' );
        $c_email = catsnboard_kontakt( 'email' );
        $c_adres = catsnboard_kontakt( 'adres' );
        ?>
        <?php if ( '' !== $c_tel ) : ?>
        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $c_tel ) ); ?>" data-rev><?php echo esc_html( $c_tel ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== $c_email ) : ?>
        <a href="mailto:<?php echo esc_attr( sanitize_email( $c_email ) ); ?>" data-rev><?php echo esc_html( $c_email ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== $c_adres ) : ?>
        <p data-rev><?php echo esc_html( $c_adres ); ?></p>
        <?php endif; ?>
      </div>
      <form class="cform" data-rev onsubmit="return false;">
        <label for="cf-name"><?php echo esc_html( catsnboard_txt( 'contactpg.form.name', 'Your name' ) ); ?></label>
        <input id="cf-name" type="text" placeholder="Jane Doe" autocomplete="name" />
        <label for="cf-email"><?php echo esc_html( catsnboard_txt( 'contactpg.form.email', 'Email' ) ); ?></label>
        <input id="cf-email" type="email" placeholder="jane@example.com" autocomplete="email" />
        <label for="cf-cat"><?php echo esc_html( catsnboard_txt( 'contactpg.form.cat', 'Your cat\'s name' ) ); ?></label>
        <input id="cf-cat" type="text" placeholder="Whiskers" />
        <label for="cf-msg"><?php echo esc_html( catsnboard_txt( 'contactpg.form.msg', 'Message' ) ); ?></label>
        <textarea id="cf-msg" placeholder="Tell us a little about your cat and the dates you have in mind…"></textarea>
        <button type="submit" class="btn" data-c><?php echo esc_html( catsnboard_txt( 'contactpg.form.send', 'Send message →' ) ); ?></button>
        <span class="note"><?php echo esc_html( catsnboard_txt( 'contactpg.form.note', "This is a demo form — it doesn't send anything yet. Connect a form plugin (e.g. Contact Form 7 / WPForms) to go live." ) ); ?></span>
      </form>
    </div>
  </div>
</section>

<?php
get_footer();
