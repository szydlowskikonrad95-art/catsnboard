<?php
/*
 * PANEL ADMINA — klucz API (maskowany), model, limit dzienny, TEST, oraz serce prostej wersji:
 * przycisk „Przetłumacz witrynę" z paskiem postępu.
 *
 * Jak działa przycisk (bez loopbacku): JS pobiera listę podstron → dla każdej fetch(url,{credentials:'omit'})
 * w przeglądarce ADMINA (strona renderuje się jak dla gościa) → POST HTML do admin-ajax → serwer tnie,
 * tłumaczy braki batchem, zapisuje słownik. Admin patrzy na postęp; gość nigdy nie czeka.
 *
 * Bezpieczeństwo (z audytów): klucz NIE wraca do HTML w całości (maska ...końcówka), nonce+manage_options,
 * ostrzeżenie gdy wykryty WPML/Polylang (dwa systemy językowe się gryzą).
 *
 * i18n: WSZYSTKIE napisy po angielsku przez __()/esc_html__ (standard WP) — polski w languages/*.mo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Strona ustawień. */
add_action( 'admin_menu', function () {
	add_options_page( 'PNB Auto PL', 'PNB Auto PL', 'manage_options', 'pnb-auto-pl', 'pnb_pl_ekran_admina' );
} );

/* Zapis ustawień. */
add_action( 'admin_init', function () {
	if ( ! isset( $_POST['pnb_pl_zapisz'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'pnb_pl_ustawienia' );
	// klucz: zapisz TYLKO gdy wpisano nowy (puste pole = zostaw stary; maski nie zapisujemy)
	$klucz = trim( (string) wp_unslash( $_POST['pnb_klucz'] ?? '' ) );
	if ( '' !== $klucz && false === strpos( $klucz, '…' ) ) {
		update_option( 'pnb_auto_pl_klucz', $klucz, false );
	}
	$model = sanitize_text_field( wp_unslash( $_POST['pnb_model'] ?? 'claude-haiku-4-5' ) );
	update_option( 'pnb_auto_pl_model', in_array( $model, array( 'claude-haiku-4-5', 'claude-sonnet-5' ), true ) ? $model : 'claude-haiku-4-5' );
	update_option( 'pnb_auto_pl_limit_znakow', max( 1000, (int) ( $_POST['pnb_limit'] ?? 100000 ) ) );
	add_settings_error( 'pnb_pl', 'zapisano', __( 'Settings saved.', 'pnb-auto-pl' ), 'success' );
} );

/* AJAX: test połączenia. */
add_action( 'wp_ajax_pnb_pl_test', function () {
	check_ajax_referer( 'pnb_pl_tlumacz', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'blad' => __( 'No permission.', 'pnb-auto-pl' ) ), 403 );
	}
	$w = pnb_auto_pl_test_polaczenia();
	if ( is_wp_error( $w ) ) {
		wp_send_json_error( array( 'blad' => $w->get_error_message() ) );
	}
	wp_send_json_success();
} );

/* AJAX: wyczyść listę nieaktualnych (po pełnym tłumaczeniu). */
add_action( 'wp_ajax_pnb_pl_wyczysc_stale', function () {
	check_ajax_referer( 'pnb_pl_tlumacz', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'blad' => __( 'No permission.', 'pnb-auto-pl' ) ), 403 );
	}
	pnb_pl_wyczysc_nieaktualne();
	wp_send_json_success();
} );

/** Lista adresów do przetłumaczenia: opublikowane strony + wpisy + wydarzenia. */
function pnb_pl_lista_stron() {
	$posty = get_posts( array(
		'post_type'   => array( 'page', 'post', 'pnb_wydarzenie' ),
		'post_status' => 'publish',
		'numberposts' => 200,
	) );
	$lista = array( array( 'tytul' => __( 'Home page', 'pnb-auto-pl' ), 'url' => home_url( '/' ) ) );
	foreach ( $posty as $p ) {
		$lista[] = array( 'tytul' => $p->post_title, 'url' => get_permalink( $p ) );
	}
	return $lista;
}

