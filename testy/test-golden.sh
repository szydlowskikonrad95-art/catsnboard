#!/usr/bin/env bash
# GOLDEN TEST produktu catsnboard — porównuje zachowanie strony z zapisanym WZORCEM (golden data).
#
# Metoda (małe kroki + golden data): trzymamy zapisany znany-dobry wynik. Po KAŻDEJ zmianie
# odpalamy test — jak wynik równa się wzorcowi, nic się nie zepsuło; jak się różni, to albo
# regresja (naprawiamy), albo świadoma zmiana (po weryfikacji nadpisujemy wzorzec).
#
# Użycie:  bash testy/test-golden.sh [adres] [plik-wzorca]   (domyślnie http://localhost:8212)
#   1. uruchomienie → zapisuje wzorzec do testy/golden/golden-dane.txt
#   kolejne         → ✅ PASS gdy zgodne, ❌ FAIL + diff gdy coś się zmieniło
#
# W goldenie tylko fakty STABILNE (kody stron, tytuły, obecność mechanizmów) — bez liczb
# wydarzeń/dat, które zmieniają się z każdym importem (te sprawdza się osobno, nie wzorcem).
#
# DWA WZORCE, DWA ŚWIATY (rozdzielone 2026-07-15, przy wpinaniu testu do CI):
#  • golden-dane.txt — KLON KLIENTA: pełny zestaw stron (/services/, /pricing/, /contact/…).
#    To jego treść, nie nasz produkt. Odpalasz ręcznie na klonie: bash testy/test-golden.sh
#  • golden-ci.txt   — GOŁY WORDPRESS + nasze 2 wtyczki: tylko to, co gwarantuje PRODUKT
#    (strony Events/Gallery tworzy aktywacja, przełącznik PL, nonce, lightbox, meta).
#    Odpala CI przy każdym PR. Strony klienta dalyby tu 404 → stad osobna lista.
# Liste stron podmienia sie zmienna PNB_STRONY, wzorzec — drugim argumentem. Domyslne
# zachowanie BEZ argumentow zostaje takie samo jak dotad (zgodnosc wstecz).
set -u
BASE="${1:-http://localhost:8212}"
TU="$(cd "$(dirname "$0")" && pwd)"
GOLD="${2:-$TU/golden/golden-dane.txt}"
STRONY="${PNB_STRONY:-/ /events/ /gallery/ /services/ /pricing/ /contact/ /our-staff/ /our-location/ /about-us/}"
WYNIK="$(mktemp)"

pomiar() {
	echo "== KODY HTTP (strona → kod, EN i PL) =="
	for u in $STRONY; do
		for l in "" "?lang=pl"; do
			printf '%s%s %s\n' "$u" "$l" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE$u$l")"
		done
	done
	printf '404-test %s\n' "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/strona-ktorej-nie-ma/")"

	echo "== TYTULY KART PRZEGLADARKI (PL musi byc po polsku) =="
	for u in /gallery/ /events/; do
		printf 'PL %s %s\n' "$u" "$(curl -s "$BASE$u?lang=pl" | grep -oE '<title>[^<]*</title>')"
		printf 'EN %s %s\n' "$u" "$(curl -s "$BASE$u" | grep -oE '<title>[^<]*</title>')"
	done

	echo "== MECHANIZMY (obecnosc w HTML) =="
	printf 'formularz-zapisu-nonce %s\n' "$(curl -s "$BASE/events/?lang=pl" | grep -c 'name="pnb_nonce"' | awk '{print ($1>0)?"JEST":"BRAK"}')"
	printf 'lightbox-markup %s\n' "$(curl -s "$BASE/gallery/" | grep -c 'id="pnbLb"' | awk '{print ($1>0)?"JEST":"BRAK"}')"
	printf 'przelacznik-jezyka %s\n' "$(curl -s "$BASE/" | grep -c 'lang=pl' | awk '{print ($1>0)?"JEST":"BRAK"}')"
	printf 'meta-description %s\n' "$(curl -s "$BASE/gallery/?lang=pl" | grep -c 'name="description"' | awk '{print ($1>0)?"JEST":"BRAK"}')"
}

pomiar > "$WYNIK"

if [ ! -f "$GOLD" ]; then
	mkdir -p "$TU/golden"
	cp "$WYNIK" "$GOLD"
	echo "📀 GOLDEN zapisany pierwszy raz → $GOLD"
	echo "   (od teraz każde uruchomienie porównuje z tym wzorcem)"
	# Wypisujemy treść, bo w CI plik ginie razem z maszyną — a wzorzec MUSI powstać
	# w tym samym środowisku, w którym potem będzie porównywany (inaczej fałszywe czerwone).
	echo "──────── TREŚĆ WZORCA (skopiuj do repo, jeśli powstał w CI) ────────"
	cat "$GOLD"
	echo "───────────────────────────────────────────────────────────────────"
	exit 0
fi

if diff -u "$GOLD" "$WYNIK"; then
	echo "✅ PASS — zachowanie strony zgodne z goldenem"
else
	echo "❌ FAIL — różnica vs golden: regresja ALBO świadoma zmiana."
	echo "   Po weryfikacji że zmiana jest OK: skasuj $GOLD i odpal test ponownie (zapisze nowy wzorzec)."
	exit 1
fi
