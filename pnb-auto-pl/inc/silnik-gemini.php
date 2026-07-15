<?php
/*
 * SILNIK TŁUMACZENIA — Google Gemini (darmowy tier, bez karty).
 *
 * Alternatywa dla Claude: klucz z aistudio.google.com/apikey (zwykłe konto Google, ZERO karty).
 * Gemini to LLM (jak Claude) → ten sam wzorzec: prompt-JSON batch, ta sama walidacja
 * (tagi/kses/meta-gadka z silnik-claude.php). Różnice obsłużone tutaj:
 *
 * 1. LIMITY (zmierzone NA ŻYWO 2026-07-15, nie z docs — docs mówią „nie gwarantujemy"):
 *    - RPM: 15/min TWARDO (16. zapytanie = HTTP 429). Stąd THROTTLE poniżej.
 *    - RPD: ~1000/dzień, reset o północy czasu PACYFICZNEGO (nie naszej! stąd strefa w liczniku).
 *    - Limity per PROJEKT, nie per klucz — dorabianie kluczy nic nie da.
 * 2. MODEL: `gemini-3.1-flash-lite` — USTALONY TESTEM. `gemini-2.5-flash-lite` (ten z docs!) zwraca
 *    404 „no longer available to new users", `gemini-2.0-flash-lite` = zerowa quota. Google wycofuje
 *    modele bez uprzedzenia → model jest OPCJĄ (edytowalny), nie stałą w kodzie.
 * 3. ODPOWIEDŹ: `candidates[0].content.parts[0].text` — ⚠️ parts niesie też `thoughtSignature`,
 *    dlatego szukamy pola `text`, nie zakładamy że parts ma jeden klucz.
 * 4. 429 = TWARDA PRAWDA ważniejsza niż nasz licznik (Google zmienia limity) → stop + fallback EN.
 *
 * Awaria (429/limit/błąd) NIGDY nie wywala strony: wołający dostaje WP_Error → segment zostaje EN,
 * następny cykl importera (10 min) dotłumaczy. Zero utraty treści.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Domyślny model — sprawdzony na żywo. Zmienialny w opcjach (Google wycofuje modele). */
const PNB_PL_GEMINI_MODEL_DOMYSLNY = 'gemini-3.1-flash-lite';

/** Minimalny odstęp między zapytaniami [s] — 15 RPM = 1 zapytanie / 4 s (zmierzone). */
const PNB_PL_GEMINI_ODSTEP_S = 4;

/* ===== LICZNIK DZIENNY ZAPYTAŃ (RPD) — strefa PACYFICZNA, bo tak resetuje Google ===== */

/** Dzień wg strefy Google (America/Los_Angeles) — nasz current_time() dałby rozjazd ~9 h. */
function pnb_pl_gemini_dzien_pt() {
	try {
		$d = new DateTime( 'now', new DateTimeZone( 'America/Los_Angeles' ) );
		return $d->format( 'Y-m-d' );
	} catch ( \Exception $e ) {
		return gmdate( 'Y-m-d' ); // awaryjnie UTC — lepsze niż nic
	}
}

/** Dzienny limit zapytań (opcja — Google zmienia limity, klient/my możemy podkręcić). */
function pnb_pl_gemini_limit_zapytan() {
	return max( 10, (int) get_option( 'pnb_auto_pl_gemini_limit_rpd', 1000 ) );
}

/** Ile zapytań poszło dziś (wg dnia pacyficznego). */
function pnb_pl_gemini_licznik_dzis() {
	$l = get_option( 'pnb_pl_gemini_licznik', array() );
	$dzis = pnb_pl_gemini_dzien_pt();
	return ( is_array( $l ) && ( $l['dzien'] ?? '' ) === $dzis ) ? (int) $l['zapytan'] : 0;
}

/** Dopisz jedno zapytanie do licznika. */
function pnb_pl_gemini_licznik_dodaj() {
	$dzis = pnb_pl_gemini_dzien_pt();
	update_option( 'pnb_pl_gemini_licznik', array(
		'dzien'   => $dzis,
		'zapytan' => pnb_pl_gemini_licznik_dzis() + 1,
	), false );
}

/** Czy dzienny limit zapytań wyczerpany? */
function pnb_pl_gemini_limit_rpd_wyczerpany() {
	return pnb_pl_gemini_licznik_dzis() >= pnb_pl_gemini_limit_zapytan();
}

/* ===== THROTTLE (RPM) — 15/min zmierzone; bez tego 16. zapytanie = 429 ===== */

/**
 * Czeka tyle, ile trzeba, żeby nie przekroczyć 15 zapytań/min.
 * Znacznik ostatniego wywołania w opcji (przeżywa między requestami PHP — cron/ajax to osobne procesy).
 * Max czekanie = PNB_PL_GEMINI_ODSTEP_S (4 s) — nie blokuje długo, a rozkłada ruch.
 */
function pnb_pl_gemini_throttle() {
	$ostatnie = (float) get_option( 'pnb_pl_gemini_ostatnie', 0 );
	$teraz    = microtime( true );
	$minelo   = $teraz - $ostatnie;
	if ( $ostatnie > 0 && $minelo < PNB_PL_GEMINI_ODSTEP_S ) {
		$spac = (int) ceil( ( PNB_PL_GEMINI_ODSTEP_S - $minelo ) * 1000000 );
		usleep( min( $spac, PNB_PL_GEMINI_ODSTEP_S * 1000000 ) );
	}
	update_option( 'pnb_pl_gemini_ostatnie', microtime( true ), false );
}

/* ===== WYWOŁANIE API ===== */

