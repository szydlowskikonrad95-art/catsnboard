# Historia zmian

## v2.0.2 — 7 lipca 2026

Języki wtyczek po standardzie WordPressa (działanie strony bez zmian):

- Wtyczki mówią teraz językiem PANELU: panel po angielsku → wtyczki po angielsku,
  panel po polsku (Users → Profile → Language → Polski) → wtyczki po polsku.
- Wcześniej nazwy, opisy i ekran tłumaczenia były po polsku na stałe — nawet
  w angielskim panelu (niespójność wychwycona przy testach).
- Polskie teksty siedzą teraz w plikach tłumaczeń (languages/*.mo), nie w kodzie.

Wersje: pnb-blocks **1.4.4** · pnb-auto-pl **0.3.3** · motyw **1.1.7** (bez zmian).

## v2.0.1 — 6 lipca 2026

Poprawka porządkowa — nic nie zmienia w działaniu strony:

- Dołączone pełne teksty licencji (plik `LICENSE`) do obu wtyczek oraz spis bibliotek
  zewnętrznych z ich licencjami (`CREDITS.md`) do wtyczki galerii i wydarzeń.
- Uzupełnione nagłówki wtyczek i motywu (informacje o licencji i wymaganiach).
- Instrukcja i opisy nie wskazują już konkretnych numerów wersji plików — zawsze aktualne.
- Motyw wyraźnie oznaczony jako **testowy** (poligon do sprawdzania wtyczek) — nie do
  wgrania na prawdziwą stronę.
- Z paczki motywu usunięte pliki deweloperskie (niepotrzebne do działania).

Wersje: pnb-blocks **1.4.3** · pnb-auto-pl **0.3.2** · motyw **1.1.7**.

## v2.0.0 — 5 lipca 2026

Pierwsze wydanie paczki: wtyczki + motyw dla strony Cats'N'Board.

**Wtyczka „PNB Galeria i Wydarzenia" (1.4.2)**
- Galeria premium (taśma kinowa + osobna sekcja „Moments") i kalendarz wydarzeń z zapisami
  gości jako bloki Gutenberga: treść edytujesz klikając prosto w podgląd.
- Galeria startuje z 12 kotami demo jako normalnymi zdjęciami w bibliotece mediów — od razu edytujesz kafelki w edytorze (przestawiasz, kasujesz, dodajesz swoje).
- Przy pierwszym włączeniu na pustym kalendarzu powstają 3 przykładowe wydarzenia ze zdjęciami
  (edytujesz/usuwasz jak swoje); hero kalendarza samo bierze zdjęcie z pierwszego wydarzenia.
- Zapisy gości widoczne przy wydarzeniu + powiadomienie e-mail + eksport do CSV.
- Adres na mapce edytowalny wprost w bloku.

**Wtyczka „PNB Polska Wersja (AI)" (0.3.1)**
- Słownik startowy w paczce: domyślne teksty strony są przetłumaczone od pierwszej minuty,
  bez klucza API. Klucz (Claude AI) potrzebny dopiero do tłumaczenia nowych/zmienionych treści.
- Każda zapisana zmiana treści dotłumacza się automatycznie; przełącznik PL/EN trzyma język na wszystkich podstronach i pokazuje się
  tylko wtedy, gdy są tłumaczenia.
- Bezpiecznik dziennego kosztu tłumaczeń.

**Motyw Cats'N'Board (1.1.6)**
- Wygląd strony: hero z animacją, sekcje usług, galeria, płynne przewijanie,
  osobne podstrony (usługi, cennik, zespół, lokalizacja, kontakt).
- Przy włączeniu motyw sam tworzy komplet podstron z edytowalnymi blokami,
  ustawia stronę główną i ładne adresy (istniejących stron nie nadpisuje).
- Dane kontaktowe (telefon, e-mail, adres) ustawiane w jednym miejscu:
  Wygląd → Dostosuj → „Dane kontaktowe". Puste pola nie pokazują się na stronie.
- Fonty i biblioteki w paczce — strona działa bez połączeń do zewnętrznych serwerów.

**Dla klienta:** instrukcja obsługi krok po kroku w `INSTRUKCJA-DLA-KLIENTA.md`
(z ostrzeżeniami: WPML, e-maile/SPAM, cache).