/** Ekran admina. */
function pnb_pl_ekran_admina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$klucz   = (string) get_option( 'pnb_auto_pl_klucz', '' );
	$maska   = $klucz ? '…' . substr( $klucz, -4 ) : '';
	$model   = (string) get_option( 'pnb_auto_pl_model', 'claude-haiku-4-5' );
	$limit   = (int) get_option( 'pnb_auto_pl_limit_znakow', 100000 );
	$staty   = pnb_pl_statystyki_slownika();
	$strony  = pnb_pl_lista_stron();
	$nonce   = wp_create_nonce( 'pnb_pl_tlumacz' );
	$wpml    = defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
	$polylang = defined( 'POLYLANG_VERSION' );
	settings_errors( 'pnb_pl' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PNB Auto PL — site translation (Claude)', 'pnb-auto-pl' ); ?></h1>

		<?php if ( $wpml || $polylang ) : ?>
		<div class="notice notice-error"><p><strong>⚠️ <?php
			/* translators: %s: name of the conflicting plugin (WPML or Polylang) */
			echo esc_html( sprintf( __( '%s detected.', 'pnb-auto-pl' ), $wpml ? 'WPML' : 'Polylang' ) ); ?></strong>
			<?php
			/* translators: %s: name of the conflicting plugin (WPML or Polylang) */
			echo esc_html( sprintf( __( 'Two language systems on one site may clash. Recommendation: use ONE — either this plugin or %s (deactivate the other).', 'pnb-auto-pl' ), $wpml ? 'WPML' : 'Polylang' ) ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pnb_pl_ustawienia' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="pnb_klucz"><?php esc_html_e( 'Anthropic API key', 'pnb-auto-pl' ); ?></label></th>
					<td>
						<input type="password" id="pnb_klucz" name="pnb_klucz" class="regular-text"
							placeholder="<?php echo esc_attr( $maska ?: 'sk-ant-…' ); ?>" autocomplete="new-password">
						<p class="description"><?php
							if ( $klucz ) {
								/* translators: %s: masked ending of the saved API key */
								echo esc_html( sprintf( __( 'Key saved (ends with %s). Enter a new one only to change it.', 'pnb-auto-pl' ), $maska ) );
							} else {
								esc_html_e( 'Paste your key from console.anthropic.com.', 'pnb-auto-pl' );
							}
						?></p>
					</td>
				</tr>
				<tr>
					<th><label for="pnb_model"><?php esc_html_e( 'Model', 'pnb-auto-pl' ); ?></label></th>
					<td>
						<select id="pnb_model" name="pnb_model">
							<option value="claude-haiku-4-5" <?php selected( $model, 'claude-haiku-4-5' ); ?>><?php esc_html_e( 'Haiku 4.5 — fast & cheap (recommended)', 'pnb-auto-pl' ); ?></option>
							<option value="claude-sonnet-5" <?php selected( $model, 'claude-sonnet-5' ); ?>><?php esc_html_e( 'Sonnet 5 — better wording, pricier', 'pnb-auto-pl' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="pnb_limit"><?php esc_html_e( 'Daily character limit', 'pnb-auto-pl' ); ?></label></th>
					<td>
						<input type="number" id="pnb_limit" name="pnb_limit" value="<?php echo esc_attr( $limit ); ?>" min="1000" step="1000">
						<p class="description"><?php
							esc_html_e( 'Cost safety valve: above the limit, translation pauses until tomorrow.', 'pnb-auto-pl' );
							echo ' ';
							/* translators: %s: number of characters used today */
							echo wp_kses( sprintf( __( 'Used today: <strong>%s</strong> characters.', 'pnb-auto-pl' ), number_format_i18n( pnb_pl_licznik_dzis() ) ), array( 'strong' => array() ) );
						?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="pnb_pl_zapisz" value="1" class="button button-primary"><?php esc_html_e( 'Save settings', 'pnb-auto-pl' ); ?></button>
				<button type="button" class="button" id="pnb-test"><?php esc_html_e( 'Test connection', 'pnb-auto-pl' ); ?></button>
				<span id="pnb-test-wynik" style="margin-left:8px;"></span>
			</p>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Site translation', 'pnb-auto-pl' ); ?></h2>
		<p><?php
			// LICZNIK (naprawa 2026-07-10, znalezione w testach): pokazywał ROZMIAR witryny (count stron),
			// więc po pełnym tłumaczeniu dalej straszył „37 do przetłumaczenia". Teraz: zaległości
			// z listy pnb_pl_nieaktualne (spada do 0), a rozmiar witryny osobno — bez kłamstwa.
			$zaleglosci = get_option( 'pnb_pl_nieaktualne', array() );
			$zaleglosci = is_array( $zaleglosci ) ? count( $zaleglosci ) : 0;
			/* translators: 1: translated phrases, 2: corrected by hand, 3: pages in the site, 4: pages awaiting translation */
			echo wp_kses( sprintf( __( 'Dictionary: <strong>%1$s</strong> translated phrases (%2$s corrected by hand). Site pages: %3$s, awaiting translation: <strong>%4$s</strong>.', 'pnb-auto-pl' ),
				esc_html( $staty['gotowe'] ), esc_html( $staty['czlowiek'] ), esc_html( count( $strony ) ), esc_html( $zaleglosci ) ), array( 'strong' => array() ) );
		?></p>
		<p>
			<button type="button" class="button button-primary button-hero" id="pnb-tlumacz-wszystko">
				🌍 <?php esc_html_e( 'Translate site to Polish', 'pnb-auto-pl' ); ?>
			</button>
		</p>
		<div id="pnb-postep" style="display:none;max-width:640px;">
			<div style="background:#f0f0f1;border-radius:4px;overflow:hidden;height:24px;">
				<div id="pnb-pasek" style="background:#2271b1;height:100%;width:0;transition:width .3s;"></div>
			</div>
			<p id="pnb-status" style="font-family:monospace;"></p>
			<ul id="pnb-log" style="font-family:monospace;font-size:12px;max-height:220px;overflow:auto;"></ul>
		</div>
	</div>

	<script>
	(function () {
		var strony = <?php echo wp_json_encode( $strony ); ?>;
		var nonce  = <?php echo wp_json_encode( $nonce ); ?>;
		var ajax   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var t      = <?php echo wp_json_encode( array(
			'dziala'    => __( 'Works', 'pnb-auto-pl' ),
			'blad'      => __( 'error', 'pnb-auto-pl' ),
			'strona'    => __( 'Page', 'pnb-auto-pl' ),
			'segmenty'  => __( 'segments:', 'pnb-auto-pl' ),
			'zPamieci'  => __( 'from cache:', 'pnb-auto-pl' ),
			'nowych'    => __( 'new:', 'pnb-auto-pl' ),
			'pominiete' => __( 'skipped:', 'pnb-auto-pl' ),
			'limitStop' => __( 'Daily character limit reached — the rest tomorrow (or raise the limit).', 'pnb-auto-pl' ),
			'gotowe'    => __( 'Done. New translations:', 'pnb-auto-pl' ),
			'sprawdz'   => __( 'Check the site with the PL switcher.', 'pnb-auto-pl' ),
		) ); ?>;

		document.getElementById('pnb-test').addEventListener('click', function () {
			var w = document.getElementById('pnb-test-wynik');
			w.textContent = '⏳…';
			fetch(ajax, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: new URLSearchParams({ action: 'pnb_pl_test', nonce: nonce }) })
				.then(function (r) { return r.json(); })
				.then(function (j) { w.textContent = j.success ? ('✅ ' + t.dziala) : ('❌ ' + (j.data && j.data.blad || t.blad)); })
				.catch(function (e) { w.textContent = '❌ ' + e; });
		});

		document.getElementById('pnb-tlumacz-wszystko').addEventListener('click', async function () {
			var btn = this, pasek = document.getElementById('pnb-pasek'),
				status = document.getElementById('pnb-status'), log = document.getElementById('pnb-log');
			btn.disabled = true;
			document.getElementById('pnb-postep').style.display = 'block';
			log.innerHTML = '';
			var razem = 0, limitStop = false;

			for (var i = 0; i < strony.length; i++) {
				var s = strony[i];
				status.textContent = t.strona + ' ' + (i + 1) + '/' + strony.length + ': ' + s.tytul;
				pasek.style.width = Math.round((i / strony.length) * 100) + '%';
				try {
					// pobierz stronę jak GOŚĆ (bez cookies = czysty HTML bez admin-bara)
					var html = await (await fetch(s.url, { credentials: 'omit' })).text();
					// wyślij do tłumaczenia
					var odp = await (await fetch(ajax, { method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: new URLSearchParams({ action: 'pnb_pl_tlumacz_strone', nonce: nonce, url: s.url, html: html }) })).json();
					if (odp.success) {
						var d = odp.data;
						razem += d.przetlumaczone;
						log.insertAdjacentHTML('beforeend', '<li>✅ ' + s.tytul + ' — ' + t.segmenty + ' ' + d.segmenty +
							', ' + t.zPamieci + ' ' + d.z_cache + ', ' + t.nowych + ' ' + d.przetlumaczone +
							(d.pominiete ? ', ' + t.pominiete + ' ' + d.pominiete : '') + '</li>');
						if (d.limit_wyczerpany) { limitStop = true; }
					} else {
						log.insertAdjacentHTML('beforeend', '<li>❌ ' + s.tytul + ' — ' + (odp.data && odp.data.blad || t.blad) + '</li>');
					}
				} catch (e) {
					log.insertAdjacentHTML('beforeend', '<li>❌ ' + s.tytul + ' — ' + e + '</li>');
				}
				if (limitStop) {
					log.insertAdjacentHTML('beforeend', '<li>⛔ ' + t.limitStop + '</li>');
					break;
				}
			}
			pasek.style.width = '100%';
			status.textContent = t.gotowe + ' ' + razem + '. ' + t.sprawdz;
			// Listę "nieaktualne" zdejmuje SERWER per przetłumaczoną stronę (odporne na przerwanie).
			// Globalne czyszczenie TYLKO gdy przebieg doszedł do końca (przy stopie limitem zostają
			// realne zaległości — wcześniej czyściło wszystko i kłamało, naprawa 2026-07-10).
			if (!limitStop) {
				fetch(ajax, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: new URLSearchParams({ action: 'pnb_pl_wyczysc_stale', nonce: nonce }) });
			}
			btn.disabled = false;
		});
	})();
	</script>
	<?php
}
