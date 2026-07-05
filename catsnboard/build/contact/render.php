<?php
/**
 * Dynamic render for the `catsnboard/contact` block.
 *
 * Renders the WHOLE Contact page (hero + contact info + demo form), 1:1 with
 * page-contact.php, but text comes from block attributes instead of
 * catsnboard_txt(). Animations are handled by the theme's site-wide
 * assets/js/main.js (already enqueued) — this markup carries the same classes
 * ([data-rev], .splitw, .page-hero, .contact-sec, .cform) so the theme's GSAP
 * reveals pick it up on the front.
 *
 * The FORM is a demo/placeholder (no backend — exactly like page-contact.php):
 * onsubmit="return false;" so it never navigates. Field ids/types/placeholders
 * are kept identical, only the visible labels/texts are editable. Wire a real
 * form plugin (Contact Form 7 / WPForms) to go live.
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
		'heroEyebrow' => 'Let\'s talk about your cat',
		'heroTitle'   => 'Please call or write!',
		'heroLead'    => 'Whether it\'s a weekend getaway or full-time care, reach out and we\'ll find the purr-fect plan.',
		'infoEyebrow' => 'Get in touch',
		'infoTitle'   => 'Say hello',
		'infoLead'    => 'We usually reply within a day. Pop in for a tour any time — the kettle (and the treats) are always on.',
		'infoPhone'   => '',
		'infoEmail'   => '',
		'infoAddress' => '',
		'formName'    => 'Your name',
		'formEmail'   => 'Email',
		'formCat'     => 'Your cat\'s name',
		'formMsg'     => 'Message',
		'formSend'    => 'Send message →',
		'formNote'    => "This is a demo form — it doesn't send anything yet. Connect a form plugin (e.g. Contact Form 7 / WPForms) to go live.",
	)
);

// Derive safe href targets from the editable phone/email text.
$tel_href  = 'tel:' . preg_replace( '/[^0-9+]/', '', $a['infoPhone'] );
$mail_href = is_email( $a['infoEmail'] ) ? 'mailto:' . $a['infoEmail'] : '';

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'cnb-contact' ) );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped ?>>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow" data-rev><?php echo esc_html( $a['heroEyebrow'] ); ?></span>
    <h1 class="round splitw"><?php echo esc_html( $a['heroTitle'] ); ?></h1>
    <p data-rev><?php echo esc_html( $a['heroLead'] ); ?></p>
  </div>
</section>

<section class="contact-sec">
  <div class="wrap">
    <div class="cgrid">
      <div class="cinfo">
        <span class="eyebrow" data-rev><?php echo esc_html( $a['infoEyebrow'] ); ?></span>
        <h2 class="round splitw"><?php echo esc_html( $a['infoTitle'] ); ?></h2>
        <p data-rev><?php echo esc_html( $a['infoLead'] ); ?></p>
        <?php if ( '' !== trim( $a['infoPhone'] ) ) : ?>
        <a href="<?php echo esc_url( $tel_href ); ?>" data-rev><?php echo esc_html( $a['infoPhone'] ); ?></a>
        <?php endif; ?>
        <?php if ( '' !== trim( $a['infoEmail'] ) ) : ?>
        <?php if ( $mail_href ) : ?>
        <a href="<?php echo esc_url( $mail_href ); ?>" data-rev><?php echo esc_html( $a['infoEmail'] ); ?></a>
        <?php else : ?>
        <a href="#" data-rev><?php echo esc_html( $a['infoEmail'] ); ?></a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ( '' !== trim( $a['infoAddress'] ) ) : ?>
        <p data-rev><?php echo esc_html( $a['infoAddress'] ); ?></p>
        <?php endif; ?>
      </div>
      <form class="cform" data-rev onsubmit="return false;">
        <label for="cf-name"><?php echo esc_html( $a['formName'] ); ?></label>
        <input id="cf-name" type="text" placeholder="Jane Doe" autocomplete="name" />
        <label for="cf-email"><?php echo esc_html( $a['formEmail'] ); ?></label>
        <input id="cf-email" type="email" placeholder="jane@example.com" autocomplete="email" />
        <label for="cf-cat"><?php echo esc_html( $a['formCat'] ); ?></label>
        <input id="cf-cat" type="text" placeholder="Whiskers" />
        <label for="cf-msg"><?php echo esc_html( $a['formMsg'] ); ?></label>
        <textarea id="cf-msg" placeholder="Tell us a little about your cat and the dates you have in mind…"></textarea>
        <button type="submit" class="btn" data-c><?php echo esc_html( $a['formSend'] ); ?></button>
        <span class="note"><?php echo esc_html( $a['formNote'] ); ?></span>
      </form>
    </div>
  </div>
</section>

</div>
