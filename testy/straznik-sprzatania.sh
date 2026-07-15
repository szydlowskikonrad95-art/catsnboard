#!/usr/bin/env bash
#
# 🛡️ STRAŻNIK SPRZĄTANIA — pilnuje dwóch błędów z recenzji 2026-07-15, żeby nie wróciły.
#
# PO CO TO ISTNIEJE (przeczytaj, zanim to wyłączysz):
#
# BŁĄD 1 — uninstall nie nadążał za kodem.
#   Silnik Gemini (v2.4.0) dodał 6 opcji, w tym KLUCZ API klienta. uninstall.php nie znał ŻADNEJ,
#   więc po usunięciu wtyczki sekret klienta zostawał w jego bazie na zawsze. Recenzent wyłapał to
#   czytaniem kodu. Bramką była wtedy notka w komentarzu („dodajesz opcję → dopisz ją tutaj"),
#   czyli LUDZKA PAMIĘĆ. Pamięć zawiodła — dlatego teraz pilnuje tego automat.
#
# BŁĄD 2 — LIKE z gołym wzorcem kasował CUDZE dane.
#   Było: LIKE '_transient_pnb_%'. W SQL podkreślnik to WILDCARD („dowolny znak"), nie zwykły znak,
#   więc wzorzec łapał szerzej niż nasze wpisy. Zmierzone na żywej bazie: stary wzorzec skasował
#   4 transienty innej wtyczki. Poprawny sposób to wzorzec z rdzenia WP: esc_like() + prepare(%s).
#
# ZASADA WŁASNOŚCI (tak rozstrzygamy, czyja jest opcja):
#   Wtyczka, która opcję ZAPISUJE (add_option / update_option / register_setting) — jest jej
#   właścicielem i MUSI ją skasować w swoim uninstall.php.
#   Sam ODCZYT (get_option) własności NIE daje. Dlatego pnb-blocks może czytać 'pnb_auto_pl_klucz'
#   bez obowiązku kasowania — właścicielem jest pnb-auto-pl i on go sprząta u siebie.
#
# JAK SPRAWDZIĆ, ŻE TEN STRAŻNIK DZIAŁA (rób to po każdej zmianie w nim!):
#   Dopisz gdziekolwiek w kodzie wtyczki:  update_option( 'pnb_test_dziura', 1 );
#   → skrypt MUSI zwrócić błąd. Jak nie zwrócił — strażnik jest ślepy i nic nie pilnuje.

set -uo pipefail
cd "$( dirname "$0" )/.." || exit 1
BLAD=0

echo "🛡️  STRAŻNIK SPRZĄTANIA"
echo

# ─────────────────────────────────────────────────────────────────────────────
# 1. Każda ZAPISYWANA opcja musi być kasowana w uninstall.php tej samej wtyczki
# ─────────────────────────────────────────────────────────────────────────────
for W in pnb-blocks pnb-auto-pl; do
	UN="$W/uninstall.php"
	if [ ! -f "$UN" ]; then
		echo "❌ $W: BRAK pliku uninstall.php — wtyczka nie sprząta po sobie."
		BLAD=1
		continue
	fi

	# opcje ZAPISYWANE (własność) — pomijamy sam uninstall.php (tam są delete_option, nie zapisy)
	#
	# ⚠️ register_setting( 'grupa', 'opcja' ) — bierzemy TYLKO 2. argument. Pierwszy to nazwa grupy
	# Settings API, która NIE jest opcją i nie istnieje w bazie (sprawdzone: 'pnb_events_settings'
	# → get_option zwraca BRAK). Pierwsza wersja tego strażnika liczyła grupę jako opcję i krzyczała
	# fałszywym alarmem — a strażnik, który kłamie, zostaje wyłączony po tygodniu i przestaje chronić.
	ZAPIS=$(
		{
			grep -rhoE "(add_option|update_option)\( *'[a-z0-9_]*pnb[a-z0-9_]*'" "$W" \
				--include='*.php' --exclude='uninstall.php' 2>/dev/null \
				| grep -oE "'[a-z0-9_]*pnb[a-z0-9_]*'" | tr -d "'"

			grep -rhoE "register_setting\( *'[^']+' *, *'[^']+'" "$W" \
				--include='*.php' --exclude='uninstall.php' 2>/dev/null \
				| sed -E "s/.*register_setting\( *'[^']+' *, *'([^']+)'.*/\1/"
		} | grep -E "pnb" | sort -u
	)

	# opcje KASOWANE w uninstall.php
	KASUJE=$( grep -oE "'[a-z0-9_]*pnb[a-z0-9_]*'" "$UN" 2>/dev/null | tr -d "'" | sort -u )

	BRAKI=$( comm -23 <( echo "$ZAPIS" ) <( echo "$KASUJE" ) )

	if [ -n "$BRAKI" ]; then
		echo "❌ $W — te opcje wtyczka ZAPISUJE, ale uninstall.php ich NIE KASUJE:"
		echo "$BRAKI" | sed 's/^/      /'
		echo "      → dopisz je do listy w $UN (jeśli któraś trzyma SEKRET, to wyciek klucza klienta)"
		BLAD=1
	else
		ILE=$( echo "$ZAPIS" | grep -c . )
		echo "✅ $W — uninstall zna wszystkie $ILE zapisywanych opcji"
	fi
done

echo

# ─────────────────────────────────────────────────────────────────────────────
# 2. Żadnego LIKE z gołym wzorcem — wzorce tylko przez esc_like() + prepare(%s)
# ─────────────────────────────────────────────────────────────────────────────
# Pomijamy KOMENTARZE (` * `, `//`, `#`) — opisujemy w nich stary błąd, żeby nie wrócił,
# i strażnik nie może się na tym opisie wywracać. Liczy się tylko realny kod.
GOLE=$( grep -rnE "LIKE +['\"]" pnb-blocks pnb-auto-pl --include='*.php' 2>/dev/null \
	| grep -vE ":[0-9]+:[[:space:]]*(\*|//|#|/\*)" )
if [ -n "$GOLE" ]; then
	echo "❌ SQL: LIKE z gołym wzorcem (podkreślnik zadziała jak wildcard → możesz skasować CUDZE dane):"
	echo "$GOLE" | sed 's/^/      /'
	echo "      → zrób jak rdzeń WP: \$wpdb->prepare( \"... LIKE %s\", \$wpdb->esc_like( 'przedrostek' ) . '%' )"
	BLAD=1
else
	echo "✅ SQL: zero LIKE z gołym wzorcem (wzorce idą przez esc_like + prepare)"
fi

echo
if [ "$BLAD" = "0" ]; then
	echo "🎯 STRAŻNIK: czysto — wtyczki sprzątają po sobie i nie tykają cudzych danych."
else
	echo "🔴 STRAŻNIK: znalazł braki (wyżej). To NIE jest formalność — dokładnie na tym"
	echo "   złapał nas recenzent 2026-07-15 (klucz API klienta zostawał w bazie)."
fi
exit $BLAD
