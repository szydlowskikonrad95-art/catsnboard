<?php
/* Moduł KALENDARZ WYDARZEŃ: CPT wydarzenie + karty nadchodzących + zapisy gości (panel+mail) + CSV.
   Spec: ZROZUMIENIE.md. Miny z audytu: LiteSpeed-nonce (no-cache), wp_mail→spam (zapis nigdy nie blokowany mailem). */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ==== REJESTRACJA (wspólna dla init i aktywacji — lekcja lifecycle: flush dopiero PO rejestracji) ==== */

function pnb_kalendarz_rejestruj() {
	register_post_type( 'pnb_wydarzenie', array(
		'labels' => array(
			'name'          => __( 'Events', 'pnb-toolkit' ),
			'singular_name' => __( 'Event', 'pnb-toolkit' ),
			'add_new_item'  => __( 'Add event', 'pnb-toolkit' ),
			'edit_item'     => __( 'Edit event', 'pnb-toolkit' ),
		),
		'public'       => true,
		'has_archive'  => false,
		'menu_icon'    => 'dashicons-calendar-alt',
		// Wydarzenia = WŁASNE menu „Events" w adminie (decyzja klienta 2026-07-05): klient ma mieć
		// wszystko w JEDNYM miejscu — lista wydarzeń + „Add event" + podstrona Settings (email na
		// powiadomienia o zapisach). Kto się zapisał = metabox przy każdym Edit. Menu jest też bramą
		// niezależną od bloku Events (gdyby klient usunął stronę z blokiem, dostęp zostaje).
		'show_in_menu' => true,
		'rewrite'      => array( 'slug' => 'event' ),
		// KLASYCZNY edytor (nie Gutenberg): dla laika pola data/godzina/miejsce/PL mają być WIDOCZNE
		// pod tytułem, a nie schowane w pasku „Meta Boxes" na dole edytora bloków. show_in_rest=false
		// wyłącza Gutenberg dla tego typu → wraca prosty ekran „tytuł + opis + meta boxy".
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest' => false,
	) );
	// zapisy gości: bez własnego UI — pokazujemy je metaboxem przy wydarzeniu
	register_post_type( 'pnb_zapis', array(
		'labels'  => array( 'name' => __( 'Sign-ups', 'pnb-toolkit' ) ),
		'public'  => false,
		'show_ui' => false,
	) );
}
add_action( 'init', 'pnb_kalendarz_rejestruj' );

/* AUTO-WYKRYCIE strony Events (naprawa 2026-07-05): opcja pnb_wydarzenia_strona była NIGDY nie zapisywana
 * → linki „All events"/back-link leciały na stronę główną zamiast na Events, a scenografia CSS się nie
 * włączała. Rozwiązanie „OBOK, nie ZAMIAST": przy zapisie DOWOLNEJ strony sprawdzamy czy ma blok
 * pnb/wydarzenia i zapisujemy jej ID. Klient nic nie robi — plugin sam znajduje stronę, gdziekolwiek wstawił blok.
 * (NIE tworzymy strony — to robota klienta; my tylko zapamiętujemy którą wskazał wstawiając blok.) */
add_action( 'save_post_page', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}
	if ( has_block( 'pnb/wydarzenia', $post ) ) {
		// ta strona MA blok wydarzeń → zapamiętaj ją jako stronę Events
		update_option( 'pnb_wydarzenia_strona', (int) $post_id );
	} elseif ( (int) get_option( 'pnb_wydarzenia_strona', 0 ) === (int) $post_id ) {
		// blok usunięto z zapamiętanej strony → wyczyść (nie wskazuj strony bez bloku)
		delete_option( 'pnb_wydarzenia_strona' );
	}
}, 10, 2 );

/* Bezpiecznik: jeśli opcja pusta (świeża instalacja / strona zapisana przed pluginem), wykryj stronę Events
 * raz na starcie admina — jednorazowy skan stron z blokiem. Tani (tylko gdy opcja pusta). */
add_action( 'admin_init', function () {
	if ( get_option( 'pnb_wydarzenia_strona' ) ) {
		return; // już wykryta
	}
	$strony = get_posts( array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'numberposts' => 50,
		'fields'      => 'ids',
	) );
	foreach ( $strony as $sid ) {
		if ( has_block( 'pnb/wydarzenia', $sid ) ) {
			update_option( 'pnb_wydarzenia_strona', (int) $sid );
			break;
		}
	}
} );

/* ==== USTAWIENIA WYDARZEŃ: podstrona „Settings" pod menu Events + pole email na powiadomienia ====
 * Klient chce email w JEDNYM miejscu (decyzja 2026-07-05). Menu Events (CPT show_in_menu=true) dostaje
 * podstronę Settings. Pole email było wcześniej osierocone (w usuniętym panelu galerii) — tu je ożywiamy. */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=pnb_wydarzenie',        // rodzic = menu Events (CPT)
		__( 'Events settings', 'pnb-toolkit' ),      // <title>
		__( 'Settings', 'pnb-toolkit' ),             // etykieta w menu
		'manage_options',
		'pnb-events-settings',
		'pnb_events_ekran_ustawien'
	);
} );

/* Rejestracja opcji email w WŁASNEJ grupie (nie w cudzej „pnb_galeria" — czysto). Sanityzacja = email. */
add_action( 'admin_init', function () {
	register_setting( 'pnb_events_settings', 'pnb_kalendarz_email', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_email',
		'default'           => '',
	) );
	// Stałe tło hero podstrony Events (ID załącznika). Gdy ustawione — hero NIE zmienia się od
	// (scrapowanych) wydarzeń, tylko pokazuje to zdjęcie. Puste = fallback: featured 1. wydarzenia.
	register_setting( 'pnb_events_settings', 'pnb_events_hero_id', array(
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'default'           => 0,
	) );
	// Źródło importu (URL Eventbrite). Puste = automat nic nie robi. Klient wkleja adres kategorii.
	register_setting( 'pnb_events_settings', 'pnb_importer_source_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	) );
} );

/* AKTYWNE POWIADOMIENIE: żółty pasek w kokpicie WP gdy importer stanął (>3h bez syncu). Admin widzi
 * od razu po zalogowaniu — nie musi zaglądać na ekran stanu. Bez emaila (wp_mail→spam, zawodny). */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$st = get_option( 'pnb_scraper_status', array() );
	if ( empty( $st ) || empty( $st['zapisano'] ) ) {
		return; // scraper nigdy nie wysłał statusu — może dopiero wdrażany, nie strasz
	}
	$godzin = ( time() - strtotime( $st['zapisano'] ) ) / 3600;
	$link   = admin_url( 'edit.php?post_type=pnb_wydarzenie&page=pnb-events-settings' );
	if ( $godzin >= 3 ) {
		printf(
			'<div class="notice notice-warning"><p>⚠️ <strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Event importer stopped:', 'pnb-toolkit' ),
			esc_html( sprintf(
				/* translators: %d = hours since last sync */
				_n( 'no sync for %d hour — it may have crashed.', 'no sync for %d hours — it may have crashed.', (int) $godzin, 'pnb-toolkit' ),
				(int) $godzin
			) ),
			esc_url( $link ),
			esc_html__( 'Check status', 'pnb-toolkit' )
		);
	} elseif ( ! empty( $st['spadek_alert'] ) ) {
		printf(
			'<div class="notice notice-warning"><p>⚠️ <strong>%s</strong> <a href="%s">%s</a></p></div>',
			esc_html__( 'Event source returned far fewer events than usual — it may have changed. Expiry is paused for safety.', 'pnb-toolkit' ),
			esc_url( $link ),
			esc_html__( 'Check status', 'pnb-toolkit' )
		);
	}
} );

/* Przycisk „Sync now" — odpala jeden cykl importu na żądanie (bez czekania na cron). */
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['pnb_test_import'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'pnb_test_import' ) ) {
		return;
	}
	if ( function_exists( 'pnb_importer_jeden_cykl' ) ) {
		pnb_importer_jeden_cykl();
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=pnb_wydarzenie&page=pnb-events-settings&pnb_synced=1' ) );
	exit;
} );

