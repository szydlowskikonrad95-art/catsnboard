# Instrukcja — wtyczki Paws'N'Board

Cześć! W paczce są **dwie wtyczki** do Twojej strony:

| Plik | Co robi |
|---|---|
| plik zaczynający się od `pnb-blocks` | **Galeria i Wydarzenia** — ładna galeria zdjęć + kalendarz wydarzeń z zapisami gości |
| plik zaczynający się od `pnb-auto-pl` | **Polska Wersja (AI)** — cała strona po polsku z przełącznikiem PL/EN |

---

## 1. Jak zainstalować (raz, 5 minut)

1. Zaloguj się do panelu swojej strony: `twojastrona.com/wp-admin`
2. W menu po lewej: **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**
3. Wybierz plik zaczynający się od `pnb-blocks` → **Zainstaluj** → **Włącz**
4. Powtórz to samo z plikiem zaczynającym się od `pnb-auto-pl`

> ⚠️ **WAŻNE — WPML:** na Twojej stronie jest zainstalowana wtyczka **WPML** (nieużywana).
> **Wyłącz ją** (Wtyczki → WPML → Wyłącz) zanim włączysz Polską Wersję — dwie wtyczki
> językowe na raz mogą się gryźć. Nasza wtyczka i tak robi wszystko czego potrzebujesz.

5. **Dodaj stronę Events do menu strony** — wtyczka tworzy stronę z kalendarzem, ale NIE
   dopisuje się sama do Twojego menu (nie ruszamy Twojej nawigacji). Wejdź w
   **Wygląd → Menu** *(Appearance → Menus)* → zaznacz po lewej stronę **Events** →
   **Dodaj do menu** → przeciągnij gdzie chcesz → **Zapisz menu**. Bez tego goście
   nie znajdą kalendarza z nawigacji (strona działa pod adresem `/events/`).

> 💡 **Chcesz mieć panel WordPressa po polsku?** Wejdź w **Users → Profile → Language →
> Polski** (język ustawiony w Twoim profilu). Wtedy przyciski wtyczek też będą po polsku.
> ⚠️ Ale **NIE zmieniaj „Site Language"** w Settings → General — język całej WITRYNY musi
> zostać **English** (polską wersję dla gości robi nasza wtyczka — tak jest bezpieczniej).
> W instrukcji niżej podajemy nazwy po polsku i angielsku (zależnie od języka panelu).

---

## 2. Galeria — jak zmieniać zdjęcia

1. **Strony → Gallery → Edytuj**
2. Kliknij w galerię — pojawią się przyciski:
   - **„Wybierz zdjęcia"** *(Choose photos)* — zdjęcia głównej taśmy (ta przewijana na górze)
   - **„Wybierz zdjęcia „Moments""** *(Choose "Moments" photos)* — zdjęcia dolnej sekcji (osobny zestaw!)
3. W okienku zaznacz zdjęcia (możesz dodać nowe z komputera) → **Aktualizuj galerię**
4. Kliknij niebieski przycisk **Zapisz/Aktualizuj** w prawym górnym rogu

Nagłówki i podpisy zmieniasz klikając prosto w tekst w podglądzie.

---

## 3. Wydarzenia — jak dodawać i sprawdzać zapisy

**Dodanie wydarzenia:**
1. W menu po lewej: **Events → Add New** (Wydarzenia → Dodaj nowe)
2. Wpisz tytuł i opis (po angielsku — polski zrobi się sam, patrz punkt 4)
3. Po prawej uzupełnij: **datę, godzinę, miejsce, limit miejsc** (0 = bez limitu)
4. **Ustaw zdjęcie wydarzenia** *(Set event photo)* — przycisk w bocznym panelu (nie wklejaj zdjęcia do opisu)
5. **Opublikuj** *(Publish)* — wydarzenie samo pojawi się na stronie Events

**Kto się zapisał:**
- Otwórz wydarzenie do edycji — lista gości jest w ramce **„Zapisani goście"** *(Signed-up guests)* z przyciskiem eksportu do Excela/CSV
- Dodatkowo dostajesz **e-mail** przy każdym zapisie — adres ustawisz w **Events → Ustawienia** *(Settings)*

> ⚠️ **E-maile:** powiadomienia mogą czasem wpadać do SPAMU — tak działają serwery, nie wtyczka.
> Zapisy ZAWSZE są w panelu (nic nie ginie). Jak chcesz pewnych maili, poproś swój hosting
> o skonfigurowanie SMTP albo zainstaluj darmową wtyczkę „WP Mail SMTP".

