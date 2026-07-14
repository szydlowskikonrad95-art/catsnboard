# Historia zmian

Format wg [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersjonowanie [SemVer](https://semver.org/lang/pl/).
Opisowe wersje wydań (dla klienta) znajdują się w [GitHub Releases](../../releases).

## [Unreleased]

### Dodane
- Kopia pełnego tekstu licencji GPL-2.0 w głównym folderze repozytorium (`LICENSE`) —
  GitHub pokazuje teraz licencję na stronie repo. Zawartość paczki i wersje wtyczek bez zmian.

### Zmienione
- Neutralna etykieta harmonogramu importera w narzędziach WordPressa: „Every 10 minutes
  (PNB importer)" — bez nazwy konkretnej strony (kosmetyka, nie zmienia działania).

### Naprawione
- Blokada „jeden cykl importu naraz" jest teraz naprawdę atomowa (wzorzec z rdzenia
  WordPressa — INSERT IGNORE): równoczesny cron i przycisk „Sync now" nie mogą już wejść
  w import podwójnie i tworzyć duplikatów wydarzeń. Dowód: test wyścigu 15 rund × 2
  równoległe procesy — za każdym razem dokładnie jeden zwycięzca.
- Odinstalowanie sprząta teraz także to, co wtyczka sama stworzyła przy aktywacji —
  podstrony Events/Gallery i 3 przykładowe wydarzenia — ale **wyłącznie w stanie
  nietkniętym** (jakakolwiek edycja klienta = treść zostaje). Wydarzenia własne
  i zaimportowane oraz zdjęcia w mediach — bez zmian, zostają. CI dodatkowo sprawdza
  teraz składnię na PHP 7.4 oraz 8.2–8.5 (matrix).

## [2.3.4] — 2026-07-10

### Naprawione
- Odinstalowanie sprząta komplet opcji: `pnb_importer_lock` (pnb-blocks) oraz liczniki
  wersji cache `pnb_pl_cache_wersja` i `pnb_pl_cache_kod_wersja` (pnb-auto-pl) —
  znalezione ostatnim audytem repozytorium.

### Zmienione
- Dokumentacja opisuje paczkę zgodnie z jej faktyczną zawartością: paczka klienta =
  2 wtyczki + instrukcja (MD i PDF) + zrzuty; motyw testowy żyje tylko w repozytorium
  (wcześniej README i instrukcja twierdziły, że motyw jedzie w paczce).
- Instrukcja klienta podaje etykiety pól po polsku i angielsku („E-mail powiadomień" /
  „Zapisz ustawienia") — zależnie od języka panelu klient zobaczy jedną z nich.
- Z tabeli hooków w dokumentacji technicznej usunięty martwy wpis AJAX `pnb_galeria_zapisz`
  (endpoint usunięty z kodu już w v1.0.6).
- Stare wydania (v2.0.3–v2.2.2) na GitHubie: paczki oczyszczone z wewnętrznej notki
  recenzenckiej, ujednolicone nazwy załączników, opisy wydań bez wewnętrznego żargonu.

Wersje: pnb-blocks 1.11.2 · pnb-auto-pl 0.3.14 · motyw 1.1.13

## [2.3.3] — 2026-07-10

### Dodane
- PDF instrukcji klienta w paczce (ta sama treść co .md, zrzuty wklejone w dokument —
  wygodny do czytania i druku dla osoby nietechnicznej).
- Powtarzalny test trybów awarii importera (`testy/awarie/`): lock, circuit breaker
  z eskalacją, puste źródło ×3, podejrzany spadek, dead letter — 5/5 potwierdzone
  symulacją na żywym WordPressie.
- Dokument `dokumentacja-techniczna/AKTUALIZACJE.md`: jak wydajemy i dostarczamy nowe
  wersje, rollback, świadoma decyzja o braku auto-aktualizacji (prywatne repo), plan na skalę.

Wersje: pnb-blocks 1.11.1 · pnb-auto-pl 0.3.13 · motyw 1.1.13 (kod wtyczek bez zmian)

## [2.3.2] — 2026-07-10

### Zmienione
- Instrukcja klienta mówi uczciwie: jak wgrywać nowe wersje wtyczek (zamiast obietnicy
  „nie trzeba aktualizować"), co dokładnie jest po polsku od razu (treści od wtyczek —
  własne strony klienta tłumaczy KROK 5), ostrzeżenie Wyłączenie ≠ Usunięcie (eksport
  gości przed Delete), wiersz FAQ o tłumaczeniu zatrzymanym przez dzienny limit kosztów.

Wersje: pnb-blocks 1.11.1 · pnb-auto-pl 0.3.13 · motyw 1.1.13 (bez zmian — wydanie dokumentacyjne)

## [2.3.1] — 2026-07-10

### Naprawione
- Słownik startowy zawierał adresy środowiska deweloperskiego — zastąpione neutralną domeną
  (mechanizm zasiewu bez zmian: przy aktywacji przepisuje domenę na adres strony klienta).
- Odinstalowanie pnb-blocks nie usuwało części opcji importera (log, breaker, źródło i in.) —
  lista sprzątania uzupełniona.
- Eksport RODO: dodane stronicowanie wyników (kontrakt WP Privacy), dopasowanie e-maila
  niezależne od ustawień bazy (normalizacja do małych liter), myślnik zamiast pustego pola
  przy trwale usuniętym wydarzeniu.
- Preload fontów kalendarza czytał niewłaściwą nazwę opcji strony wydarzeń.

### Zmienione
- README i dokumentacja techniczna uzupełnione o funkcję RODO; nazwy przycisków w instrukcji
  zgodne 1:1 z interfejsem; instrukcja klienta opisuje obsługę żądań RODO gościa.

Wersje: pnb-blocks 1.11.1 · pnb-auto-pl 0.3.13 · motyw 1.1.13

## [2.3.0] — 2026-07-10

### Dodane
- Zapisy gości wpięte w natywne narzędzia prywatności WordPressa (Narzędzia → Eksport / Usuwanie
  danych osobowych) — obsługa żądań RODO po adresie e-mail, przekrojowo przez wszystkie wydarzenia.

Wersje: pnb-blocks 1.11.0 · pnb-auto-pl 0.3.12 · motyw 1.1.13

## [2.2.3] — 2026-07-10

### Naprawione
- Licznik oczekujących tłumaczeń pokazywał całkowitą liczbę stron zamiast realnych zaległości.
- Strona pojedynczego wydarzenia na obcym motywie: zdublowany tytuł, drugie zdjęcie i pusty podpis autora; sekcja renderowała się w wąskiej kolumnie motywu zamiast na pełną szerokość.
- Tytuł wydarzenia tłumaczony słowo-po-słowie zamiast całą frazą.

### Zmienione
- „Szczegóły wydarzenia" prowadzą wprost na podstronę wydarzenia (zamiast rozsuwanego panelu w karcie).

- Ujednolicone metadane paczki (autor: PNB) i komentarze kodu.

Wersje: pnb-blocks 1.10.40 · pnb-auto-pl 0.3.12 · motyw 1.1.13

## [2.2.2] — 2026-07-10

### Naprawione
- Wydarzenie bez pobranego zdjęcia (chwilowy błąd sieci) pozostawało bez zdjęcia na stałe — teraz importer dociąga brakujące zdjęcia w kolejnych cyklach.
- Nieudane pobrania zdjęć nie zostawiały śladu — teraz są logowane w dzienniku importu z przyczyną.
- Po dociągnięciu zdjęć cache polskiej wersji nie odświeżał się (goście widzieli stare miniatury).

Wersje: pnb-blocks 1.10.36 · pnb-auto-pl 0.3.10 · motyw 1.1.12

## [2.2.1] — 2026-07-10

### Naprawione
- `<title>` i `og:title` pozostawały po angielsku w trybie PL (zakładka przeglądarki i podgląd linku FB/Google).
- Cache polskiej wersji nie unieważniał się po aktualizacji wtyczki (stary HTML zalegał do zmiany słownika).

Wersje: pnb-blocks 1.10.35 · pnb-auto-pl 0.3.10 · motyw 1.1.12

## [2.2.0] — 2026-07-09

### Dodane
- Tłumaczenie PL objęło menu, treści, galerię i wydarzenia oraz meta (`og:*`) stron i wydarzeń.
- Paginacja kalendarza z zapamiętaniem strony po odświeżeniu (hash `#evN`).

### Zmienione
- Zoptymalizowana galeria (decoding async, transform zamiast clipPath).
- Cache przetłumaczonego HTML — skrócony czas generowania strony PL.

### Naprawione
- Importer tworzył duplikaty przy nakładających się cyklach (dodany lock).
- Ręcznie skasowane wydarzenie wracało przy następnym imporcie.

Wersje: pnb-blocks 1.10.35 · pnb-auto-pl 0.3.9 · motyw 1.1.12

## [2.1.0] — 2026-07-08

### Dodane
- Automatyczny importer wydarzeń z Eventbrite (adres źródła w Wydarzenia → Ustawienia).
- Import w obrębie WordPressa przez WP-Cron — bez osobnego programu ani serwera.
- Circuit breaker na awarie źródła (ponawianie z rosnącym odstępem, nie kasuje istniejących danych).
- Ochrona ręcznych zmian: zaimportowane wydarzenie poprawione ręcznie nie jest nadpisywane (`_pnb_locked`).
- Sprzątanie wygasłych wydarzeń (do kosza) wraz z ich zdjęciami.
- Ekran stanu importu w panelu (ostatni sync, metryki).

> Uwaga: korzystanie z danych źródła podlega jego regulaminowi; dla własnych wydarzeń zalecane oficjalne API.

Wersje: pnb-blocks 1.9.2 · pnb-auto-pl 0.3.5 · motyw 1.1.7

## [2.0.3] — 2026-07-08

### Naprawione
- Płynne przewijanie (Lenis) działa na dowolnym motywie, nie tylko testowym (wbudowane w wtyczkę).

### Dodane
- Komplet zdjęć demo galerii i wydarzeń w paczce wtyczki.

### Zmienione
- Poprawki polskich napisów (menu, przyciski, komunikaty).

Wersje: pnb-blocks 1.5.2 · pnb-auto-pl 0.3.5 · motyw 1.1.7

## [2.0.2] — 2026-07-07

### Zmienione
- Wtyczki respektują język panelu (i18n przez `.mo`), zamiast polskiego na stałe.

### Naprawione
- Nazwy, opisy i ekran tłumaczenia były po polsku nawet w angielskim panelu.

Wersje: pnb-blocks 1.4.4 · pnb-auto-pl 0.3.3 · motyw 1.1.7

## [2.0.1] — 2026-07-06

### Dodane
- Pełne teksty licencji (`LICENSE`) w obu wtyczkach + `CREDITS.md` (biblioteki zewnętrzne) w pnb-blocks.

### Zmienione
- Uzupełnione nagłówki wtyczek i motywu (licencja, wymagania).
- Dokumentacja nie zawiera zaszytych numerów wersji plików.
- Motyw oznaczony jako testowy (nie do produkcji).

### Usunięte
- Pliki deweloperskie z paczki motywu.

Wersje: pnb-blocks 1.4.3 · pnb-auto-pl 0.3.2 · motyw 1.1.7

## [2.0.0] — 2026-07-05

Pierwsze wydanie: 2 wtyczki + motyw testowy.

### Dodane — PNB Galeria i Wydarzenia (1.4.2)
- Galeria premium (taśma + sekcja „Moments") i kalendarz wydarzeń z zapisami gości jako bloki Gutenberga.
- Samozasiew przy aktywacji: 12 zdjęć demo w bibliotece mediów + 3 przykładowe wydarzenia.
- Zapisy gości przy wydarzeniu, powiadomienie e-mail, eksport CSV.

### Dodane — PNB Polska Wersja AI (0.3.1)
- Słownik startowy w paczce (strona po polsku bez klucza API); klucz Claude potrzebny do tłumaczenia nowych treści.
- Auto-tłumaczenie zmian treści po zapisie; przełącznik PL/EN na wszystkich podstronach.
- Dzienny limit kosztu tłumaczeń.

### Dodane — Motyw Cats'N'Board (1.1.6, testowy)
- Wygląd strony (hero, sekcje, podstrony), samozasiew podstron przy aktywacji.
- Fonty i biblioteki lokalnie (bez zewnętrznych połączeń).

Wersje: pnb-blocks 1.4.2 · pnb-auto-pl 0.3.1 · motyw 1.1.6