/* Ekran Settings — jedno pole: email na powiadomienia o zapisach. Prosty, dla laika. */
function pnb_events_ekran_ustawien() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$admin_mail = get_option( 'admin_email' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Events settings', 'pnb-toolkit' ); ?></h1>

		<?php
		// ── MONITORING: stan automatu (importera). Pokazuje że działa — bez wchodzenia na serwer. ──
		$st = get_option( 'pnb_scraper_status', array() );
		if ( ! empty( $st ) && ! empty( $st['zapisano'] ) ) {
			$minut = max( 0, (int) round( ( time() - strtotime( $st['zapisano'] ) ) / 60 ) );
			// świeżo (<30 min) = zielono, dawno = ostrzeżenie (automat mógł stanąć).
			$swieze = $minut < 30;
			$kolor  = $swieze ? '#008a20' : '#b32d2e';
			$tlo    = $swieze ? '#edfaef' : '#fcf0f1';
			?>
			<div style="max-width:760px;margin:14px 0 24px;padding:16px 18px;background:<?php echo esc_attr( $tlo ); ?>;border:1px solid <?php echo esc_attr( $kolor ); ?>33;border-left:4px solid <?php echo esc_attr( $kolor ); ?>;border-radius:8px;">
				<strong style="font-size:14px;">🤖 <?php esc_html_e( 'Event importer status', 'pnb-toolkit' ); ?></strong>
				<span style="color:<?php echo esc_attr( $kolor ); ?>;font-weight:600;margin-left:8px;">
					<?php
					/* translators: %d = minutes since last sync */
					printf( esc_html( _n( 'last sync %d minute ago', 'last sync %d minutes ago', $minut, 'pnb-toolkit' ) ), (int) $minut );
					echo $swieze ? ' ✓' : ' ⚠️';
					?>
				</span>
				<?php if ( ! $swieze ) : ?>
					<p style="margin:8px 0 0;color:#b32d2e;"><?php esc_html_e( 'The importer has not run recently. WordPress only runs scheduled tasks when someone visits the site — on a quiet site it can lag. Fix: ask your host to add a real cron job (one line), or use a free pinger like cron-job.org to visit your site every 10 minutes.', 'pnb-toolkit' ); ?></p>
				<?php endif; ?>
				<div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;font-size:13px;color:#50575e;">
					<span><?php esc_html_e( 'Fetched', 'pnb-toolkit' ); ?>: <strong><?php echo (int) ( $st['pobrane'] ?? 0 ); ?></strong></span>
					<span><?php esc_html_e( 'Added', 'pnb-toolkit' ); ?>: <strong><?php echo (int) ( $st['wyslane'] ?? 0 ); ?></strong></span>
					<span><?php esc_html_e( 'On site', 'pnb-toolkit' ); ?>: <strong><?php echo (int) ( $st['juz_jest'] ?? 0 ); ?></strong></span>
					<span><?php esc_html_e( 'Expired→trash', 'pnb-toolkit' ); ?>: <strong><?php echo (int) ( $st['wygasle'] ?? 0 ); ?></strong></span>
					<span style="<?php echo ( (int) ( $st['bledy'] ?? 0 ) > 0 ) ? 'color:#b32d2e;font-weight:600;' : ''; ?>"><?php esc_html_e( 'Errors', 'pnb-toolkit' ); ?>: <strong><?php echo (int) ( $st['bledy'] ?? 0 ); ?></strong></span>
				</div>
				<?php if ( ! empty( $st['spadek_alert'] ) ) : ?>
					<p style="margin:8px 0 0;color:#b32d2e;">⚠️ <?php esc_html_e( 'Sudden drop in event count — the source may have changed. Expiry paused for safety.', 'pnb-toolkit' ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
		?>

		<p style="max-width:640px;color:#50575e;"><?php esc_html_e( 'Set the email address where sign-up notifications are sent. Every time someone signs up for an event, a message goes to this address. Sign-ups are always saved on the event itself too (see “Signed-up guests” when you edit an event), so you never lose them — even if the email fails.', 'pnb-toolkit' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'pnb_events_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pnb_importer_source_url">🤖 <?php esc_html_e( 'Event source (Eventbrite URL)', 'pnb-toolkit' ); ?></label></th>
					<td>
						<input type="url" id="pnb_importer_source_url" name="pnb_importer_source_url" class="widefat"
							value="<?php echo esc_attr( get_option( 'pnb_importer_source_url', '' ) ); ?>"
							placeholder="https://www.eventbrite.com/d/united-states/cats/">
						<p class="description" style="max-width:640px;">
							<?php esc_html_e( 'Paste an Eventbrite listing URL. The importer checks it every 10 minutes and adds new events automatically. Leave empty to turn the importer off.', 'pnb-toolkit' ); ?>
						</p>
						<details style="max-width:640px;margin-top:6px;">
							<summary style="cursor:pointer;color:#2271b1;"><?php esc_html_e( 'How does the 10-minute timing work? (important)', 'pnb-toolkit' ); ?></summary>
							<div style="margin-top:8px;padding:12px;background:#f6f7f7;border-radius:6px;font-size:13px;color:#50575e;">
								<p style="margin:0 0 8px;"><?php esc_html_e( 'WordPress runs scheduled tasks only when someone visits the site. On a busy site this is fine — visitors trigger the importer often enough. On a very quiet site it may lag (e.g. run only when someone finally visits).', 'pnb-toolkit' ); ?></p>
								<p style="margin:0;"><strong><?php esc_html_e( 'For clockwork timing (optional):', 'pnb-toolkit' ); ?></strong> <?php esc_html_e( 'ask your host to add a cron job hitting', 'pnb-toolkit' ); ?> <code><?php echo esc_html( home_url( '/wp-cron.php?doing_wp_cron' ) ); ?></code> <?php esc_html_e( 'every 10 minutes — or use a free service like cron-job.org to visit that URL. No code needed.', 'pnb-toolkit' ); ?></p>
							</div>
						</details>
						<?php if ( get_option( 'pnb_importer_source_url', '' ) ) : ?>
							<p style="margin-top:8px;">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=pnb_wydarzenie&page=pnb-events-settings&pnb_test_import=1' ), 'pnb_test_import' ) ); ?>" class="button">
									▶ <?php esc_html_e( 'Sync now (test)', 'pnb-toolkit' ); ?>
								</a>
								<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Run one import cycle right now instead of waiting.', 'pnb-toolkit' ); ?></span>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pnb_kalendarz_email"><?php esc_html_e( 'Notification email', 'pnb-toolkit' ); ?></label></th>
					<td>
						<input type="email" id="pnb_kalendarz_email" name="pnb_kalendarz_email" class="regular-text"
							value="<?php echo esc_attr( get_option( 'pnb_kalendarz_email', '' ) ); ?>"
							placeholder="<?php echo esc_attr( $admin_mail ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s = site admin email used as fallback */
								esc_html__( 'Leave empty to use the site admin email (%s).', 'pnb-toolkit' ),
								esc_html( $admin_mail )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Events page header image', 'pnb-toolkit' ); ?></th>
					<td>
						<?php
						$hero_id  = (int) get_option( 'pnb_events_hero_id', 0 );
						$hero_url = $hero_id ? wp_get_attachment_image_url( $hero_id, 'medium' ) : '';
						?>
						<div id="pnb-hero-podglad" style="margin-bottom:10px;<?php echo $hero_url ? '' : 'display:none;'; ?>">
							<img src="<?php echo esc_url( $hero_url ); ?>" style="max-width:320px;height:auto;border-radius:8px;display:block;border:1px solid #dcdcde;">
						</div>
						<input type="hidden" id="pnb_events_hero_id" name="pnb_events_hero_id" value="<?php echo esc_attr( $hero_id ); ?>">
						<button type="button" class="button" id="pnb-hero-wybierz"><?php echo $hero_id ? esc_html__( 'Change image', 'pnb-toolkit' ) : esc_html__( 'Choose image', 'pnb-toolkit' ); ?></button>
						<button type="button" class="button" id="pnb-hero-usun" style="<?php echo $hero_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'pnb-toolkit' ); ?></button>
						<p class="description" style="max-width:640px;">
							<?php esc_html_e( 'The big background image at the top of your Events page. Choose your own photo so it always looks the way you want. Leave empty and the page will use the photo of the next upcoming event instead.', 'pnb-toolkit' ); ?>
						</p>
						<script>
						jQuery( function () {
							var frame, btn = document.getElementById( 'pnb-hero-wybierz' );
							var pole = document.getElementById( 'pnb_events_hero_id' );
							var podglad = document.getElementById( 'pnb-hero-podglad' );
							var usun = document.getElementById( 'pnb-hero-usun' );
							if ( ! btn || ! window.wp || ! wp.media ) { return; }
							btn.addEventListener( 'click', function ( e ) {
								e.preventDefault();
								if ( frame ) { frame.open(); return; }
								frame = wp.media( { title: <?php echo wp_json_encode( __( 'Choose header image', 'pnb-toolkit' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use this image', 'pnb-toolkit' ) ); ?> }, multiple: false, library: { type: 'image' } } );
								frame.on( 'select', function () {
									var a = frame.state().get( 'selection' ).first().toJSON();
									pole.value = a.id;
									var url = ( a.sizes && a.sizes.medium && a.sizes.medium.url ) || a.url;
									podglad.querySelector( 'img' ).src = url;
									podglad.style.display = '';
									usun.style.display = '';
									btn.textContent = <?php echo wp_json_encode( __( 'Change image', 'pnb-toolkit' ) ); ?>;
								} );
								frame.open();
							} );
							if ( usun ) { usun.addEventListener( 'click', function ( e ) {
								e.preventDefault();
								pole.value = '';
								podglad.style.display = 'none';
								usun.style.display = 'none';
								btn.textContent = <?php echo wp_json_encode( __( 'Choose image', 'pnb-toolkit' ) ); ?>;
							} ); }
						} );
						</script>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'pnb-toolkit' ) ); ?>
		</form>
	</div>
	<?php
}

/* USUNIĘTE 2026-07-05 (domknięcie audytu): funkcja pnb_kalendarz_aktywacja() — była MARTWA (nigdzie
 * niewołana) i robiła wp_insert_post() tworząc stronę „events" u klienta. To stary błąd architektury
 * („plugin ZAMIAST WordPressa" — tworzył/podmieniał strony klienta). Aktywacją zajmuje się teraz
 * pnb_blocks_aktywacja() w pliku głównym: rejestruje CPT + flush_rewrite_rules, NIC nie tworzy u klienta.
 * Plugin działa OBOK WP. Jeśli klient chce stronę Events — tworzy ją sam i wstawia blok. */

/* ==== METABOX: szczegóły wydarzenia + lista zapisanych ==== */

/* Etykieta NAD natywnym tytułem/edytorem: prosta instrukcja co wpisać. Plugin tylko po ANGIELSKU
 * (decyzja klienta) — bez wzmianki o polskim. */
add_action( 'edit_form_top', function ( $post ) {
	if ( 'pnb_wydarzenie' !== $post->post_type ) {
		return;
	}
	echo '<div style="background:#eef6f8;border-left:4px solid #45A3B8;padding:12px 16px;margin:10px 0 4px;border-radius:4px;">';
	echo '<strong style="font-size:14px;">📅 ' . esc_html__( 'Event name and description', 'pnb-toolkit' ) . '</strong><br>';
	echo '<span style="color:#50575e;">' . esc_html__( 'Type the event name (title field below) and description (editor below).', 'pnb-toolkit' ) . '</span>';
	echo '</div>';
} );

add_action( 'add_meta_boxes', function () {
	// Szczegóły (data/godzina/miejsce) → zapisani goście. Metabox „po polsku" USUNIĘTY (plugin tylko EN).
	add_meta_box( 'pnb-wydarzenie-dane', __( 'Event details (date, time, place)', 'pnb-toolkit' ), 'pnb_kalendarz_metabox_dane', 'pnb_wydarzenie', 'normal', 'high' );
	// Zapisani goście: BOX „side" (prawa kolumna, obok publikacji) + tytuł z liczbą → widać ile zapisów
	// nawet bez rozwijania (audyt UX: panel był zwinięty, klient nie widział gości).
	add_meta_box( 'pnb-wydarzenie-zapisy', pnb_kalendarz_tytul_zapisy(), 'pnb_kalendarz_metabox_zapisy', 'pnb_wydarzenie', 'side', 'high' );
} );

/* Tytuł meta boxa gości Z LICZBĄ zapisów — widać „Zapisani goście (3)" nawet gdy box zwinięty. */
function pnb_kalendarz_tytul_zapisy() {
	global $post;
	$ile = $post ? pnb_kalendarz_ile_zapisow( $post->ID ) : 0;
	return sprintf(
		/* translators: %d = number of signed-up guests */
		__( '👥 Signed-up guests (%d)', 'pnb-toolkit' ),
		$ile
	);
}

/* Polskie przyciski WP na ekranie edycji wydarzenia (filtr gettext — wzorzec „Say What?" ze zwiadu,
 * celowany TYLKO na ten ekran, nie globalnie). Klient dodaje wydarzenie i widzi „Opublikuj" nie „Publish". */
add_filter( 'gettext', function ( $tlumaczenie, $tekst, $domena ) {
	if ( 'default' !== $domena || ! is_admin() ) {
		return $tlumaczenie;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'pnb_wydarzenie' !== $screen->post_type ) {
		return $tlumaczenie;
	}
	$mapa = array(
		'Publish'              => __( 'Publish event', 'pnb-toolkit' ),
		'Update'               => __( 'Save changes', 'pnb-toolkit' ),
		'Save Draft'           => __( 'Save draft', 'pnb-toolkit' ),
		'Preview'              => __( 'Preview', 'pnb-toolkit' ),
		'Preview Changes'      => __( 'View preview', 'pnb-toolkit' ),
		'Move to Trash'        => __( 'Delete event', 'pnb-toolkit' ),
		'Status'               => __( 'Status', 'pnb-toolkit' ),
		'Status:'              => __( 'Status:', 'pnb-toolkit' ),
		'Visibility'           => __( 'Visibility', 'pnb-toolkit' ),
		'Visibility:'          => __( 'Visibility:', 'pnb-toolkit' ),
		'Published'            => __( 'Published', 'pnb-toolkit' ),
		'Public'               => __( 'Public', 'pnb-toolkit' ),
		'Publish immediately'  => __( 'Publish immediately', 'pnb-toolkit' ),
		'Add title'            => __( 'Enter the event name', 'pnb-toolkit' ),
		'Add Media'            => __( 'Add image/file', 'pnb-toolkit' ),
		'Set featured image'   => __( 'Set event image', 'pnb-toolkit' ),
		'Featured image'       => __( 'Event image', 'pnb-toolkit' ),
		'Remove featured image' => __( 'Remove image', 'pnb-toolkit' ),
		'Click the image to edit or update' => __( 'Click the image to change it', 'pnb-toolkit' ),
		'Published on: %s'     => __( 'Published on: %s', 'pnb-toolkit' ),
	);
	return isset( $mapa[ $tekst ] ) ? $mapa[ $tekst ] : $tlumaczenie;
}, 10, 3 );

/* Media picker (wp.media) na ekranie edycji wydarzenia — dla pola „Event photo" w metaboxie. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && 'pnb_wydarzenie' === get_post_type() ) {
		wp_enqueue_media();
	}
	// Strona „Events settings" (media picker dla stałego tła hero). Hook submenu pod CPT.
	if ( false !== strpos( (string) $hook, 'pnb-events-settings' ) ) {
		wp_enqueue_media();
	}
} );

/* Wyłącz przycisk „Add Media" NAD edytorem opisu — TYLKO na ekranie wydarzenia (decyzja klienta 2026-07-05):
 * klient wrzucał główne zdjęcie DO OPISU zamiast przez „Set event photo" → dublowało się na karcie.
 * Zostaje sam edytor tekstu (pogrubienie/listy), znika pokusa wstawiania zdjęć do treści.
 * remove_action tylko dla pnb_wydarzenie → inne strony/CPT NIETKNIĘTE (plugin działa OBOK, nie ZAMIAST). */
add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( $screen && 'pnb_wydarzenie' === $screen->post_type ) {
		remove_action( 'media_buttons', 'media_buttons' );
	}
} );

function pnb_kalendarz_metabox_dane( $post ) {
	wp_nonce_field( 'pnb_wydarzenie_dane', 'pnb_wydarzenie_nonce' );
	$data    = get_post_meta( $post->ID, '_pnb_event_date', true );
	$godzina = get_post_meta( $post->ID, '_pnb_event_time', true );
	$miejsce = get_post_meta( $post->ID, '_pnb_event_place', true );
	$limit   = (int) get_post_meta( $post->ID, '_pnb_event_limit', true );
	$kat     = get_post_meta( $post->ID, '_pnb_event_cat', true );
	// Główne zdjęcie wydarzenia = Featured image (WP). Tu wyraźny przycisk, żeby klient nie szukał
	// (wcześniej wstawiał zdjęcie do OPISU zamiast jako główne → puste miejsce na karcie).
	$foto_id  = (int) get_post_thumbnail_id( $post->ID );
	$foto_url = $foto_id ? wp_get_attachment_image_url( $foto_id, 'medium' ) : '';
	?>
	<div class="pnb-ev-foto-pole" style="margin:0 0 18px;padding:14px;background:#fbf7f5;border:1px solid #f0dcd6;border-radius:8px;">
		<strong style="display:block;margin-bottom:8px;">📷 <?php esc_html_e( 'Event photo (shown on the card and page)', 'pnb-toolkit' ); ?></strong>
		<div id="pnb-ev-foto-podglad" style="margin-bottom:10px;<?php echo $foto_url ? '' : 'display:none;'; ?>">
			<img src="<?php echo esc_url( $foto_url ); ?>" style="max-width:220px;height:auto;border-radius:8px;display:block;">
		</div>
		<input type="hidden" id="pnb-ev-foto-id" name="pnb_event_foto_id" value="<?php echo esc_attr( $foto_id ); ?>">
		<button type="button" class="button button-primary" id="pnb-ev-foto-wybierz"><?php echo $foto_id ? esc_html__( 'Change photo', 'pnb-toolkit' ) : esc_html__( 'Set event photo', 'pnb-toolkit' ); ?></button>
		<button type="button" class="button" id="pnb-ev-foto-usun" style="<?php echo $foto_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'pnb-toolkit' ); ?></button>
		<span class="description" style="display:block;margin-top:8px;"><?php esc_html_e( 'This is the main event photo. Add it here — not inside the description.', 'pnb-toolkit' ); ?></span>
	</div>
	<script>
	// jQuery(document).ready: inline <script> w metaboxie wykonuje się ZANIM wp.media jest gotowe
	// (wp_enqueue_media ładuje media-views w footerze). Bez tego listener się nie podpinał → przycisk
	// nie otwierał pickera. Docs WP: nie odpalać wp.media inline, owinąć w ready(). (naprawa 2026-07-05)
	jQuery( function () {
		var frame, btn = document.getElementById( 'pnb-ev-foto-wybierz' );
		var pole = document.getElementById( 'pnb-ev-foto-id' );
		var podglad = document.getElementById( 'pnb-ev-foto-podglad' );
		var usun = document.getElementById( 'pnb-ev-foto-usun' );
		if ( ! btn || ! window.wp || ! wp.media ) { return; }
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) { frame.open(); return; }
			frame = wp.media( { title: <?php echo wp_json_encode( __( 'Choose event photo', 'pnb-toolkit' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use this photo', 'pnb-toolkit' ) ); ?> }, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				pole.value = a.id;
				var url = ( a.sizes && a.sizes.medium && a.sizes.medium.url ) || a.url;
				podglad.querySelector( 'img' ).src = url;
				podglad.style.display = '';
				usun.style.display = '';
				btn.textContent = <?php echo wp_json_encode( __( 'Change photo', 'pnb-toolkit' ) ); ?>;
			} );
			frame.open();
		} );
		if ( usun ) { usun.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			pole.value = '';
			podglad.style.display = 'none';
			usun.style.display = 'none';
			btn.textContent = <?php echo wp_json_encode( __( 'Set event photo', 'pnb-toolkit' ) ); ?>;
		} ); }
	} );
	</script>
	<p><label><?php esc_html_e( 'Date', 'pnb-toolkit' ); ?><br>
		<input type="date" name="pnb_event_date" value="<?php echo esc_attr( $data ); ?>" required></label></p>
	<p><label><?php esc_html_e( 'Start time', 'pnb-toolkit' ); ?><br>
		<input type="time" name="pnb_event_time" value="<?php echo esc_attr( $godzina ); ?>"></label>
	&nbsp;&nbsp;<label><?php esc_html_e( 'until (optional)', 'pnb-toolkit' ); ?><br>
		<input type="time" name="pnb_event_time_end" value="<?php echo esc_attr( get_post_meta( $post->ID, '_pnb_event_time_end', true ) ); ?>"></label></p>
	<p><label><?php esc_html_e( 'Place', 'pnb-toolkit' ); ?><br>
		<input type="text" name="pnb_event_place" value="<?php echo esc_attr( $miejsce ); ?>" class="widefat"></label></p>
	<p><label><?php esc_html_e( 'Seat limit', 'pnb-toolkit' ); ?><br>
		<input type="number" name="pnb_event_limit" min="0" value="<?php echo esc_attr( $limit ); ?>">
		<span class="description" style="display:block;margin-top:3px;"><?php esc_html_e( 'How many people can sign up. Enter 0 (or leave empty) = no limit.', 'pnb-toolkit' ); ?></span></label></p>
	<p><label><?php esc_html_e( 'Category', 'pnb-toolkit' ); ?><br>
		<select name="pnb_event_cat">
		<?php foreach ( pnb_kalendarz_kategorie() as $klucz => $nazwa ) : ?>
			<option value="<?php echo esc_attr( $klucz ); ?>" <?php selected( $kat ? $kat : 'other', $klucz ); ?>><?php echo esc_html( $nazwa ); ?></option>
		<?php endforeach; ?>
		</select></label></p>

	<?php
	// ── Dodatkowe pola (te same, które pokazują się na stronie wydarzenia). Wcześniej
	//    wypełniał je TYLKO import (scraper) — własne wydarzenia klienta były na nie ślepe.
	//    Wartości dropdownów = dokładne klucze whitelisty z pnb_kalendarz_good_to_know_html().
	$price   = get_post_meta( $post->ID, '_pnb_event_price', true );
	$address = get_post_meta( $post->ID, '_pnb_event_address', true );
	$hl      = get_post_meta( $post->ID, '_pnb_event_highlights', true );
	$hl      = is_array( $hl ) ? $hl : array();
	$refund  = get_post_meta( $post->ID, '_pnb_event_refund', true );
	$src_url = get_post_meta( $post->ID, '_pnb_source_url', true );
	$sel     = function ( $val, $opt ) { return (string) $val === (string) $opt ? ' selected' : ''; };
	?>
	<hr style="margin:16px 0;border:0;border-top:1px solid #f0dcd6;">
	<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Extra details (optional — shown on the event page)', 'pnb-toolkit' ); ?></strong></p>

	<p><label>🎫 <?php esc_html_e( 'Price', 'pnb-toolkit' ); ?><br>
		<input type="text" name="pnb_event_price" value="<?php echo esc_attr( $price ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. 20 USD, Free, £15', 'pnb-toolkit' ); ?>">
		<span class="description" style="display:block;margin-top:3px;"><?php esc_html_e( 'Leave empty if not applicable. Type "Free" for free events.', 'pnb-toolkit' ); ?></span></label></p>

	<p><label>📍 <?php esc_html_e( 'Full address (for the map / directions)', 'pnb-toolkit' ); ?><br>
		<input type="text" name="pnb_event_address" value="<?php echo esc_attr( $address ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Street, city, postcode', 'pnb-toolkit' ); ?>">
		<span class="description" style="display:block;margin-top:3px;"><?php esc_html_e( 'Used for the "Get directions" button. Leave empty to use the Place name above.', 'pnb-toolkit' ); ?></span></label></p>

	<p style="margin-bottom:4px;"><strong>ℹ️ <?php esc_html_e( 'Good to know', 'pnb-toolkit' ); ?></strong></p>
	<p style="display:flex;gap:14px;flex-wrap:wrap;">
		<label><?php esc_html_e( 'Age', 'pnb-toolkit' ); ?><br>
			<select name="pnb_event_hl_age">
				<option value=""<?php echo $sel( $hl['age'] ?? '', '' ); ?>><?php esc_html_e( '— none —', 'pnb-toolkit' ); ?></option>
				<option value="all_ages"<?php echo $sel( $hl['age'] ?? '', 'all_ages' ); ?>><?php esc_html_e( 'All ages welcome', 'pnb-toolkit' ); ?></option>
				<option value="under_16_with_guardian"<?php echo $sel( $hl['age'] ?? '', 'under_16_with_guardian' ); ?>><?php esc_html_e( 'Under 16 with guardian', 'pnb-toolkit' ); ?></option>
				<option value="under_18_with_guardian"<?php echo $sel( $hl['age'] ?? '', 'under_18_with_guardian' ); ?>><?php esc_html_e( 'Under 18 with guardian', 'pnb-toolkit' ); ?></option>
				<option value="over_18"<?php echo $sel( $hl['age'] ?? '', 'over_18' ); ?>><?php esc_html_e( '18 and over', 'pnb-toolkit' ); ?></option>
				<option value="over_21"<?php echo $sel( $hl['age'] ?? '', 'over_21' ); ?>><?php esc_html_e( '21 and over', 'pnb-toolkit' ); ?></option>
			</select></label>
		<label><?php esc_html_e( 'Parking', 'pnb-toolkit' ); ?><br>
			<select name="pnb_event_hl_parking">
				<option value=""<?php echo $sel( $hl['parking'] ?? '', '' ); ?>><?php esc_html_e( '— none —', 'pnb-toolkit' ); ?></option>
				<option value="free"<?php echo $sel( $hl['parking'] ?? '', 'free' ); ?>><?php esc_html_e( 'Free parking', 'pnb-toolkit' ); ?></option>
				<option value="paid"<?php echo $sel( $hl['parking'] ?? '', 'paid' ); ?>><?php esc_html_e( 'Paid parking', 'pnb-toolkit' ); ?></option>
				<option value="no"<?php echo $sel( $hl['parking'] ?? '', 'no' ); ?>><?php esc_html_e( 'No parking', 'pnb-toolkit' ); ?></option>
			</select></label>
		<label><?php esc_html_e( 'Format', 'pnb-toolkit' ); ?><br>
			<select name="pnb_event_hl_location">
				<option value=""<?php echo $sel( $hl['location_type'] ?? '', '' ); ?>><?php esc_html_e( '— none —', 'pnb-toolkit' ); ?></option>
				<option value="in_person"<?php echo $sel( $hl['location_type'] ?? '', 'in_person' ); ?>><?php esc_html_e( 'In person', 'pnb-toolkit' ); ?></option>
				<option value="online"<?php echo $sel( $hl['location_type'] ?? '', 'online' ); ?>><?php esc_html_e( 'Online', 'pnb-toolkit' ); ?></option>
			</select></label>
		<label><?php esc_html_e( 'Duration (min)', 'pnb-toolkit' ); ?><br>
			<input type="number" name="pnb_event_hl_duration" min="0" style="width:90px;" value="<?php echo esc_attr( $hl['duration_min'] ?? '' ); ?>"></label>
	</p>

	<p><label>↩️ <?php esc_html_e( 'Refund policy', 'pnb-toolkit' ); ?><br>
		<select name="pnb_event_refund">
			<option value=""<?php echo $sel( $refund, '' ); ?>><?php esc_html_e( '— none —', 'pnb-toolkit' ); ?></option>
			<option value="no_refunds"<?php echo $sel( $refund, 'no_refunds' ); ?>><?php esc_html_e( 'No refunds', 'pnb-toolkit' ); ?></option>
			<option value="refund_30"<?php echo $sel( $refund, 'refund_30' ); ?>><?php esc_html_e( 'Refunds up to 30 days before', 'pnb-toolkit' ); ?></option>
			<option value="refund_7"<?php echo $sel( $refund, 'refund_7' ); ?>><?php esc_html_e( 'Refunds up to 7 days before', 'pnb-toolkit' ); ?></option>
			<option value="refund_1"<?php echo $sel( $refund, 'refund_1' ); ?>><?php esc_html_e( 'Refunds up to 1 day before', 'pnb-toolkit' ); ?></option>
		</select></label></p>

	<?php if ( $src_url ) : ?>
	<p style="margin-top:10px;padding:8px;background:#f4f9f7;border:1px solid #d5ebe4;border-radius:6px;">
		🔗 <strong><?php esc_html_e( 'Imported event', 'pnb-toolkit' ); ?></strong> —
		<?php esc_html_e( 'this event was added automatically. Source:', 'pnb-toolkit' ); ?>
		<a href="<?php echo esc_url( $src_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $src_url ); ?></a>
		<?php if ( get_post_meta( $post->ID, '_pnb_locked', true ) ) : ?>
			<span style="display:block;margin-top:8px;padding-top:8px;border-top:1px solid #d5ebe4;">
				🔒 <strong><?php esc_html_e( 'You edited this event', 'pnb-toolkit' ); ?></strong> —
				<?php esc_html_e( 'the importer keeps your title & description, but still updates facts (date, place, price) and cancellations from the source.', 'pnb-toolkit' ); ?>
			</span>
			<label style="display:block;margin-top:8px;">
				<input type="checkbox" name="pnb_event_unlock" value="1">
				<?php esc_html_e( 'Sync everything with the source again (discard my manual title/description)', 'pnb-toolkit' ); ?>
			</label>
		<?php else : ?>
			<span class="description" style="display:block;margin-top:3px;"><?php esc_html_e( '(set by the importer — shown as the "View event & tickets" button)', 'pnb-toolkit' ); ?></span>
		<?php endif; ?>
	</p>
	<?php endif; ?>
	<?php
}