**Twój adres na mapce:** na dole strony Events jest sekcja „Where to find us" z mapką.
Wpisz tam swój **prawdziwy adres**: edytuj stronę Events → kliknij w blok → na dole podglądu
są dwa pola: *adres* (użyje go przycisk „Get directions" — prowadzi do Google Maps) i *krótka
etykieta przy pinezce*. Dopóki ich nie wypełnisz, adres się nie pokazuje (nic nie zmyślamy).

---

## 4. Polska wersja — jak działa

**Dobra wiadomość:** domyślne teksty strony są **już przetłumaczone** (słownik jedzie
w paczce) — przełącznik **PL | EN** działa od pierwszego dnia, bez żadnej konfiguracji.

> 💡 Jeśli po przełączeniu na PL **jakiś napis został po angielsku** (np. pozycja Twojego
> menu albo tekst, który sam dopisałeś) — to normalne: słownik z paczki zna domyślne
> teksty strony, a Twoje własne dotłumaczy przycisk. Podepnij klucz (kroki niżej)
> i kliknij **„Przetłumacz witrynę"** — wyłapie całą resztę.

**Klucz API** potrzebujesz po to, żeby **Twoje zmiany i nowe treści** tłumaczyły się same
(to „silnik" tłumaczenia — Claude AI; płacisz tylko za faktyczne tłumaczenie, ~grosze):

1. Wejdź na **console.anthropic.com** → załóż konto → w menu **API Keys → Create Key** → skopiuj klucz
2. W panelu strony: **Ustawienia → PNB Auto PL**
3. Wklej klucz → **Zapisz ustawienia** → kliknij **Testuj połączenie** (ma być ✅)
4. Od teraz każda zapisana zmiana tłumaczy się sama; przycisk **„Przetłumacz witrynę
   na polski"** dotłumacza wszystko naraz (pasek pokaże postęp, 1-2 minuty)

**Od teraz działa samo:** gdy zmienisz albo dopiszesz tekst na stronie i klikniesz **Zapisz**,
polska wersja **zaktualizuje się automatycznie** (zapis potrwa 2-3 sekundy dłużej — to tłumaczenie).
Żeby zobaczyć efekt na stronie — odśwież ją (F5).

**Bezpiecznik kosztów:** wtyczka ma dzienny limit znaków (ustawiony z zapasem). Nawet gdyby coś
poszło nie tak, nie wydasz więcej niż kilka złotych dziennie. Licznik zużycia widzisz w ustawieniach.

---

## 5. Twoje dane kontaktowe na stronie

Telefon, e-mail i adres w **nagłówku, stopce i na stronach Contact / Our Location** ustawiasz
w jednym miejscu: **Wygląd → Dostosuj → „Dane kontaktowe"** *(Appearance → Customize)*.
Są tam 4 pola: telefon, e-mail, adres i krótka etykieta przy mapce (strona Our Location).
Wpisz swoje prawdziwe dane → **Opublikuj**.

> Dopóki pole jest puste, ta informacja **w ogóle nie pokazuje się** na stronie (nic nie zmyślamy).
> Strony Contact i Our Location zrobione na blokach mają też własne pola w edytorze — wtedy
> na tej konkretnej stronie liczy się to, co wpiszesz w bloku.

---

## 6. Dobre rady i znane sprawy

- **Cache (LiteSpeed):** masz na hostingu wtyczkę przyspieszającą. Po dużym tłumaczeniu warto
  kliknąć w niej „Purge All" (wyczyść cache), żeby goście od razu widzieli świeżą wersję.
- **Cofnij (Ctrl+Z) w edytorze** bywa kapryśne przy blokach — to znana przypadłość samego
  WordPressa, nie wtyczek. Jak coś pójdzie nie tak: nie zapisuj, tylko odśwież stronę edytora.
- **Zdjęcia:** najlepiej wgrywać zdjęcia do ~2500px szerokości (wtyczka sama robi miniatury).
- Wtyczek **nie trzeba aktualizować** — nie pobierają nic z internetu (poza tłumaczeniem u Claude).

## Coś nie działa?

Napisz do nas — opisz co klikasz i co się dzieje (najlepiej ze zrzutem ekranu). Zapisy gości
i tłumaczenia są bezpieczne w bazie — nawet jak coś wygląda dziwnie, nic nie ginie.