/**
 * Wywołanie Gemini generateContent. Zwraca string (tekst odpowiedzi) albo WP_Error.
 * Sygnatura zgodna z pnb_pl_wywolaj_claude() — router woła je wymiennie.
 *
 * @param string $system       instrukcja systemowa (Gemini nie ma osobnego pola → sklejamy z user)
 * @param string $user_content treść do przetłumaczenia
 * @param int    $max_tokens   limit odpowiedzi
 * @return string|WP_Error
 */
function pnb_pl_wywolaj_gemini( $system, $user_content, $max_tokens = 4096 ) {
	$klucz = trim( (string) get_option( 'pnb_auto_pl_gemini_klucz', '' ) );
	if ( '' === $klucz ) {
		return new WP_Error( 'brak_klucza', __( 'No Gemini API key set — add it in settings.', 'pnb-auto-pl' ) );
	}
	if ( pnb_pl_gemini_limit_rpd_wyczerpany() ) {
		return new WP_Error( 'limit', __( 'Daily Gemini request limit reached — the rest will translate tomorrow.', 'pnb-auto-pl' ) );
	}

	$model = (string) get_option( 'pnb_auto_pl_gemini_model', PNB_PL_GEMINI_MODEL_DOMYSLNY );
	$model = preg_replace( '/[^a-z0-9.\-]/i', '', $model ); // nazwa modelu idzie do URL
	if ( '' === $model ) {
		$model = PNB_PL_GEMINI_MODEL_DOMYSLNY;
	}
	$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

	// Gemini nie ma osobnego "system" jak Claude → instrukcja na początku promptu (sprawdzone: działa).
	$prompt = $system . "\n\n" . $user_content;

	$body = array(
		'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
		'generationConfig' => array(
			'temperature'     => 0.3, // niska = przewidywalne tłumaczenie, nie twórczość
			'maxOutputTokens' => (int) $max_tokens,
		),
	);

	pnb_pl_gemini_throttle(); // RPM: nie łomotać (15/min zmierzone)

	$odp = wp_remote_post( $url, array(
		'timeout' => 60, // batch bywa dłuższy; czeka ADMIN z paskiem, nie gość
		'headers' => array(
			'x-goog-api-key' => $klucz, // klucz w nagłówku, NIE w URL (nie ląduje w logach serwera)
			'Content-Type'   => 'application/json',
		),
		'body'    => wp_json_encode( $body ),
	) );

	// ⚠️ Licznik dopiero PO sprawdzeniu błędu sieci (recenzja 2026-07-15): timeout/DNS/WAF znaczy,
	// że zapytanie NIE dotarło do Google — Google go nie policzył, więc my też nie możemy (inaczej
	// awaria sieci zjadałaby dzienny budżet klienta bez jednego przetłumaczonego zdania).
	if ( is_wp_error( $odp ) ) {
		return $odp;
	}
	pnb_pl_gemini_licznik_dodaj(); // odpowiedź przyszła (200 albo błąd API) = Google to policzył
	$kod  = (int) wp_remote_retrieve_response_code( $odp );
	$dane = json_decode( wp_remote_retrieve_body( $odp ), true );

	// 429 = limit Google. To TWARDA prawda (ważniejsza niż nasz licznik — Google zmienia limity bez uprzedzenia).
	if ( 429 === $kod ) {
		// dobij nasz licznik do limitu → reszta cyklu nie łomocze na darmo, dotłumaczy się później
		update_option( 'pnb_pl_gemini_licznik', array(
			'dzien'   => pnb_pl_gemini_dzien_pt(),
			'zapytan' => pnb_pl_gemini_limit_zapytan(),
		), false );
		return new WP_Error( 'limit', __( 'Gemini rate limit reached (429) — the rest will translate later.', 'pnb-auto-pl' ) );
	}
	if ( 200 !== $kod ) {
		$msg = isset( $dane['error']['message'] ) ? $dane['error']['message'] : 'HTTP ' . $kod;
		return new WP_Error( 'api_blad', $msg, array( 'kod' => $kod ) );
	}

	$kand = $dane['candidates'][0] ?? null;
	if ( ! $kand ) {
		return new WP_Error( 'brak_tresci', 'Gemini returned no candidates.' );
	}
	// ucięta odpowiedź = nie ufamy jej (jak przy Claude: JSON ucięty w połowie)
	if ( isset( $kand['finishReason'] ) && ! in_array( $kand['finishReason'], array( 'STOP', 'MAX_TOKENS' ), true ) ) {
		return new WP_Error( 'odrzut', 'Gemini finishReason: ' . $kand['finishReason'] ); // SAFETY/RECITATION itp.
	}
	if ( isset( $kand['finishReason'] ) && 'MAX_TOKENS' === $kand['finishReason'] ) {
		return new WP_Error( 'uciete', 'Response hit maxOutputTokens — batch too big.' );
	}
	// ⚠️ parts niesie też thoughtSignature — szukamy elementu z polem 'text'
	foreach ( (array) ( $kand['content']['parts'] ?? array() ) as $part ) {
		if ( isset( $part['text'] ) && '' !== trim( (string) $part['text'] ) ) {
			return (string) $part['text'];
		}
	}
	return new WP_Error( 'brak_tresci', 'Gemini returned no text.' );
}

/** Test połączenia Gemini (przycisk Test w ustawieniach). */
function pnb_pl_gemini_test_polaczenia() {
	$w = pnb_pl_wywolaj_gemini(
		pnb_pl_system_prompt(),
		'<source>Hello, this is a test.</source>' . "\n\nReply with ONLY the Polish translation.",
		256
	);
	return is_wp_error( $w ) ? $w : true;
}