/* USUNIĘTE (decyzja klienta): metabox „Wersja polska" + jego zapis. Plugin tylko po ANGIELSKU —
 * żadnych pól/notek o polskim w edycji wydarzenia. Helpery front (niżej) zwracają czysto EN. */

/* Helpery front: zwróć nazwę/opis/miejsce. Plugin tylko EN — natywne pola wydarzenia (bez PL). */
function pnb_event_tytul( $id ) {
	return get_the_title( $id );
}
function pnb_event_opis( $id ) {
	return (string) get_post_field( 'post_content', $id );
}

/**
 * „Co warto wiedzieć" (Good to know) — praktyczne info: parking, wiek, godzina drzwi,
 * czas trwania, forma (na miejscu/online), polityka zwrotów. Renderowane tylko gdy są dane.
 * Wartości surowe (z importu) mapowane na czytelne etykiety; własne wydarzenia klienta też
 * mogą mieć te meta (przez edytor).
 */
function pnb_kalendarz_good_to_know_html( $id ) {
	$id    = (int) $id;
	$hl    = get_post_meta( $id, '_pnb_event_highlights', true );
	$refund = get_post_meta( $id, '_pnb_event_refund', true );
	$items = array();

	if ( is_array( $hl ) ) {
		// wiek
		$mapa_wiek = array(
			'all_ages'                 => __( 'All ages welcome', 'pnb-toolkit' ),
			'under_16_with_guardian'   => __( 'Under 16 with a parent or guardian', 'pnb-toolkit' ),
			'under_18_with_guardian'   => __( 'Under 18 with a parent or guardian', 'pnb-toolkit' ),
			'over_18'                  => __( '18 and over', 'pnb-toolkit' ),
			'over_21'                  => __( '21 and over', 'pnb-toolkit' ),
		);
		if ( ! empty( $hl['age'] ) && isset( $mapa_wiek[ $hl['age'] ] ) ) {
			$items[] = array( 'ticket', $mapa_wiek[ $hl['age'] ] );
		}
		// parking
		if ( ! empty( $hl['parking'] ) ) {
			$mapa_p  = array(
				'free' => __( 'Free parking', 'pnb-toolkit' ),
				'paid' => __( 'Paid parking', 'pnb-toolkit' ),
				'no'   => __( 'No parking', 'pnb-toolkit' ),
			);
			if ( isset( $mapa_p[ $hl['parking'] ] ) ) {
				$items[] = array( 'pinezka', $mapa_p[ $hl['parking'] ] );
			}
		}
		// forma
		if ( ! empty( $hl['location_type'] ) ) {
			$items[] = array(
				'pinezka',
				'online' === $hl['location_type'] ? __( 'Online event', 'pnb-toolkit' ) : __( 'In person', 'pnb-toolkit' ),
			);
		}
		// godzina drzwi (z ISO → HH:MM). WAŻNE: pokazujemy godzinę W STREFIE WYDARZENIA (np. -07 dla
		// Kalifornii), NIE w strefie serwera — inaczej „19:00" pokazywało się jako „2:00 am" (bug strefy).
		// DateTime zachowuje offset z ISO stringa; format() nie konwertuje do strefy serwera.
		if ( ! empty( $hl['door_time'] ) ) {
			try {
				$dt = new DateTime( (string) $hl['door_time'] );
				$fmt = (string) get_option( 'time_format', 'g:i A' );
				/* translators: %s: godzina otwarcia drzwi */
				$items[] = array( 'plus', sprintf( __( 'Doors at %s', 'pnb-toolkit' ), $dt->format( $fmt ) ) );
			} catch ( Exception $e ) {
				// niepoprawny format door_time → pomijamy (nie psujemy sekcji)
			}
		}
		// czas trwania (minuty → h/min)
		if ( ! empty( $hl['duration_min'] ) ) {
			$m = (int) $hl['duration_min'];
			$t = $m >= 60
				/* translators: 1: godziny, 2: minuty */
				? trim( sprintf( __( '%1$dh %2$dmin', 'pnb-toolkit' ), intdiv( $m, 60 ), $m % 60 ) )
				/* translators: %d: minuty */
				: sprintf( __( '%d min', 'pnb-toolkit' ), $m );
			$items[] = array( 'plus', sprintf( '%s: %s', __( 'Duration', 'pnb-toolkit' ), $t ) );
		}
	}

	// polityka zwrotów (osobna „karta" obok highlightów)
	$refund_txt = '';
	if ( $refund ) {
		$mapa_r = array(
			'no_refunds'   => __( 'No refunds', 'pnb-toolkit' ),
			'refund_30'    => __( 'Refunds up to 30 days before the event', 'pnb-toolkit' ),
			'refund_7'     => __( 'Refunds up to 7 days before the event', 'pnb-toolkit' ),
			'refund_1'     => __( 'Refunds up to 1 day before the event', 'pnb-toolkit' ),
		);
		$refund_txt = isset( $mapa_r[ $refund ] ) ? $mapa_r[ $refund ] : '';
	}

	if ( ! $items && ! $refund_txt ) {
		return '';
	}

	$out = '<section class="pnb-evs-gtk"><h3 class="pnb-evs-gtk-title">' . esc_html__( 'Good to know', 'pnb-toolkit' ) . '</h3>';
	$out .= '<div class="pnb-evs-gtk-grid">';
	if ( $items ) {
		$out .= '<div class="pnb-evs-gtk-card"><h4>' . esc_html__( 'Highlights', 'pnb-toolkit' ) . '</h4><ul>';
		foreach ( $items as $it ) {
			$out .= '<li>' . pnb_kalendarz_ikona( $it[0] ) . ' <span>' . esc_html( $it[1] ) . '</span></li>';
		}
		$out .= '</ul></div>';
	}
	if ( $refund_txt ) {
		$out .= '<div class="pnb-evs-gtk-card"><h4>' . esc_html__( 'Refund policy', 'pnb-toolkit' ) . '</h4><p>' . esc_html( $refund_txt ) . '</p></div>';
	}
	$out .= '</div></section>';
	return $out;
}
function pnb_event_miejsce( $id, $miejsce_en ) {
	return $miejsce_en;
}

/* Hero „słowa-schodki" z JEDNEGO tłumaczonego stringu: każde słowo w masce, ostatnie = akcent+kropka.
 * Działa po EN i PL bez hardkodowania osobnych słów. */
function pnb_kalendarz_hero_slowa( $tekst ) {
	$tekst = trim( $tekst );
	$kropka = '';
	if ( '' !== $tekst && '.' === substr( $tekst, -1 ) ) {
		$kropka = '<span class="pnb-ev-h1dot">.</span>';
		$tekst  = rtrim( $tekst, '.' );
	}
	$slowa = preg_split( '/\s+/', $tekst );
	$n = count( $slowa );
	$out = '';
	foreach ( $slowa as $i => $slowo ) {
		$ostatnie = ( $i === $n - 1 );
		$tresc = $ostatnie ? '<em>' . esc_html( $slowo ) . '</em>' . $kropka : esc_html( $slowo );
		$out  .= '<span class="pnb-wm"><span class="pnb-wi">' . $tresc . '</span></span>' . ( $ostatnie ? '' : ' ' );
	}
	return $out;
}

/* Kategorie wydarzeń: chips filtrów + tagi z kropką koloru na kartach. */
function pnb_kalendarz_kategorie() {
	return array(
		'adoption' => __( 'Adoption', 'pnb-toolkit' ),
		'class'    => __( 'Class', 'pnb-toolkit' ),
		'openday'  => __( 'Open day', 'pnb-toolkit' ),
		'other'    => __( 'Other', 'pnb-toolkit' ),
	);
}

