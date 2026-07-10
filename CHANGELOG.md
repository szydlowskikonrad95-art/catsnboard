# Historia zmian

## v2.2.3 — 10 lipca 2026

Wydanie zbiorcze po całonocnych testach od zera (dwie świeże instalacje: same wtyczki
na obcym motywie oraz motyw + wtyczki) i przeglądzie porządkowym repozytorium:

Naprawione:

- **Licznik tłumaczeń mówi prawdę** — panel pokazuje realne zaległości („do przetłumaczenia: N")
  i licznik spada do zera; wcześniej straszył rozmiarem całej witryny i nigdy nie malał.
- **Strona pojedynczego wydarzenia na motywie klienta** — bez zdublowanego tytułu, drugiego
  zdjęcia i pustego podpisu autora; sekcja zajmuje pełną szerokość ekranu (wcześniej wąska
  kolumna „jak telefon na komputerze").
- **Tytuł wydarzenia po polsku w całości** — tłumaczenie całej frazy przed animacją słów
  (koniec wpadek typu pojedyncze słowo przetłumaczone bez kontekstu).

Zmienione:

- **„Szczegóły wydarzenia" prowadzi wprost na podstronę wydarzenia** (zamiast rozsuwanego
  panelu w karcie) — czytelniej i wygodniej na telefonie.
- Porządek w metadanych paczki (autor: PNB) i komentarzach kodu.

Wersje: pnb-blocks **1.10.40** · pnb-auto-pl **0.3.12** · motyw **1.1.13**.

## v2.2.2 — 10 lipca 2026

Poprawka niezawodności zdjęć wydarzeń (znaleziona nocnym testem od zera na czystym WordPressie):

- Wydarzenie, któremu nie udało się pobrać zdjęcia (chwilowy problem sieci/serwera), dostaje je
  teraz **automatycznie w kolejnych cyklach importu** (po kilka na cykl, aż do kompletu) —
  wcześniej zostawało bez zdjęcia na zawsze.
- Nieudane pobrania zdjęć są **widoczne w dzienniku importu** z podpowiedzią przyczyny
  (wcześniej znikały bez śladu).
- Po dociągnięciu zdjęć polska wersja strony odświeża się sama (goście nie oglądają starych ikonek).

Wersje: pnb-blocks **1.10.36** · pnb-auto-pl **0.3.10** · motyw **1.1.12**.

## v2.2.1 — 10 lipca 2026

Poprawka po debug-przeglądzie całego produktu (każda warstwa sprawdzona narzędziami):

- **Tytuł karty przeglądarki po polsku** — w trybie PL zakładka pokazuje teraz „Galeria",
  „Wydarzenia", „Joga z kotami" itd. (wcześniej zostawała angielska, mimo polskiej strony).
- **Tytuł przy udostępnianiu linku** (Facebook/Google) też po polsku — spójny z polskim opisem.
- Polska wersja strony odświeża się sama po aktualizacji wtyczki (stary wygląd nie zalega w pamięci).

Wersje: pnb-blocks **1.10.35** · pnb-auto-pl **0.3.10** · motyw **1.1.12**.

## v2.2.0 — 9 lipca 2026

Duże dopieszczenie: pełne tłumaczenie, szybsza strona, wygodniejszy kalendarz.

**Polska wersja — teraz kompletna:**
- Całe menu, treści, galeria i wydarzenia po polsku (także opisy pobieranych wydarzeń).
- Nowe i zmienione wydarzenia (również demo i te z importu) tłumaczą się same.
- Opis strony dla Google i Facebooka (przy udostępnianiu linku) też jest po polsku.

**Szybsza strona:**
- Galeria nie zacina się już przy wchodzeniu — zdjęcia i animacje zoptymalizowane.
- Strona z wydarzeniami po polsku ładuje się błyskawicznie (wcześniej ~1 s, teraz ~0,01 s
  dzięki zapamiętywaniu przetłumaczonej wersji; formularze zapisu działają bez zmian).

**Kalendarz wydarzeń:**
- Przy wielu wydarzeniach są numerki stron (1, 2, 3). Po odświeżeniu strony zostajesz na tej
  samej stronie kalendarza (wcześniej wracało na początek).
- Importer nie tworzy już podwójnych wydarzeń, nawet gdy sprawdzanie źródła nałoży się w czasie.
- Import szanuje Twoje decyzje: skasowane ręcznie wydarzenie nie wraca przy następnym sprawdzeniu.
- Pobierane wydarzenia dostają zdjęcia i pełny polski opis.

Wersje: pnb-blocks **1.10.35** · pnb-auto-pl **0.3.9** · motyw **1.1.12**.



## v2.1.0 — 8 lipca 2026

Automatyczny import wydarzeń — nowa duża funkcja (wtyczka sama dodaje wydarzenia ze źródła):

- **Wtyczka sama pobiera wydarzenia** z zewnętrznego źródła (Eventbrite) i dodaje je na
  stronę — bez ręcznej roboty. Wpisujesz adres źródła w panelu (Wydarzenia → Ustawienia)
  i wtyczka sprawdza co jakiś czas, dorzucając nowe.
- **Wszystko w jednej wtyczce** — nie trzeba osobnego programu ani serwera. Import chodzi
  wewnątrz WordPressa (przez wbudowany harmonogram WP-Cron).
- **Odporny na awarie** — gdy źródło chwilowo padnie albo zwróci błąd, wtyczka spokojnie
  czeka i próbuje później, nie psując tego co już jest na stronie.
- **Chroni Twoje ręczne zmiany** — jeśli sam poprawisz zaimportowane wydarzenie, wtyczka
  tego nie nadpisze przy następnym sprawdzeniu.
- **Sprząta po sobie** — wydarzenia które znikną ze źródła trafiają do kosza; zdjęcia
  usuniętych wydarzeń też są czyszczone.
- Ekran stanu w panelu pokazuje kiedy był ostatni import i czy wszystko działa.

⚠️ Uwaga o źródle: upewnij się, że masz prawo korzystać z danych źródła zgodnie z jego
regulaminem. Dla wydarzeń których jesteś organizatorem zalecane jest oficjalne API serwisu.

Wersje: pnb-blocks **1.9.2** · pnb-auto-pl **0.3.5** · motyw **1.1.7**.


## v2.0.3 — 8 lipca 2026

Ulepszenia po testach (działanie tylko lepsze):

- **Płynne przewijanie galerii i kalendarza działa teraz zawsze** — także na Twoim
  motywie, nie tylko na naszym testowym (płynność wbudowana w samą wtyczkę).
- **Galeria i wydarzenia mają komplet zdjęć demo od razu** — nawet zanim wgrasz własne
  (zdjęcia jadą w paczce wtyczki).
- Drobne poprawki polskich napisów (menu, przyciski, komunikaty) i porządki.

Wersje: pnb-blocks **1.5.2** · pnb-auto-pl **0.3.5** · motyw **1.1.7**.

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