add_action( 'save_post_pnb_wydarzenie', function ( $post_id ) {
	// bramki: autosave / nonce / capability (nonce ≠ autoryzacja — twarda para)
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! isset( $_POST['pnb_wydarzenie_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pnb_wydarzenie_nonce'] ), 'pnb_wydarzenie_dane' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	// UNLOCK ma priorytet: gdy admin zaznaczył „sync again", oddaje wydarzenie pod scraper (nie lockujemy).
	$chce_unlock = ( isset( $_POST['pnb_event_unlock'] ) && '1' === $_POST['pnb_event_unlock'] );
	// Admin RĘCZNIE zapisał importowane wydarzenie (ma source_id) → oznacz „przejęte": scraper NIE
	// nadpisze jego treści (tytuł/opis) przy sync (fakty i tak aktualizuje). Chyba że chce unlock.
	if ( ! $chce_unlock && get_post_meta( $post_id, '_pnb_source_id', true ) ) {
		update_post_meta( $post_id, '_pnb_locked', 1 );
	}
	$kat = isset( $_POST['pnb_event_cat'] ) ? sanitize_key( wp_unslash( $_POST['pnb_event_cat'] ) ) : '';
	if ( ! array_key_exists( $kat, pnb_kalendarz_kategorie() ) ) {
		$kat = 'other'; // whitelist — meta nigdy nie trzyma wartości spoza słownika
	}
	$pola = array(
		'_pnb_event_date'  => isset( $_POST['pnb_event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_date'] ) ) : '',
		'_pnb_event_time'  => isset( $_POST['pnb_event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_time'] ) ) : '',
		'_pnb_event_time_end' => isset( $_POST['pnb_event_time_end'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_time_end'] ) ) : '',
		'_pnb_event_place' => isset( $_POST['pnb_event_place'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_place'] ) ) : '',
		'_pnb_event_limit' => isset( $_POST['pnb_event_limit'] ) ? absint( $_POST['pnb_event_limit'] ) : 0,
		'_pnb_event_cat'   => $kat,
		// Dodatkowe pola (te same co na stronie) — teraz edytowalne dla WŁASNYCH wydarzeń klienta.
		'_pnb_event_price'   => isset( $_POST['pnb_event_price'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_price'] ) ) : '',
		'_pnb_event_address' => isset( $_POST['pnb_event_address'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_event_address'] ) ) : '',
	);
	// Refund: whitelist (meta nigdy nie trzyma wartości spoza słownika — inaczej strona jej nie wyświetli).
	$refund_ok = array( '', 'no_refunds', 'refund_30', 'refund_7', 'refund_1' );
	$refund    = isset( $_POST['pnb_event_refund'] ) ? sanitize_key( wp_unslash( $_POST['pnb_event_refund'] ) ) : '';
	$pola['_pnb_event_refund'] = in_array( $refund, $refund_ok, true ) ? $refund : '';
	// Highlights: tablica z whitelistą kluczy/wartości (dokładnie jak wypełnia furtka/scraper).
	$age_ok  = array( '', 'all_ages', 'under_16_with_guardian', 'under_18_with_guardian', 'over_18', 'over_21' );
	$park_ok = array( '', 'free', 'paid', 'no' );
	$loc_ok  = array( '', 'in_person', 'online' );
	$age     = isset( $_POST['pnb_event_hl_age'] ) ? sanitize_key( wp_unslash( $_POST['pnb_event_hl_age'] ) ) : '';
	$park    = isset( $_POST['pnb_event_hl_parking'] ) ? sanitize_key( wp_unslash( $_POST['pnb_event_hl_parking'] ) ) : '';
	$loc     = isset( $_POST['pnb_event_hl_location'] ) ? sanitize_key( wp_unslash( $_POST['pnb_event_hl_location'] ) ) : '';
	$dur     = isset( $_POST['pnb_event_hl_duration'] ) ? absint( $_POST['pnb_event_hl_duration'] ) : 0;
	$hl      = array();
	if ( in_array( $age, $age_ok, true ) && $age )   $hl['age']           = $age;
	if ( in_array( $park, $park_ok, true ) && $park ) $hl['parking']       = $park;
	if ( in_array( $loc, $loc_ok, true ) && $loc )   $hl['location_type'] = $loc;
	if ( $dur )                                       $hl['duration_min']  = $dur;
	// door_time z importu (ISO) — jeśli było i klient nie ruszał, zachowaj.
	$hl_stare = get_post_meta( $post_id, '_pnb_event_highlights', true );
	if ( is_array( $hl_stare ) && ! empty( $hl_stare['door_time'] ) ) {
		$hl['door_time'] = $hl_stare['door_time'];
	}
	if ( $hl ) {
		update_post_meta( $post_id, '_pnb_event_highlights', $hl );
	} else {
		delete_post_meta( $post_id, '_pnb_event_highlights' );
	}
	foreach ( $pola as $klucz => $wartosc ) {
		update_post_meta( $post_id, $klucz, $wartosc );
	}
	// Główne zdjęcie wydarzenia = Featured image. Pole „Event photo" z metaboxu (id wybranego zdjęcia).
	if ( isset( $_POST['pnb_event_foto_id'] ) ) {
		$foto_id = absint( $_POST['pnb_event_foto_id'] );
		if ( $foto_id ) {
			set_post_thumbnail( $post_id, $foto_id );
			delete_post_meta( $post_id, '_pnb_img_removed' ); // admin dał zdjęcie → cofnij „usunięte"
		} else {
			delete_post_thumbnail( $post_id );
			// Na wydarzeniu importowanym: admin ŚWIADOMIE usunął zdjęcie → scraper go nie przywraca.
			if ( get_post_meta( $post_id, '_pnb_source_id', true ) ) {
				update_post_meta( $post_id, '_pnb_img_removed', 1 );
			}
		}
	}
	// UNLOCK: checkbox „Sync with source again" → admin oddaje wydarzenie z powrotem pod scraper.
	if ( isset( $_POST['pnb_event_unlock'] ) && '1' === $_POST['pnb_event_unlock'] ) {
		delete_post_meta( $post_id, '_pnb_locked' );
	}
} );

function pnb_kalendarz_zapisy_wydarzenia( $event_id ) {
	return get_posts( array(
		'post_type'      => 'pnb_zapis',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_key'       => '_pnb_zapis_wydarzenie',
		'meta_value'     => (int) $event_id,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
}

/* Samo LICZENIE zapisów: fields=ids + cache per request (strona jest no-cache — bez tego N+1 przy każdym hicie). */
function pnb_kalendarz_ile_zapisow( $event_id ) {
	static $cache = array();
	$event_id = (int) $event_id;
	if ( isset( $cache[ $event_id ] ) ) {
		return $cache[ $event_id ];
	}
	$ids = get_posts( array(
		'post_type'      => 'pnb_zapis',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_key'       => '_pnb_zapis_wydarzenie',
		'meta_value'     => $event_id,
	) );
	$cache[ $event_id ] = count( $ids );
	return $cache[ $event_id ];
}

/* RODO: kasujesz wydarzenie = kasują się jego zapisy (inaczej dane gości wiszą bez UI na zawsze) */
add_action( 'before_delete_post', function ( $post_id ) {
	if ( 'pnb_wydarzenie' !== get_post_type( $post_id ) ) {
		return;
	}
	// Zapisy gości → kasujemy z wydarzeniem (RODO: dane gości nie wiszą bez UI).
	foreach ( pnb_kalendarz_zapisy_wydarzenia( $post_id ) as $z ) {
		wp_delete_post( $z->ID, true );
	}
	// GARBAGE COLLECTION: zaimportowane zdjęcie (z Eventbrite) też kasujemy — inaczej Media Library
	// puchnie osieroconymi zdjęciami po latach. TYLKO importowane (ma _pnb_original_img_url) i TYLKO
	// gdy żadne inne wydarzenie go nie używa (dedup mógł je współdzielić). Zdjęć klienta NIE ruszamy.
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id && get_post_meta( $thumb_id, '_pnb_original_img_url', true ) ) {
		$uzywa_inny = get_posts( array(
			'post_type'      => 'pnb_wydarzenie',
			'post__not_in'   => array( $post_id ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_thumbnail_id', 'value' => $thumb_id ) ), // phpcs:ignore
		) );
		if ( empty( $uzywa_inny ) ) {
			wp_delete_attachment( $thumb_id, true );
		}
	}
} );

function pnb_kalendarz_metabox_zapisy( $post ) {
	$zapisy = pnb_kalendarz_zapisy_wydarzenia( $post->ID );
	if ( ! $zapisy ) {
		echo '<p>' . esc_html__( 'No one has signed up yet.', 'pnb-toolkit' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'pnb-toolkit' ) . '</th><th>Email</th><th>' . esc_html__( 'Phone', 'pnb-toolkit' ) . '</th><th>' . esc_html__( 'When', 'pnb-toolkit' ) . '</th></tr></thead><tbody>';
	foreach ( $zapisy as $z ) {
		echo '<tr><td>' . esc_html( $z->post_title ) . '</td>'
			. '<td>' . esc_html( get_post_meta( $z->ID, '_pnb_zapis_email', true ) ) . '</td>'
			. '<td>' . esc_html( get_post_meta( $z->ID, '_pnb_zapis_tel', true ) ) . '</td>'
			. '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $z ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$csv = wp_nonce_url( admin_url( 'admin-post.php?action=pnb_export_csv&event=' . (int) $post->ID ), 'pnb_export_csv_' . (int) $post->ID );
	echo '<p><a class="button" href="' . esc_url( $csv ) . '">' . esc_html__( 'Export sign-ups to CSV', 'pnb-toolkit' ) . '</a> '
		. '<span class="description">' . esc_html( sprintf( _n( '%d sign-up', '%d sign-ups', count( $zapisy ), 'pnb-toolkit' ), count( $zapisy ) ) ) . '</span></p>';
}

/* kolumna „Zapisy" na liście wydarzeń */
add_filter( 'manage_pnb_wydarzenie_posts_columns', function ( $kolumny ) {
	$kolumny['pnb_zapisy'] = __( 'Sign-ups', 'pnb-toolkit' );
	$kolumny['pnb_data']   = __( 'Event date', 'pnb-toolkit' );
	return $kolumny;
} );
add_action( 'manage_pnb_wydarzenie_posts_custom_column', function ( $kolumna, $post_id ) {
	if ( 'pnb_zapisy' === $kolumna ) {
		$ile   = pnb_kalendarz_ile_zapisow( $post_id );
		$limit = (int) get_post_meta( $post_id, '_pnb_event_limit', true );
		echo esc_html( $limit ? $ile . ' / ' . $limit : (string) $ile );
	}
	if ( 'pnb_data' === $kolumna ) {
		$data = get_post_meta( $post_id, '_pnb_event_date', true );
		if ( ! $data ) {
			// bez daty wydarzenie nigdy nie pokaże się na froncie — powiedz to, nie chowaj
			echo '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( '⚠ NO DATE', 'pnb-toolkit' ) . '</span>';
		} else {
			echo esc_html( $data . ' ' . get_post_meta( $post_id, '_pnb_event_time', true ) );
		}
	}
}, 10, 2 );

/* ============================================================================
 * PANEL WBUDOWANY na stronie edycji „Events" (edit_form_after_title).
 * Klient prosił: zarządzać wydarzeniami OD RAZU na stronie Events. Pełnego edytora CPT
 * (osobny ekran listy WP) nie da się bezpiecznie wcisnąć w stronę — dajemy PRZYDATNY mini-panel:
 * lista wydarzeń (tytuł + data + link „Edytuj") + duży przycisk „+ Dodaj wydarzenie".
 * Dodawanie/edycja i tak dzieje się na natywnym ekranie CPT (pełne meta boxy) — tu jest brama.
 * ========================================================================== */
function pnb_wydarzenia_panel_wbudowany() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$dzis = current_time( 'Y-m-d' );
	// Nadchodzące najpierw (rosnąco po dacie), potem wszystko inne. Bez daty = na końcu (i tak nie pokaże się na froncie).
	$q = new WP_Query( array(
		'post_type'      => 'pnb_wydarzenie',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_key'       => '_pnb_event_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
	$dodaj_url = admin_url( 'post-new.php?post_type=pnb_wydarzenie' );
	$lista_url = admin_url( 'edit.php?post_type=pnb_wydarzenie' );
	?>
	<div class="pnb-embed-panel" style="background:#fff;border:1px solid #dcdcde;border-left:5px solid #2271b1;padding:18px 22px 22px;margin:14px 0 4px;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
		<p style="margin:0 0 4px;font-size:16px;font-weight:700;color:#1d2327;">
			<span style="font-size:20px;vertical-align:-2px;">📅</span>
			<?php esc_html_e( 'You manage events here', 'pnb-toolkit' ); ?>
		</p>
		<p style="margin:0 0 14px;max-width:680px;color:#50575e;">
			<?php esc_html_e( 'Click "+ Add event" to create a new one (date, time, place, description). Edit existing events with the "Edit" link next to them. Changes appear on the Events page right away.', 'pnb-toolkit' ); ?>
		</p>

		<p style="margin:0 0 16px;">
			<a href="<?php echo esc_url( $dodaj_url ); ?>" class="button button-primary button-hero" style="text-decoration:none;">
				<?php esc_html_e( '+ Add event', 'pnb-toolkit' ); ?>
			</a>
		</p>

		<?php if ( $q->have_posts() ) : ?>
			<table class="widefat striped" style="max-width:760px;">
				<thead>
					<tr>
						<th style="width:44%;"><?php esc_html_e( 'Event', 'pnb-toolkit' ); ?></th>
						<th style="width:30%;"><?php esc_html_e( 'Date and time', 'pnb-toolkit' ); ?></th>
						<th style="width:26%;"></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $q->have_posts() ) : $q->the_post();
						$id      = get_the_ID();
						$data    = get_post_meta( $id, '_pnb_event_date', true );
						$godzina = get_post_meta( $id, '_pnb_event_time', true );
						$edit    = get_edit_post_link( $id ); // WP-owy, z nonce
						$przyszle = ( $data && $data >= $dzis );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( get_the_title() ? get_the_title() : __( '(no title)', 'pnb-toolkit' ) ); ?></strong>
								<?php if ( $przyszle ) : ?>
									<span style="display:inline-block;margin-left:6px;font-size:11px;font-weight:600;color:#008a20;background:#edfaef;border-radius:3px;padding:1px 6px;vertical-align:1px;"><?php esc_html_e( 'upcoming', 'pnb-toolkit' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $data ) : ?>
									<?php echo esc_html( trim( $data . ' ' . $godzina ) ); ?>
								<?php else : ?>
									<span style="color:#b32d2e;font-weight:600;"><?php esc_html_e( '⚠ no date', 'pnb-toolkit' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $edit ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'pnb-toolkit' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
			<p style="margin:12px 0 0;">
				<a href="<?php echo esc_url( $lista_url ); ?>"><?php esc_html_e( 'Open the full events list (sign-ups, CSV export) ↗', 'pnb-toolkit' ); ?></a>
			</p>
		<?php else : ?>
			<p style="margin:0;padding:14px 16px;background:#f6f7f7;border-radius:6px;color:#50575e;max-width:760px;">
				<?php esc_html_e( 'You have no events yet. Click "+ Add event" above to create your first one.', 'pnb-toolkit' ); ?>
			</p>
		<?php endif; ?>
	</div>
	<?php
	wp_reset_postdata();
}

/* ==== EKSPORT CSV (nonce + capability) ==== */

add_action( 'admin_post_pnb_export_csv', function () {
	$event = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;
	if ( ! $event || ! current_user_can( 'edit_post', $event ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'pnb-toolkit' ) );
	}
	check_admin_referer( 'pnb_export_csv_' . $event );
	$zapisy = pnb_kalendarz_zapisy_wydarzenia( $event );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=zapisy-' . $event . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'imie', 'email', 'telefon', 'data_zapisu' ) );
	foreach ( $zapisy as $z ) {
		fputcsv( $out, array_map( 'pnb_kalendarz_csv_bezpieczne', array(
			$z->post_title,
			get_post_meta( $z->ID, '_pnb_zapis_email', true ),
			get_post_meta( $z->ID, '_pnb_zapis_tel', true ),
			get_the_date( 'Y-m-d H:i', $z ),
		) ) );
	}
	fclose( $out ); // phpcs:ignore
	exit;
} );

/* Excel wykonuje komórki zaczynające się od =,+,-,@ (formula injection) — prefiksujemy apostrofem. */
function pnb_kalendarz_csv_bezpieczne( $wartosc ) {
	$wartosc = (string) $wartosc;
	return preg_match( '/^[=+\-@\t\r]/', $wartosc ) ? "'" . $wartosc : $wartosc;
}

/* ==== FRONT: auto-wstawienie + shortcode + no-cache (mina LiteSpeed) ==== */

add_shortcode( 'pnb_wydarzenia', 'pnb_kalendarz_render' );

add_filter( 'the_content', function ( $tresc ) {
	static $zrobione = false;
	$strona = (int) get_option( 'pnb_wydarzenia_strona', 0 );
	if ( $zrobione || ! $strona || ! is_page( $strona ) || ! in_the_loop() || ! is_main_query() ) {
		return $tresc;
	}
	if ( has_shortcode( $tresc, 'pnb_wydarzenia' ) ) {
		return $tresc;
	}
	// NOWE: jeśli strona ma blok Gutenberg pnb/wydarzenia → to ON renderuje galerię. Nie doklejamy drugi raz.
	$post_biezacy = get_post();
	if ( $post_biezacy && has_block( 'pnb/wydarzenia', $post_biezacy ) ) {
		return $tresc;
	}
	$zrobione = true;
	return $tresc . pnb_kalendarz_render();
} );

/* cache nie może przetrzymać strony z formularzem (nonce żyje krócej niż cache = 403) —
   dotyczy strony auto-wstawienia, SINGLI wydarzeń ORAZ każdej strony z ręcznie wklejonym shortcode */
add_action( 'template_redirect', function () {
	$strona   = (int) get_option( 'pnb_wydarzenia_strona', 0 );
	$formularz = ( $strona && is_page( $strona ) ) || is_singular( 'pnb_wydarzenie' );
	if ( ! $formularz && is_singular() ) {
		$p = get_post();
		// shortcode ORAZ blok (audyt: strona z blokiem zanim auto-detect zapisze opcję była
		// cache'owalna → LiteSpeed zamrażał nonce → każdy zapis gościa kończył się „expired")
		$formularz = $p && ( has_shortcode( $p->post_content, 'pnb_wydarzenia' ) || has_block( 'pnb/wydarzenia', $p ) );
	}
	if ( $formularz ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}
} );

/* Etykieta grupy dat jak Luma: „Today · Friday" / „Tomorrow · Saturday" / „Jul 8 · Wednesday" (EN). */
function pnb_kalendarz_etykieta_dnia( $data, $dzis ) {
	$ts = strtotime( $data );
	if ( ! $ts ) {
		return array( (string) $data, '' );
	}
	$roznica = (int) round( ( $ts - strtotime( $dzis ) ) / DAY_IN_SECONDS );
	if ( 0 === $roznica ) {
		$glowna = __( 'Today', 'pnb-toolkit' );
	} elseif ( 1 === $roznica ) {
		$glowna = __( 'Tomorrow', 'pnb-toolkit' );
	} else {
		$glowna = date_i18n( 'M j', $ts );
	}
	$dow = date_i18n( 'l', $ts );
	return array( $glowna, $dow );
}

/* „Add to Google Calendar" — czysty URL template (bez API). Godzina wpisana przez klienta jest
   w LOKALNEJ strefie strony (np. Europe/Warsaw) → zamieniamy na UTC przez get_gmt_from_date()
   ZANIM sklejamy z sufiksem Z. Wcześniej strtotime parsował lokalny czas jako UTC → GCal +2h. */
function pnb_kalendarz_gcal_url( $id, $data, $godzina, $koniec, $miejsce ) {
	// pomocnik: "2026-07-08 17:30" (czas lokalny strony) → timestamp UTC
	$do_utc = function ( $lokalny ) {
		$gmt = get_gmt_from_date( $lokalny, 'Y-m-d H:i:s' ); // uwzględnia strefę WP
		return strtotime( $gmt . ' UTC' );
	};
	$start = $do_utc( trim( $data . ' ' . $godzina ) );
	if ( ! $start ) {
		return '';
	}
	if ( $godzina ) {
		$stop = $koniec ? $do_utc( trim( $data . ' ' . $koniec ) ) : 0;
		if ( ! $stop || $stop <= $start ) {
			$stop = $start + HOUR_IN_SECONDS;
		}
		$dates = gmdate( 'Ymd\THis\Z', $start ) . '/' . gmdate( 'Ymd\THis\Z', $stop );
	} else {
		$dates = gmdate( 'Ymd', $start ) . '/' . gmdate( 'Ymd', $start + DAY_IN_SECONDS );
	}
	$opis = mb_substr( trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) ), 0, 200 );
	return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
		. '&text=' . rawurlencode( get_the_title( $id ) )
		. '&dates=' . rawurlencode( $dates )
		. '&details=' . rawurlencode( $opis )
		. '&location=' . rawurlencode( $miejsce );
}

/* Badge stanu miejsc: pełne → „Fully booked"; zostało ≤3 → „Only X left"; zajętość ≥75% → „Near capacity". */
function pnb_kalendarz_badge( $limit, $zajete ) {
	if ( $limit <= 0 ) {
		return null;
	}
	$zostalo = $limit - $zajete;
	if ( $zostalo <= 0 ) {
		return array( 'full', __( 'Fully booked', 'pnb-toolkit' ) );
	}
	if ( $zostalo <= 3 ) {
		/* translators: %d = liczba wolnych miejsc */
		return array( 'left', sprintf( _n( '🔥 Only %d spot left', '🔥 Only %d spots left', $zostalo, 'pnb-toolkit' ), $zostalo ) );
	}
	if ( $zajete / $limit >= 0.75 ) {
		return array( 'near', __( 'Near capacity', 'pnb-toolkit' ) );
	}
	return null;
}

/* Ikony inline SVG (stroke 1.6, currentColor) — spójny język graficzny zamiast emoji (werdykt P3).
   Proste path-y, zero bibliotek; rozmiar dziedziczy z CSS (.pnb-ev-ico). */
function pnb_kalendarz_ikona( $nazwa ) {
	$paths = array(
		'dom'     => '<path d="M3.5 11 12 4l8.5 7"/><path d="M5.8 9.3V20h12.4V9.3"/>',
		'czapka'  => '<path d="M12 4.5 2.5 8.8 12 13l9.5-4.2z"/><path d="M6.2 10.9v4.3c0 1.3 2.6 2.5 5.8 2.5s5.8-1.2 5.8-2.5v-4.3"/><path d="M21.5 9.2v4.4"/>',
		'drzwi'   => '<path d="M5.5 20.5V5.3c0-1 .8-1.8 1.8-1.8h9.4c1 0 1.8.8 1.8 1.8v15.2"/><path d="M3 20.5h18"/><path d="M14.4 12h.01"/>',
		'lapka'   => '<circle cx="6.8" cy="9.4" r="1.6"/><circle cx="12" cy="7.6" r="1.6"/><circle cx="17.2" cy="9.4" r="1.6"/><path d="M12 11.6c-2.7 0-5 2.1-5 4.3 0 1.5 1.3 2.7 2.6 2.3.9-.3 1.5-.5 2.4-.5s1.5.2 2.4.5c1.3.4 2.6-.8 2.6-2.3 0-2.2-2.3-4.3-5-4.3z"/>',
		'pinezka' => '<path d="M12 21c-3.2-3.3-6.5-7-6.5-10.7a6.5 6.5 0 0 1 13 0C18.5 14 15.2 17.7 12 21z"/><circle cx="12" cy="10" r="2.3"/>',
		'plus'    => '<path d="M12 5.5v13"/><path d="M5.5 12h13"/>',
		'lupa'    => '<circle cx="10.5" cy="10.5" r="6"/><path d="M20 20l-4.7-4.7"/>',
	);
	if ( ! isset( $paths[ $nazwa ] ) ) {
		return '';
	}
	return '<svg class="pnb-ev-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $nazwa ] . '</svg>';
}

/* Assety rodziny efektów — wzorzec enqueue z galeria.php (handle pnb-gsap, nie 'gsap': inna wtyczka
   mogłaby nadpisać starszą wersją). JS front zależny od pnb-gsap-scrolltrigger. Wspólne: lista + singiel. */
function pnb_kalendarz_zaladuj_assety() {
	wp_enqueue_style( 'pnb-fonty', PNB_TOOLKIT_URL . 'assets/fonts/fonts.css', array(), PNB_TOOLKIT_VERSION ); // fonty LOKALNIE (OFL), offline
	wp_enqueue_style( 'pnb-kalendarz', PNB_TOOLKIT_URL . 'assets/css/kalendarz.css', array(), PNB_TOOLKIT_VERSION );
	// GSAP+ScrollTrigger: JEDNA kopia (jak galeria) — jeśli motyw już je ma, wpinamy się pod nie, inaczej
	// ładujemy własne offline (licencja GSAP no-charge — nota w pliku, CREDITS.md).
	$gsap_dep = function_exists( 'pnb_galeria_zapewnij_gsap' ) ? pnb_galeria_zapewnij_gsap() : array();
	if ( ! $gsap_dep ) {
		wp_enqueue_script( 'pnb-gsap', PNB_TOOLKIT_URL . 'assets/lib/gsap.min.js', array(), '3.12.5', true );
		wp_enqueue_script( 'pnb-gsap-scrolltrigger', PNB_TOOLKIT_URL . 'assets/lib/ScrollTrigger.min.js', array( 'pnb-gsap' ), '3.12.5', true );
		$gsap_dep = array( 'pnb-gsap-scrolltrigger' );
	}
	if ( function_exists( 'pnb_zapewnij_lenis' ) ) {
		pnb_zapewnij_lenis( $gsap_dep ); // płynny scroll dla podstrony wydarzeń (gdy motyw swojego nie ma)
	}
	wp_enqueue_script( 'pnb-kalendarz-front', PNB_TOOLKIT_URL . 'assets/js/kalendarz-front.js', $gsap_dep, PNB_TOOLKIT_VERSION, true );
}

/* Preload fontu tytułów (Varela Round) — priorytetowe ładowanie PRZED renderem, żeby wszystkie
 * tytuły dostały ten sam font (bez migotania fallbackiem przy scroll-reveal). Oba zakresy (latin+ext).
 * MUSI być na wp_head (przed <body>), NIE wewnątrz renderu bloku (ten leci w the_content = za późno). */
add_action( 'wp_head', 'pnb_kalendarz_preload_fontow', 1 );
function pnb_kalendarz_preload_fontow() {
	// Tylko tam gdzie kalendarz się renderuje (strona z blokiem wydarzeń albo single wydarzenia) —
	// nie ładujemy fontu priorytetowo na każdej podstronie sklepu klienta.
	$strona_ev = (int) get_option( 'pnb_events_page_id', 0 );
	$na_wydarzeniach = is_singular( 'pnb_wydarzenie' )
		|| ( $strona_ev && is_page( $strona_ev ) )
		|| ( is_a( get_post(), 'WP_Post' ) && has_block( 'pnb/wydarzenia', get_post() ) );
	// Fallback gdy nie umiemy wykryć: włącz i tak (preload jednego fontu jest tani).
	if ( ! $na_wydarzeniach && ! is_page() && ! is_singular( 'pnb_wydarzenie' ) ) {
		return;
	}
	$base = PNB_TOOLKIT_URL . 'assets/fonts/';
	foreach ( array( 'varela-round-latin.woff2', 'varela-round-latin-ext.woff2' ) as $f ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $base . $f )
		);
	}
}

/* Formularz zapisu — JEDEN render dla listy i singla (pola/nonce/honeypot 1:1, mechanika handlera nietknięta). */
function pnb_kalendarz_formularz_html( $event_id ) {
	$event_id = (int) $event_id;

	// Wydarzenie ZAIMPORTOWANE (z zewnętrznego źródła) — to nie NASZE wydarzenie, więc zamiast
	// formularza zapisu (który wysłałby mail właścicielowi o cudzym evencie) pokazujemy przycisk
	// kierujący do oryginału (tam gość kupi bilet / zapisze się u organizatora).
	$source_url = get_post_meta( $event_id, '_pnb_source_url', true );
	if ( $source_url ) {
		return '<div class="pnb-ev-extlink"><a class="pnb-ev-extbtn" href="' . esc_url( $source_url )
			. '" target="_blank" rel="noopener nofollow">'
			. esc_html__( 'View event & tickets', 'pnb-toolkit' )
			. ' <span aria-hidden="true">↗</span></a></div>';
	}

	$out  = '<form class="pnb-ev-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= '<input type="hidden" name="action" value="pnb_zapis">';
	$out .= '<input type="hidden" name="pnb_event" value="' . $event_id . '">';
	$out .= wp_nonce_field( 'pnb_zapis_' . $event_id, 'pnb_nonce', true, false );
	$out .= '<input type="text" name="pnb_hp" value="" class="pnb-hp" tabindex="-1" autocomplete="off" aria-hidden="true">'; // honeypot
	$out .= '<input type="text" name="pnb_imie" placeholder="' . esc_attr__( 'Your name', 'pnb-toolkit' ) . '" required>';
	$out .= '<input type="email" name="pnb_email" placeholder="' . esc_attr__( 'E-mail', 'pnb-toolkit' ) . '" required>';
	$out .= '<input type="tel" name="pnb_tel" placeholder="' . esc_attr__( 'Phone (optional)', 'pnb-toolkit' ) . '">';
	$out .= '<button type="submit">' . esc_html__( 'Sign up', 'pnb-toolkit' ) . '</button>';
	$out .= '</form>';
	return $out;
}

/* Komunikaty po powrocie z handlera (?pnb_zapis=…) — jeden słownik dla listy i singla.
 * $id > 0  → render TYLKO gdy pnb_event w URL wskazuje to wydarzenie (komunikat przy właściwej karcie,
 *            nie przy wszystkich naraz — zgłoszenie Dzidka 2026-07-07).
 * $id == 0 → slot ogólny (góra listy): renderuje WYŁĄCZNIE gdy w URL brak pnb_event (awaryjne błędy
 *            walidacji bez ID) — w normalnym przepływie nic nie pokazuje. */
function pnb_kalendarz_komunikaty_html( $id = 0 ) {
	$status = isset( $_GET['pnb_zapis'] ) ? sanitize_key( $_GET['pnb_zapis'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$ev     = isset( $_GET['pnb_event'] ) ? (int) $_GET['pnb_event'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ( $id > 0 && $ev > 0 && $ev !== $id ) || ( 0 === $id && $ev > 0 ) ) {
		return ''; // przy niedopasowanym ID cicho; błąd bez ID ($ev=0) zostaje widoczny (single/slot ogólny)
	}
	$mapa   = array(
		'ok'      => array( 'pnb-ev-ok', __( 'Thank you! You are on the list — see you there. 🐾', 'pnb-toolkit' ) ),
		'full'    => array( 'pnb-ev-err', __( 'Sorry — this event is fully booked.', 'pnb-toolkit' ) ),
		'dupl'    => array( 'pnb-ev-ok', __( 'You are already on the list for this event — no need to sign up twice. 🐾', 'pnb-toolkit' ) ),
		'expired' => array( 'pnb-ev-err', __( 'The form expired (page was open for a long time) — it has been refreshed, please send again.', 'pnb-toolkit' ) ),
		'blad'    => array( 'pnb-ev-err', __( 'Something went wrong — please check your name and e-mail and try again.', 'pnb-toolkit' ) ),
	);
	if ( ! isset( $mapa[ $status ] ) ) {
		return '';
	}
	return '<div class="pnb-ev-msgs"><div class="pnb-ev-msg ' . esc_attr( $mapa[ $status ][0] ) . '">' . esc_html( $mapa[ $status ][1] ) . '</div></div>';
}

/* FRONT premium (gigant-prompt EVENTS): hero-welon + chips filtrów + OŚ CZASU (wzór Luma) + mapa marki.
   Mechanika zapisów (handler/nonce/limit/dedupe/CSV) — NIETKNIĘTA; formularz przeniesiony 1:1. */
function pnb_kalendarz_render() {
	pnb_kalendarz_zaladuj_assety();
	$strona_ev = (int) get_option( 'pnb_wydarzenia_strona', 0 );
	if ( $strona_ev && is_page( $strona_ev ) ) {
		// strona wydarzeń = pełna scena: chowamy hero/tytuł motywu i gorset 70ch (jak galeria)
		wp_add_inline_style( 'pnb-kalendarz',
			'.page-id-' . $strona_ev . ' .page-hero{display:none}'
			. '.page-id-' . $strona_ev . ' .location{padding:0;background:transparent}'
			. '.page-id-' . $strona_ev . ' .location .wrap{max-width:none;padding:0}'
			. '.page-id-' . $strona_ev . ' .page-content{max-width:none !important;margin:0 !important}'
			. '.page-id-' . $strona_ev . ' .entry-title{display:none}' );
	}

	$dzis = current_time( 'Y-m-d' );
	$q    = new WP_Query( array(
		'post_type'              => 'pnb_wydarzenie',
		// Pokazujemy WSZYSTKIE nadchodzące (nie sztywne 20 — klient nie wiedział ile ich będzie ze
		// źródła). Porządek trzymają: filtry (All/tydzień/miesiąc/kategoria), grupowanie po dacie
		// i wyszukiwarka — więc lista nie jest „scroll w nieskończoność". Minione i tak odpadają
		// (meta_query >= dziś). Górny bezpiecznik 200 chroni przed patologią (źródło z setkami wydarzeń
		// = setki zdjęć na raz zabiłyby LCP); realny pensjonat ma kilka-kilkadziesiąt.
		'posts_per_page'         => 200,
		'meta_key'               => '_pnb_event_date',
		'orderby'                => 'meta_value',
		'order'                  => 'ASC',
		'no_found_rows'          => true,  // WYDAJNOŚĆ: brak paginacji → nie licz SQL_CALC_FOUND_ROWS
		'update_post_term_cache' => false, // CPT bez taksonomii (kategoria w post_meta) → nie ciągnij terminów
		'meta_query'             => array( array(
			'key'     => '_pnb_event_date',
			'value'   => $dzis,
			'compare' => '>=',
			'type'    => 'DATE',
		) ),
	) );

	// jeden przebieg po danych (bez the_post — czytamy po ID), z niego chips-liczniki i grupy dat
	$kategorie = pnb_kalendarz_kategorie();
	$items     = array();
	foreach ( $q->posts as $p ) {
		$kat = get_post_meta( $p->ID, '_pnb_event_cat', true );
		if ( ! isset( $kategorie[ $kat ] ) ) {
			$kat = 'other';
		}
		$items[] = array(
			'id'      => (int) $p->ID,
			'tytul'   => (string) get_the_title( $p->ID ),
			'data'    => (string) get_post_meta( $p->ID, '_pnb_event_date', true ),
			'godzina' => (string) get_post_meta( $p->ID, '_pnb_event_time', true ),
			'koniec'  => (string) get_post_meta( $p->ID, '_pnb_event_time_end', true ),
			'miejsce' => (string) get_post_meta( $p->ID, '_pnb_event_place', true ),
			'limit'   => (int) get_post_meta( $p->ID, '_pnb_event_limit', true ),
			'kat'     => $kat,
		);
	}

	// liczniki chipów: tydzień = do końca niedzieli (ta sama reguła co w JS), miesiąc = bieżący
	$dow   = (int) gmdate( 'w', strtotime( $dzis ) );
	$eow   = gmdate( 'Y-m-d', strtotime( $dzis ) + ( ( 0 === $dow ? 0 : 7 - $dow ) * DAY_IN_SECONDS ) );
	$mies  = substr( $dzis, 0, 7 );
	$n_all = count( $items );
	$n_tydzien = 0;
	$n_miesiac = 0;
	$n_kat     = array_fill_keys( array_keys( $kategorie ), 0 );
	foreach ( $items as $it ) {
		if ( $it['data'] && $it['data'] <= $eow ) {
			$n_tydzien++;
		}
		if ( substr( $it['data'], 0, 7 ) === $mies ) {
			$n_miesiac++;
		}
		$n_kat[ $it['kat'] ]++;
	}

	// hero: obraz w tle. KOLEJNOŚĆ (naprawa 2026-07-05 — usunięto hardkod ID 82 = dane demo klienta):
	//   1) stała/filtr jeśli klient/dev jawnie ustawił (PNB_EVENTS_HERO_ID / filtr pnb_kalendarz_hero_id),
	//   2) OPCJA z panelu (Events → Settings → „Events page header image") — klient wybiera STAŁE tło,
	//      żeby hero nie zmieniał się od (scrapowanych) wydarzeń,
	//   3) featured 1. (najbliższego) wydarzenia — naturalny fallback z TREŚCI klienta,
	//   4) brak → sam welon marki (bez zdjęcia). Żadnego zaszytego ID demo.
	// Trzymamy ID (nie URL) → renderujemy przez wp_get_attachment_image() = srcset+sizes automatycznie
	// (WYDAJNOŚĆ: bez tego hero ciągnął 'full' = oryginał klienta, kilka MB, priorytetowo — zabójstwo LCP).
	$hero_id = defined( 'PNB_EVENTS_HERO_ID' ) ? (int) PNB_EVENTS_HERO_ID : 0;
	$hero_id = (int) apply_filters( 'pnb_kalendarz_hero_id', $hero_id );
	if ( ! $hero_id ) {
		$hero_id = (int) get_option( 'pnb_events_hero_id', 0 ); // stałe tło z panelu (wybór klienta)
	}
	// ⚠️ NIE bierzemy featured 1. wydarzenia jako fallback (naprawa 2026-07-09): plakaty scrapowanych
	// wydarzeń (Eventbrite) mają WŁASNY tekst (np. „YOGA CATS") który nachodzi na napis hero „Save the
	// date" → nieczytelne. Brak ustawionego tła → sam welon marki (zawsze ładny, napis czytelny).
	// Klient który CHCE zdjęcie w hero ustawia je świadomie (edytor bloku / Events→Settings).

	/* ── HERO: full-bleed zdjęcie + ciepły welon + „Save the date." splitwords (CSS-only) ── */
	$out  = '<section class="pnb-events" id="pnbEvents">';
	$out .= '<header class="pnb-evh">';
	if ( $hero_id ) {
		// rozmiar 'large' jako baza + srcset/sizes: full-bleed 100vw → przeglądarka wybierze wariant do ekranu.
		$out .= '<div class="pnb-evh-img" aria-hidden="true">'
			. wp_get_attachment_image( $hero_id, 'large', false, array(
				'alt'           => '',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'sizes'         => '100vw',
			) )
			. '</div>';
	}
	$out .= '<div class="pnb-evh-veil" aria-hidden="true"></div>';
	$out .= '<div class="pnb-evh-grain" aria-hidden="true"></div>';
	$out .= '<div class="pnb-evh-in">';
	$out .= '<span class="pnb-ev-eyebrow">' . esc_html( pnb_txt( 'events.hero.eyebrow', "Cats'N'Board · what's on" ) ) . '</span>';
	// Nagłówek budowany z JEDNEGO tłumaczonego stringu (nie 3 osobne słowa — inaczej PL to bełkot słowo-w-słowo).
	// Ostatnie słowo = akcent koralowy + kropka; wzór jak w galerii. „Save the date." / „Zarezerwuj termin."
	// Tekst edytowalny w panelu Teksty (zakładka Wydarzenia); pnb_kalendarz_hero_slowa robi akcent na ostatnim słowie.
	$out .= '<h1 class="pnb-ev-h1">' . pnb_kalendarz_hero_slowa( pnb_txt( 'events.hero.title', 'Save the date.' ) ) . '</h1>';
	$out .= '<p class="pnb-ev-lead">' . esc_html( pnb_txt( 'events.hero.lead1', 'Adoption days, cat classes and open doors at the pension.' ) ) . '<br>'
		. esc_html( pnb_txt( 'events.hero.lead2', 'Sign up below — spots are limited.' ) ) . '</p>';
	$out .= '</div>';
	$out .= '<div class="pnb-evh-cue pnb-ev-mono" aria-hidden="true"><span>→</span> ' . esc_html__( 'scroll', 'pnb-toolkit' ) . '</div>'; // → w vertical-rl wskazuje w dół
	$out .= '</header>';

	/* slot awaryjny (tylko błędy bez ID wydarzenia) — normalny komunikat renderuje się przy karcie */
	$out .= pnb_kalendarz_komunikaty_html();

	if ( ! $items ) {
		$out .= '<p class="pnb-ev-empty">' . esc_html__( 'No upcoming events right now — check back soon!', 'pnb-toolkit' ) . '</p>';
	}

	/* ── PASEK CHIPÓW (sticky pod nav): All / This week / This month / kategorie z kropkami ── */
	if ( $items ) {
		// licznik 0 → chip wygaszony (disabled + podpowiedź); OŻYWA sam gdy licznik >0, bo warunek liczy się tu przy renderze
		$chip = function ( $filtr, $etykieta, $ile, $aktywny = false, $kat = '', $ico = '', $tytul_zero = '' ) {
			$dot  = $kat ? '<span class="pnb-ev-dot" data-c="' . esc_attr( $kat ) . '" aria-hidden="true"></span>' : '';
			$zero = ( 0 === (int) $ile && 'all' !== $filtr );
			return '<button type="button" class="pnb-ev-chip' . ( $aktywny ? ' is-active' : '' ) . '" data-filter="' . esc_attr( $filtr ) . '" aria-pressed="' . ( $aktywny ? 'true' : 'false' ) . '"'
				. ( $zero ? ' disabled aria-disabled="true"' . ( $tytul_zero ? ' title="' . esc_attr( $tytul_zero ) . '"' : '' ) : '' ) . '>'
				. $dot . $ico . '<span>' . esc_html( $etykieta ) . '</span><span class="pnb-ev-chip-n">' . (int) $ile . '</span></button>';
		};
		$ikony = array( 'adoption' => 'dom', 'class' => 'czapka', 'openday' => 'drzwi', 'other' => 'lapka' );
		$out  .= '<nav class="pnb-ev-chips" aria-label="' . esc_attr__( 'Event filters', 'pnb-toolkit' ) . '"><div class="pnb-ev-chips-in">';
		$out  .= $chip( 'all', pnb_txt( 'events.filter.all', 'All' ), $n_all, true );
		$out  .= $chip( 'week', pnb_txt( 'events.filter.week', 'This week' ), $n_tydzien, false, '', '', __( 'No events this week', 'pnb-toolkit' ) );
		$out  .= $chip( 'month', pnb_txt( 'events.filter.month', 'This month' ), $n_miesiac, false, '', '', __( 'No events this month', 'pnb-toolkit' ) );
		foreach ( $kategorie as $klucz => $nazwa ) {
			if ( $n_kat[ $klucz ] > 0 ) {
				$out .= $chip( 'cat-' . $klucz, $nazwa, $n_kat[ $klucz ], false, $klucz, pnb_kalendarz_ikona( $ikony[ $klucz ] ) );
			}
		}
		// Wyszukiwarka na KOŃCU paska (po prawej, za filtrami) — w lepkim pasku, jedzie z chipami.
		$out .= '<div class="pnb-ev-search"><span class="pnb-ev-search-ico" aria-hidden="true">' . pnb_kalendarz_ikona( 'lupa' ) . '</span>'
			. '<input type="search" class="pnb-ev-search-input" placeholder="' . esc_attr__( 'Search…', 'pnb-toolkit' ) . '" aria-label="' . esc_attr__( 'Search events', 'pnb-toolkit' ) . '"></div>';
		$out .= '</div></nav>';
	}

	/* ── OŚ CZASU (serce, wzór Luma): głębia planów — plamy (-1), watermark (0), karty (1) ── */
	$out .= '<div class="pnb-ev-tl">';
	$out .= '<div class="pnb-ev-blob pnb-ev-blob-a" aria-hidden="true"></div><div class="pnb-ev-blob pnb-ev-blob-b" aria-hidden="true"></div>';
	$out .= '<div class="pnb-ev-wm" aria-hidden="true">' . esc_html__( 'Events', 'pnb-toolkit' ) . '</div>';
	$out .= '<div class="pnb-ev-tl-in"><div class="pnb-ev-line" aria-hidden="true"></div>';

	// format godziny z ustawień WP (domyślnie 12h am/pm)
	$fmt_czasu = get_option( 'time_format', 'g:i a' );
	$grupy     = array();
	foreach ( $items as $it ) {
		$grupy[ $it['data'] ][] = $it;
	}
	foreach ( $grupy as $d => $lista ) {
		list( $gd, $gw ) = pnb_kalendarz_etykieta_dnia( $d, $dzis );
		$out .= '<section class="pnb-ev-group" data-when="' . esc_attr( $d ) . '">';
		$out .= '<p class="pnb-ev-ghead"><span class="pnb-ev-gd">' . esc_html( $gd ) . '</span>' . ( $gw ? '<span class="pnb-ev-gw">· ' . esc_html( $gw ) . '</span>' : '' ) . '</p>';
		foreach ( $lista as $it ) {
			$id      = $it['id'];
			$zajete  = pnb_kalendarz_ile_zapisow( $id );
			$pelne   = $it['limit'] > 0 && $zajete >= $it['limit'];
			$otworz  = ( isset( $_GET['pnb_zapis'] ) && isset( $_GET['pnb_event'] ) && (int) $_GET['pnb_event'] === $id );

			// wrapper = kanał pod-scrubu głębi (reveal ma transform KARTY, scrub ma transform WRAPPERA — bez konfliktu)
			$out .= '<div class="pnb-ev-cardwrap">';
			// data-search = tytuł + miejsce + skrót opisu (do wyszukiwarki po tytule/opisie na żywo).
			$szukaj_tekst = trim( ( $it['tytul'] ?? get_the_title( $id ) ) . ' ' . ( $it['miejsce'] ?? '' ) . ' ' . wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ) );
			$out .= '<article class="pnb-ev-card" id="event-' . (int) $id . '" data-when="' . esc_attr( $it['data'] ) . '" data-cat="' . esc_attr( $it['kat'] ) . '" data-search="' . esc_attr( mb_substr( $szukaj_tekst, 0, 300 ) ) . '">';
			$out .= '<div class="pnb-ev-main">';
			// godzina od–do
			if ( $it['godzina'] ) {
				$g = date_i18n( $fmt_czasu, strtotime( $it['data'] . ' ' . $it['godzina'] ) );
				if ( $it['koniec'] ) {
					$g .= ' — ' . date_i18n( $fmt_czasu, strtotime( $it['data'] . ' ' . $it['koniec'] ) );
				}
				$out .= '<div class="pnb-ev-time pnb-ev-mono">' . esc_html( $g ) . '</div>';
			}
			// tytuł = link do strony wydarzenia (checklista UX: karta prowadzi do singla)
			$out .= '<h2 class="pnb-ev-title"><a class="pnb-ev-tlink pnb-ev-golink" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( pnb_event_tytul( $id ) ) . '</a></h2>';
			$miejsce_disp = pnb_event_miejsce( $id, $it['miejsce'] );
			if ( $miejsce_disp ) {
				$out .= '<div class="pnb-ev-place">' . pnb_kalendarz_ikona( 'pinezka' ) . esc_html( $miejsce_disp ) . '</div>';
			}
			$out .= '<span class="pnb-ev-tag" data-c="' . esc_attr( $it['kat'] ) . '"><span class="pnb-ev-dot" data-c="' . esc_attr( $it['kat'] ) . '" aria-hidden="true"></span>' . esc_html( $kategorie[ $it['kat'] ] ) . '</span>';
			// OPIS NA LIŚCIE = SKRÓT (importowane wydarzenia mają długie opisy które rozpychały kartę;
			// pełny opis + linki są na stronie pojedynczego wydarzenia po kliknięciu). Krótkie opisy
			// (demo, ręczne) mieszczą się w całości — skrót ich nie tnie.
			$tresc = pnb_event_opis( $id );
			if ( trim( $tresc ) ) {
				$plain = trim( wp_strip_all_tags( $tresc ) );
				$skrot = mb_substr( $plain, 0, 180 );
				if ( mb_strlen( $plain ) > 180 ) {
					// utnij na ostatniej spacji żeby nie ciąć w połowie słowa
					$skrot = mb_substr( $skrot, 0, (int) mb_strrpos( $skrot, ' ' ) ) . '…';
				}
				$out .= '<div class="pnb-ev-desc"><p>' . esc_html( $skrot ) . '</p></div>';
			}
			// badge stanu + social proof: „X going" tylko gdy ≥2 i NIE przy pełnym; pełne → „Sold out" (uczciwy stan, brak listy oczekujących)
			$stan  = '';
			$badge = pnb_kalendarz_badge( $it['limit'], $zajete );
			if ( $badge ) {
				$stan .= '<span class="pnb-ev-badge pnb-ev-badge--' . esc_attr( $badge[0] ) . '">' . esc_html( $badge[1] ) . '</span>';
			}
			if ( $it['limit'] > 0 && ! $pelne ) {
				/* translators: 1: liczba wolnych miejsc, 2: limit miejsc */
				$stan .= '<span class="pnb-ev-spots pnb-ev-mono">' . esc_html( sprintf( __( '%1$d of %2$d spots left', 'pnb-toolkit' ), $it['limit'] - $zajete, $it['limit'] ) ) . '</span>';
			}
			if ( $pelne ) {
				$stan .= '<span class="pnb-ev-going pnb-ev-mono">' . esc_html__( 'Sold out', 'pnb-toolkit' ) . '</span>';
			} elseif ( $zajete >= 2 ) {
				/* translators: %d = liczba zapisanych gości */
				$stan .= '<span class="pnb-ev-going pnb-ev-mono">' . esc_html( sprintf( _n( '%d going', '%d going', $zajete, 'pnb-toolkit' ), $zajete ) ) . '</span>';
			}
			if ( $stan ) {
				$out .= '<div class="pnb-ev-state">' . $stan . '</div>';
			}
			// ══ CTA PRZEBUDOWANE (v2.12.0) — decyzja klienta: FORMULARZ ZAPISU POZA KARTĄ ═══════════
			// STARY BUG: formularz „Sign up" był <details> WPLĄTANYM MIĘDZY guziki w .pnb-ev-cta (flex-wrap)
			// → po otwarciu rozpychał rząd, guziki skakały w słupek (zmierzone: 4 różne Y).
			// NAPRAWA U ŹRÓDŁA: w karcie zostają TYLKO guziki akcji (GCal + Event details + Edit) w rzędzie.
			// Guzik „Sign up" + formularz wyjechały PONIŻEJ karty (rodzeństwo .pnb-ev-card w .pnb-ev-cardwrap)
			// — patrz blok „ZAPIS POZA KARTĄ" po </article>. Formularz nie ma czego rozpychać, bo nie jest
			// w karcie. Logika zapisu (form/action/nonce/pola) NIETKNIĘTA.
			$det_id  = 'pnb-detbox-' . (int) $id;
			$dojazd  = $it['miejsce'] ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $it['miejsce'] ) : '';
			$out .= '<div class="pnb-ev-cta">';
			// ── RZĄD GUZIKÓW W KARCIE: zawsze poziomo (flex-wrap — zawija równymi parami gdy ciasno) ──
			$out .= '<div class="pnb-ev-actrow">';
			$gcal = pnb_kalendarz_gcal_url( $id, $it['data'], $it['godzina'], $it['koniec'], $it['miejsce'] );
			if ( $gcal ) {
				$out .= '<a class="pnb-ev-gcal" href="' . esc_url( $gcal ) . '" target="_blank" rel="noopener noreferrer">' . pnb_kalendarz_ikona( 'plus' ) . ' ' . esc_html__( 'Add to Google Calendar', 'pnb-toolkit' ) . '</a>';
			}
			// „Event details" = guzik-przełącznik (ghost); steruje panelem linków PONIŻEJ (w karcie, drobne linki).
			$out .= '<button type="button" class="pnb-ev-toggle pnb-ev-detbtn" aria-expanded="false" aria-controls="' . esc_attr( $det_id ) . '">'
				. '<span>' . esc_html__( 'Event details', 'pnb-toolkit' ) . '</span><span class="pnb-ev-chev" aria-hidden="true">▾</span></button>';
			// PRZYCISK EDYCJI dla właściciela (widoczny TYLKO zalogowanemu z prawem edycji — gość go nie widzi).
			// Natywny get_edit_post_link (wzorzec WP: edycja treści prosto z frontu dla admina).
			$edit = get_edit_post_link( $id );
			if ( $edit ) {
				$out .= '<a class="pnb-ev-edit" href="' . esc_url( $edit ) . '">✎ ' . esc_html__( 'Edit event', 'pnb-toolkit' ) . '</a>';
			}
			$out .= '</div>'; // .pnb-ev-actrow
			// ── Panel „Event details" (rodzeństwo rzędu, rozsuwa się POD nim; nie rusza guzików) ──
			$out .= '<div class="pnb-ev-panels">';
			$out .= '<div class="pnb-ev-panel pnb-ev-detpanel" id="' . esc_attr( $det_id ) . '" hidden>';
			$out .= '<div class="pnb-ev-detacts">';
			if ( $dojazd ) {
				$out .= '<a class="pnb-ev-gcal" href="' . esc_url( $dojazd ) . '" target="_blank" rel="noopener noreferrer">' . pnb_kalendarz_ikona( 'pinezka' ) . ' ' . esc_html__( 'Get directions', 'pnb-toolkit' ) . '</a>';
			}
			$out .= '<a class="pnb-ev-det pnb-ev-golink" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html__( 'Open event page', 'pnb-toolkit' ) . '<span class="pnb-ev-arrow" aria-hidden="true">→</span></a>';
			$out .= '</div>'; // .pnb-ev-detacts
			$out .= '</div>'; // .pnb-ev-detpanel
			$out .= '</div>'; // .pnb-ev-panels
			$out .= '</div>'; // .pnb-ev-cta
			$out .= '</div>'; // .pnb-ev-main
			// zdjęcie po prawej (link do singla) — hover-zoom żyje na IMG w masce .pnb-ev-zoom (reveal karty = osobny kanał)
			/* translators: %s = tytuł wydarzenia */
			$foto_a = '<a class="pnb-ev-plink pnb-ev-golink" href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Event details: %s', 'pnb-toolkit' ), pnb_event_tytul( $id ) ) ) . '">';
			if ( has_post_thumbnail( $id ) ) {
				$out .= '<div class="pnb-ev-photo">' . $foto_a . '<span class="pnb-ev-zoom">' . get_the_post_thumbnail( $id, 'large' ) . '</span></a></div>';
			} else {
				$out .= '<div class="pnb-ev-photo">' . $foto_a . '<span class="pnb-ev-zoom pnb-ev-photo-brak" aria-hidden="true">🐾</span></a></div>';
			}
			$out .= '</article>';
			// ══ ZAPIS POZA KARTĄ (v2.12.0, decyzja klienta) ═══════════════════════════════════════
			// Guzik „Sign up →" + formularz są RODZEŃSTWEM karty (tu, PO </article>), NIE w środku karty
			// — dzięki temu rozwinięcie formularza NIE MOŻE rozepchać układu kafelka. Pełna szerokość osi.
			// Guzik = <button aria-controls> steruje panelem formularza (JS toggle w kalendarz-front.js).
			// Pełne wydarzenie: zamiast guzika — nota „Sold out" (jak na karcie/singlu). Logika NIETKNIĘTA.
			$form_id = 'pnb-signup-' . (int) $id;
			$out .= '<div class="pnb-ev-signup">';
			// Wydarzenie ZAIMPORTOWANE (cudze) — na liście też link do oryginału, NIE formularz zapisu.
			$src_url = get_post_meta( (int) $id, '_pnb_source_url', true );
			if ( $src_url ) {
				$out .= '<a class="pnb-ev-extbtn pnb-ev-signbtn" href="' . esc_url( $src_url ) . '" target="_blank" rel="noopener nofollow">'
					. '<span>' . esc_html__( 'View event & tickets', 'pnb-toolkit' ) . '</span><span class="pnb-ev-arrow" aria-hidden="true">↗</span></a>';
			} elseif ( $pelne ) {
				$out .= '<p class="pnb-ev-signup-full"><span class="pnb-ev-badge pnb-ev-badge--full">' . esc_html__( 'Fully booked', 'pnb-toolkit' ) . '</span> <span class="pnb-ev-going pnb-ev-mono">' . esc_html__( 'Sold out', 'pnb-toolkit' ) . '</span></p>';
			} else {
				$out .= '<button type="button" class="pnb-ev-toggle pnb-ev-signbtn" aria-expanded="' . ( $otworz ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $form_id ) . '">'
					. '<span>' . esc_html__( 'Sign up', 'pnb-toolkit' ) . '</span><span class="pnb-ev-arrow" aria-hidden="true">→</span></button>';
				$out .= '<div class="pnb-ev-signup-panel" id="' . esc_attr( $form_id ) . '"' . ( $otworz ? '' : ' hidden' ) . '>';
				$out .= pnb_kalendarz_komunikaty_html( (int) $id ); // komunikat TYLKO przy zapisanej karcie
				$out .= pnb_kalendarz_formularz_html( $id ); // wspólny render z singlem — logika NIETKNIĘTA
				$out .= '</div>';
			}
			$out .= '</div>'; // .pnb-ev-signup
			$out .= '</div>'; // .pnb-ev-cardwrap
		}
		$out .= '</section>';
	}
	if ( $items ) {
		$out .= '<p class="pnb-ev-none is-hidden pnb-ev-mono">' . esc_html__( 'Nothing in this range — try “All”.', 'pnb-toolkit' ) . '</p>';
	}
	$out .= '</div></div>'; // .pnb-ev-tl-in, .pnb-ev-tl

	/* ── ŁĄCZNIK: kreskowana trasa od końca osi DO KARTY MAPY (P3: koniec dotyka górnej krawędzi
	   karty przy pinezce — końcówka schodzi poza viewBox, svg ma overflow:visible; wsp. z pomiaru
	   1440×900: górna krawędź mapbox ≈ y=140 vb, pion pinezki ≈ x=761 vb) ── */
	if ( $items ) {
		$out .= '<div class="pnb-ev-path" aria-hidden="true">'
			. '<svg viewBox="0 0 1040 110" preserveAspectRatio="none">'
			. '<path class="pnb-ev-path-d" d="M12.5 0 C 12.5 46, 160 18, 330 44 C 478 66, 560 76, 628 93 C 696 110, 738 124, 757 136"/>'
			. '<circle class="pnb-ev-path-end" cx="761" cy="140" r="4.5"/>'
			. '</svg></div>';
	}

	/* ── MAPA „Where to find us": adres z BLOKU (atrybut mapAddress) albo z 1. wydarzenia.
	   ⚠️ ZERO zmyślonego fallbacku (audyt 2026-07-05: zmyślony adres-fallback = fikcja pokazywana gościom
	   bez możliwości edycji — gość jechał pod wymyślony adres). Pusto → nie pokazujemy adresu
	   ani „Get directions" (klient uzupełnia adres w bloku Events albo w polu Miejsce wydarzenia). ── */
	$adres = pnb_txt( 'events.map.address', '' );
	if ( '' === trim( $adres ) && $items && ! empty( $items[0]['miejsce'] ) ) {
		$adres = $items[0]['miejsce'];
	}
	$adres = trim( (string) $adres );
	$dirs  = $adres ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $adres ) : '';
	$out  .= '<section class="pnb-ev-map">';
	$out  .= '<div class="pnb-ev-map-txt">';
	$out  .= '<span class="pnb-ev-eyebrow2 pnb-ev-mono">' . esc_html( pnb_txt( 'events.map.eyebrow', 'Where to find us' ) ) . '</span>';
	$out  .= '<h2>' . wp_kses( pnb_txt( 'events.map.title', 'Come say <em>hi</em>.' ), array( 'em' => array() ) ) . '</h2>';
	$out  .= '<p>' . esc_html( pnb_txt( 'events.map.lead', 'All events happen at the pension — a quiet street in the heart of Żoliborz.' ) ) . '</p>';
	// Kontakt: z ustawień klienta (pnb_kontakt_tel/mail). Puste → NIE pokazujemy placeholdera
	// (sztuczny "+48 000 000 000" na produkcji wygląda jak niedokończona strona).
	$tel_k  = trim( (string) get_option( 'pnb_kontakt_tel', '' ) );
	$mail_k = trim( (string) get_option( 'pnb_kontakt_mail', '' ) );
	if ( '' === $mail_k ) {
		$mail_k = get_option( 'admin_email' ); // sensowny fallback zamiast catsnboard.example
	}
	$kontakt_czesci = array_filter( array( $adres, $tel_k, $mail_k ) );
	if ( $kontakt_czesci ) {
		$out .= '<div class="pnb-ev-addr pnb-ev-mono">' . implode( '<br>', array_map( 'esc_html', $kontakt_czesci ) ) . '</div>';
	}
	if ( $dirs ) {
		$out .= '<a class="pnb-ev-dirs" href="' . esc_url( $dirs ) . '" target="_blank" rel="noopener noreferrer"><span>' . esc_html__( 'Get directions', 'pnb-toolkit' ) . '</span><span class="pnb-ev-arrow" aria-hidden="true">→</span></a>';
	}
	$out  .= '</div>';
	$out  .= '<div class="pnb-ev-mapbox" aria-hidden="true">'
		. '<svg class="pnb-ev-streets" viewBox="0 0 400 275" preserveAspectRatio="none">'
		. '<rect class="pnb-ev-park" x="24" y="24" width="96" height="66" rx="14"/>'
		. '<rect class="pnb-ev-park" x="290" y="180" width="86" height="70" rx="14"/>'
		. '<path class="pnb-ev-road pnb-ev-road-big" d="M0 92 C130 84 240 70 400 66"/>'
		. '<path class="pnb-ev-road pnb-ev-road-big" d="M0 188 C120 196 250 210 400 202"/>'
		. '<path class="pnb-ev-road pnb-ev-road-sm" d="M110 0 C102 90 96 190 92 275"/>'
		. '<path class="pnb-ev-road pnb-ev-road-sm" d="M272 0 C282 90 292 190 300 275"/>'
		. '<path class="pnb-ev-road pnb-ev-road-sm" d="M0 140 C120 132 180 200 400 150"/>'
		. '<path class="pnb-ev-route" d="M300 200 C240 175 210 150 200 121"/>'
		. '</svg>'
		. '<svg class="pnb-ev-pin" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8 2 5 5 5 9c0 5 7 13 7 13s7-8 7-13c0-4-3-7-7-7z"/><circle cx="12" cy="9" r="2.6" fill="#fff"/></svg>';
	$pin_label = trim( (string) pnb_txt( 'events.map.label', '' ) );
	if ( '' === $pin_label && $adres ) {
		$pin_label = $adres; // sensowny zamiennik: prawdziwy adres zamiast zmyślonej etykiety
	}
	if ( $pin_label ) {
		$out .= '<div class="pnb-ev-lab">' . esc_html( $pin_label ) . '</div>';
	}
	$out .= '</div>';
	$out .= '</section>';

	$out .= '</section>';
	return $out;
}

/* ==== SINGIEL WYDARZENIA (checklista UX „Strona wydarzenia"): hero + panel info + opis + zapis ==== */

/* Tytuł → słowa-schodki .pnb-wm/.pnb-wi (język hero listy); delay inline = działa dla DOWOLNEJ liczby słów
   (CSS nth-child kończy się na 5). Reduced-motion gasi animację regułą .pnb-wi w kalendarz.css. */
function pnb_kalendarz_splitwords( $tekst ) {
	$out = array();
	$i   = 0;
	foreach ( preg_split( '/\s+/', trim( (string) $tekst ) ) as $slowo ) {
		if ( '' === $slowo ) {
			continue;
		}
		$out[] = '<span class="pnb-wm"><span class="pnb-wi" style="animation-delay:' . esc_attr( number_format( 0.15 + $i * 0.08, 2, '.', '' ) ) . 's">' . esc_html( $slowo ) . '</span></span>';
		$i++;
	}
	return implode( ' ', $out );
}

/* Pełny layout singla — wołany przez filtr the_content (render przez plugin = działa na obcym motywie).
   Mechanika zapisów NIETKNIĘTA: formularz/komunikaty/GCal to te same helpery co na liście. */
function pnb_kalendarz_render_single() {
	pnb_kalendarz_zaladuj_assety();
	// obcy motyw może dokładać własny hero/tytuł/gorset na singlach — chowamy jak na stronie listy
	wp_add_inline_style( 'pnb-kalendarz',
		'.single-pnb_wydarzenie .page-hero{display:none}'
		. '.single-pnb_wydarzenie .entry-title{display:none}'
		. '.single-pnb_wydarzenie .location{padding:0;background:transparent}'
		. '.single-pnb_wydarzenie .location .wrap{max-width:none;padding:0}'
		. '.single-pnb_wydarzenie .page-content{max-width:none !important;margin:0 !important}' );

	$id        = get_the_ID();
	$data      = (string) get_post_meta( $id, '_pnb_event_date', true );
	$godzina   = (string) get_post_meta( $id, '_pnb_event_time', true );
	$koniec    = (string) get_post_meta( $id, '_pnb_event_time_end', true );
	$miejsce   = (string) get_post_meta( $id, '_pnb_event_place', true );
	$miejsce_disp = pnb_event_miejsce( $id, $miejsce ); // PL gdy wpisane (do WYŚWIETLENIA); $miejsce surowe zostaje dla GCal/mapy
	$limit     = (int) get_post_meta( $id, '_pnb_event_limit', true );
	$kat       = get_post_meta( $id, '_pnb_event_cat', true );
	$kategorie = pnb_kalendarz_kategorie();
	if ( ! isset( $kategorie[ $kat ] ) ) {
		$kat = 'other';
	}
	$zajete = pnb_kalendarz_ile_zapisow( $id );
	$pelne  = $limit > 0 && $zajete >= $limit;
	$ts     = $data ? strtotime( $data ) : 0;

	// godziny od–do (ten sam format co karty listy)
	$fmt = get_option( 'time_format', 'g:i a' );
	$g   = '';
	if ( $godzina && $ts ) {
		$g = date_i18n( $fmt, strtotime( $data . ' ' . $godzina ) );
		if ( $koniec ) {
			$g .= ' — ' . date_i18n( $fmt, strtotime( $data . ' ' . $koniec ) );
		}
	}

	/* ── HERO ~55vh: featured full-bleed + welon marki (lżejszy) + tytuł splitwords + mono-linia ── */
	// WYDAJNOŚĆ: wp_get_attachment_image z 'large'+srcset zamiast surowego 'full' (oryginał klienta = MB).
	$hero_id = (int) get_post_thumbnail_id( $id );
	$out  = '<section class="pnb-events pnb-events--single">';
	$out .= '<header class="pnb-evh pnb-evh--single">';
	if ( $hero_id ) {
		$out .= '<div class="pnb-evh-img" aria-hidden="true">'
			. wp_get_attachment_image( $hero_id, 'large', false, array(
				'alt'           => '',
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'sizes'         => '100vw',
			) )
			. '</div>';
	}
	$out .= '<div class="pnb-evh-veil" aria-hidden="true"></div>';
	$out .= '<div class="pnb-evh-grain" aria-hidden="true"></div>';
	$out .= '<div class="pnb-evh-in">';
	$out .= '<span class="pnb-ev-eyebrow">' . esc_html( pnb_txt( 'events.single.eyebrow', "Cats'N'Board · Event" ) ) . '</span>';
	$out .= '<h1 class="pnb-ev-h1">' . pnb_kalendarz_splitwords( pnb_event_tytul( $id ) ) . '</h1>';
	$mono = array();
	if ( $ts ) {
		$data_hero = date_i18n( 'l, F j', $ts );
		$mono[] = '<span>' . esc_html( $data_hero ) . '</span>';
	}
	if ( $g ) {
		$mono[] = '<span>' . esc_html( $g ) . '</span>';
	}
	if ( $miejsce ) {
		$mono[] = '<span>' . pnb_kalendarz_ikona( 'pinezka' ) . esc_html( $miejsce_disp ) . '</span>';
	}
	if ( $mono ) {
		$out .= '<p class="pnb-evs-meta pnb-ev-mono">' . implode( '<span class="pnb-evs-sep" aria-hidden="true">·</span>', $mono ) . '</p>';
	}
	$out .= '</div></header>';

	/* ── BODY: powrót → panel info → opis → zapis ── */
	$pid  = (int) get_option( 'pnb_wydarzenia_strona', 0 );
	$back = $pid ? get_permalink( $pid ) : home_url( '/' );
	$out .= '<div class="pnb-evs-body">';
	$out .= '<a class="pnb-evs-back pnb-ev-mono" href="' . esc_url( $back ) . '"><span class="pnb-ev-arrow" aria-hidden="true">←</span> ' . esc_html__( 'All events', 'pnb-toolkit' ) . '</a>';

	// panel info: kolumny Data/Godzina/Miejsce/Kategoria/Wolne miejsca + akcje (GCal, dojazd)
	$cell = function ( $etykieta, $wartosc_html ) {
		return '<div class="pnb-evs-cell"><span class="pnb-evs-k pnb-ev-mono">' . esc_html( $etykieta ) . '</span><span class="pnb-evs-v">' . $wartosc_html . '</span></div>';
	};
	$out .= '<div class="pnb-evs-panel">';
	if ( $ts ) {
		$data_panel = date_i18n( get_option( 'date_format', 'F j, Y' ), $ts );
		$out .= $cell( __( 'Date', 'pnb-toolkit' ), esc_html( $data_panel ) );
	}
	if ( $g ) {
		$out .= $cell( __( 'Time', 'pnb-toolkit' ), esc_html( $g ) );
	}
	if ( $miejsce ) {
		$out .= $cell( __( 'Place', 'pnb-toolkit' ), esc_html( $miejsce_disp ) );
	}
	$out .= $cell( __( 'Category', 'pnb-toolkit' ),
		'<span class="pnb-ev-tag" data-c="' . esc_attr( $kat ) . '"><span class="pnb-ev-dot" data-c="' . esc_attr( $kat ) . '" aria-hidden="true"></span>' . esc_html( $kategorie[ $kat ] ) . '</span>' );
	// Cena — NIE w panelu; pokazujemy ją przy przycisku „kup bilet" (sekcja Interested?),
	// bo cena + akcja kupna to jedna decyzja (wzór Eventbrite).
	if ( $limit > 0 ) {
		$wolne = $pelne
			? '<span class="pnb-evs-full">' . esc_html__( 'Fully booked', 'pnb-toolkit' ) . '</span>'
			/* translators: 1: liczba wolnych miejsc, 2: limit miejsc */
			: esc_html( sprintf( __( '%1$d of %2$d spots left', 'pnb-toolkit' ), $limit - $zajete, $limit ) );
		$out  .= $cell( __( 'Spots', 'pnb-toolkit' ), $wolne );
	}
	$out .= '<div class="pnb-evs-actions">';
	$gcal = pnb_kalendarz_gcal_url( $id, $data, $godzina, $koniec, $miejsce );
	if ( $gcal ) {
		$out .= '<a class="pnb-ev-gcal" href="' . esc_url( $gcal ) . '" target="_blank" rel="noopener noreferrer">' . pnb_kalendarz_ikona( 'plus' ) . ' ' . esc_html__( 'Add to Google Calendar', 'pnb-toolkit' ) . '</a>';
	}
	// „Get directions" — używa PEŁNEGO adresu jeśli jest (dokładny pin), inaczej nazwy miejsca.
	$adres_pelny = get_post_meta( $id, '_pnb_event_address', true );
	$cel_map     = $adres_pelny ? $adres_pelny : $miejsce;
	if ( $cel_map ) {
		$dojazd = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $cel_map );
		$out   .= '<a class="pnb-ev-gcal pnb-evs-getdirs" href="' . esc_url( $dojazd ) . '" target="_blank" rel="noopener noreferrer">' . pnb_kalendarz_ikona( 'pinezka' ) . ' ' . esc_html__( 'Get directions', 'pnb-toolkit' ) . '</a>';
	}
	$out .= '</div>'; // .pnb-evs-actions
	$out .= '</div>'; // .pnb-evs-panel

	// „Co warto wiedzieć" (Good to know) — praktyczne info dla gościa. Działa dla importu
	// (dane z Eventbrite) I dla wydarzeń własnych klienta (gdy wpisze highlights).
	$out .= pnb_kalendarz_good_to_know_html( $id );

	// pełny opis (PL gdy wpisany)
	$tresc = pnb_event_opis( $id );
	if ( trim( $tresc ) ) {
		$out .= '<div class="pnb-evs-desc">' . wp_kses_post( wpautop( $tresc ) ) . '</div>';
	}

	// zapis: kotwica #event-{id} = cel redirectu handlera; komunikaty NAD formularzem
	$out .= '<section class="pnb-evs-signup" id="event-' . (int) $id . '">';
	// Wydarzenie zaimportowane (source_url) — nagłówek „Interested?" + link do oryginału (nie „Sign up",
	// bo u nas nie ma zapisu na cudze wydarzenie). Własne — normalny nagłówek „Sign up" + formularz.
	$jest_importowane = (bool) get_post_meta( (int) $id, '_pnb_source_url', true );
	if ( $jest_importowane ) {
		$out .= '<h2>' . esc_html__( 'Interested?', 'pnb-toolkit' ) . '</h2>';
		$out .= '<p class="pnb-evs-extnote">' . esc_html__( 'This event is hosted externally — see full details and get tickets at the organiser.', 'pnb-toolkit' ) . '</p>';
		// Cena TU (przy przycisku kupna) — cena + akcja razem, jak Eventbrite.
		$cena_raw = get_post_meta( (int) $id, '_pnb_event_price', true );
		if ( $cena_raw ) {
			$cena_disp = ( 'free' === $cena_raw ) ? __( 'Free', 'pnb-toolkit' ) : $cena_raw;
			$out      .= '<p class="pnb-evs-price"><span class="pnb-evs-price-label">' . esc_html__( 'From', 'pnb-toolkit' ) . '</span> <span class="pnb-evs-price-val">' . esc_html( $cena_disp ) . '</span></p>';
		}
		$out .= pnb_kalendarz_formularz_html( $id ); // zwróci przycisk „View event & tickets"
	} elseif ( $pelne ) {
		$out .= '<h2>' . esc_html__( 'Sign up', 'pnb-toolkit' ) . '</h2>';
		$out .= pnb_kalendarz_komunikaty_html( (int) $id );
		$out .= '<p class="pnb-evs-fullnote"><span class="pnb-ev-badge pnb-ev-badge--full">' . esc_html__( 'Fully booked', 'pnb-toolkit' ) . '</span> <span class="pnb-ev-going pnb-ev-mono">' . esc_html__( 'Sold out', 'pnb-toolkit' ) . '</span></p>';
	} else {
		$out .= '<h2>' . esc_html__( 'Sign up', 'pnb-toolkit' ) . '</h2>';
		$out .= pnb_kalendarz_komunikaty_html( (int) $id );
		$out .= pnb_kalendarz_formularz_html( $id );
	}
	$out .= '</section>';

	$out .= '</div>'; // .pnb-evs-body
	$out .= '</section>'; // .pnb-events--single
	return $out;
}

/* Podmiana treści singla na pełny layout — te same bezpieczniki co auto-wstaw
   (in_the_loop + is_main_query + static: raz na żądanie, nie w widgetach/excerptach). */
add_filter( 'the_content', function ( $tresc ) {
	static $zrobione = false;
	if ( $zrobione || ! is_singular( 'pnb_wydarzenie' ) || ! in_the_loop() || ! is_main_query() ) {
		return $tresc;
	}
	$zrobione = true;
	return pnb_kalendarz_render_single();
} );

/* Siatka bezpieczeństwa pod filtr the_content: motyw BEZ single.php (fallback index.php woła
   the_excerpt, nigdy the_content — layout by nie powstał) dostaje minimalny szablon pluginu.
   Motyw z własnym single*.php zostaje uszanowany — wtedy działa sam filtr. */
add_filter( 'template_include', function ( $template ) {
	if ( is_singular( 'pnb_wydarzenie' ) && 'index.php' === basename( (string) $template ) ) {
		$wlasny = PNB_TOOLKIT_DIR . 'templates/single-pnb-wydarzenie.php';
		if ( file_exists( $wlasny ) ) {
			return $wlasny;
		}
	}
	return $template;
}, 20 );

/* ==== ZAPIS GOŚCIA (admin-post, także niezalogowani) ==== */

add_action( 'admin_post_nopriv_pnb_zapis', 'pnb_kalendarz_przyjmij_zapis' );
add_action( 'admin_post_pnb_zapis', 'pnb_kalendarz_przyjmij_zapis' );

function pnb_kalendarz_przyjmij_zapis() {
	$event = isset( $_POST['pnb_event'] ) ? absint( $_POST['pnb_event'] ) : 0;
	// wracaj tam skąd gość przyszedł (shortcode może być na innej stronie); fallbacki: strona wydarzeń → home
	$dokad = wp_get_referer();
	if ( ! $dokad ) {
		$pid   = (int) get_option( 'pnb_wydarzenia_strona', 0 );
		$dokad = $pid ? get_permalink( $pid ) : '';
	}
	if ( ! $dokad ) {
		$dokad = home_url( '/' );
	}
	$wroc = function ( $status ) use ( $dokad, $event ) {
		// pnb_event w URL: karta wydarzenia otwiera się rozwinięta z komunikatem po powrocie
		$cel = add_query_arg( array( 'pnb_zapis' => $status, 'pnb_event' => $event ), $dokad );
		wp_safe_redirect( $cel . ( $event ? '#event-' . $event : '' ) );
		exit;
	};

	// honeypot: bot wypełnił ukryte pole → udajemy sukces, nic nie zapisujemy
	if ( ! empty( $_POST['pnb_hp'] ) ) {
		$wroc( 'ok' );
	}
	if ( ! $event || 'pnb_wydarzenie' !== get_post_type( $event ) || 'publish' !== get_post_status( $event ) ) {
		$wroc( 'blad' );
	}
	if ( ! isset( $_POST['pnb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pnb_nonce'] ), 'pnb_zapis_' . $event ) ) {
		$wroc( 'expired' ); // nonce wygasa po ~12-24h — to nie wina danych gościa, powiedz prawdę
	}

	$imie  = isset( $_POST['pnb_imie'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_imie'] ) ) : '';
	$email = isset( $_POST['pnb_email'] ) ? sanitize_email( wp_unslash( $_POST['pnb_email'] ) ) : '';
	$tel   = isset( $_POST['pnb_tel'] ) ? sanitize_text_field( wp_unslash( $_POST['pnb_tel'] ) ) : '';
	if ( ! $imie || ! is_email( $email ) ) {
		$wroc( 'blad' );
	}

	// dedupe: ten sam mail na to samo wydarzenie = jeden zapis (griefing/podwójny klik)
	$dubel = get_posts( array(
		'post_type'      => 'pnb_zapis',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array( 'key' => '_pnb_zapis_wydarzenie', 'value' => $event ),
			array( 'key' => '_pnb_zapis_email', 'value' => $email ),
		),
	) );
	if ( $dubel ) {
		$wroc( 'dupl' );
	}

	// ZAPIS DO BAZY — zawsze najpierw; mail to tylko powiadomienie (lekcja wp_mail→spam)
	$zapis = wp_insert_post( array(
		'post_type'   => 'pnb_zapis',
		'post_status' => 'publish',
		'post_title'  => $imie,
	) );
	if ( is_wp_error( $zapis ) || ! $zapis ) {
		$wroc( 'blad' );
	}
	update_post_meta( $zapis, '_pnb_zapis_email', $email );
	update_post_meta( $zapis, '_pnb_zapis_tel', $tel );
	update_post_meta( $zapis, '_pnb_zapis_wydarzenie', $event );

	// LIMIT bez wyścigu: wstaw NAJPIERW, potem sprawdź pozycję po ID (auto-increment rozstrzyga remis);
	// za limitem → cofnij własny wpis. Dwóch na ostatnie miejsce = wejdzie dokładnie jeden.
	$limit = (int) get_post_meta( $event, '_pnb_event_limit', true );
	if ( $limit > 0 ) {
		$ids = get_posts( array(
			'post_type'      => 'pnb_zapis',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => '_pnb_zapis_wydarzenie',
			'meta_value'     => $event,
		) );
		$poz = array_search( $zapis, array_map( 'intval', $ids ), true );
		if ( false === $poz || $poz >= $limit ) {
			wp_delete_post( $zapis, true );
			$wroc( 'full' );
		}
	}

	// mail do właściciela: From=domena strony (bez www — SPF/DMARC), Reply-To=sam adres (imię z cudzysłowem psuje nagłówek)
	$do    = get_option( 'pnb_kalendarz_email' );
	$do    = $do ? $do : get_option( 'admin_email' );
	$host  = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	// mail to czysty tekst — encje HTML muszą wrócić do znaków (nazwa „Cats'N'Board" dawała &#039; u odbiorcy)
	$nazwa      = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$wydarzenie = wp_specialchars_decode( get_the_title( $event ), ENT_QUOTES );
	$naglowki = array(
		'From: ' . $nazwa . ' <wordpress@' . $host . '>',
		'Reply-To: <' . $email . '>',
	);
	$temat = sprintf( __( 'New sign-up: %s', 'pnb-toolkit' ), $wydarzenie );
	$tresc = sprintf(
		/* translators: 1 name, 2 email, 3 phone, 4 event, 5 date */
		__( "New event sign-up.\n\nName: %1\$s\nE-mail: %2\$s\nPhone: %3\$s\nEvent: %4\$s (%5\$s)\n\nFull list: dashboard → Events.", 'pnb-toolkit' ),
		$imie, $email, ( $tel ? $tel : '—' ), $wydarzenie, get_post_meta( $event, '_pnb_event_date', true )
	);
	add_action( 'wp_mail_failed', 'pnb_kalendarz_mail_blad' );
	wp_mail( $do, $temat, $tresc, $naglowki );
	remove_action( 'wp_mail_failed', 'pnb_kalendarz_mail_blad' );

	$wroc( 'ok' );
}

function pnb_kalendarz_mail_blad( $blad ) {
	if ( function_exists( 'error_log' ) ) {
		error_log( 'PNB kalendarz: mail nie wyszedł — ' . ( is_wp_error( $blad ) ? $blad->get_error_message() : 'nieznany błąd' ) ); // phpcs:ignore
	}
}

/* USUNIĘTE 2026-07-05 (domknięcie audytu): zdublowany register_setting dla pnb_kalendarz_email w grupie
 * „pnb_galeria" — relikt po usuniętym panelu galerii. Opcja jest zarejestrowana raz, poprawnie, w grupie
 * „pnb_events_settings" (patrz góra pliku, przy ekranie Settings menu Events). Dublet mylił i był martwy. */
