# Instrukcja obsługi — strona Cats'N'Board

Cześć! 👋 Ta instrukcja tłumaczy **prostym językiem** jak obsługiwać Twoją stronę.
Nie musisz się znać na komputerach — każdy krok jest opisany po kolei.

W paczce dostałeś **3 rzeczy do wgrania**:

| Co | Do czego służy |
|---|---|
| **motyw** (plik `catsnboard-motyw...zip`) | Wygląd całej strony (kolory, układ, zdjęcie kota na górze) |
| **wtyczka Galeria i Wydarzenia** (`pnb-blocks...zip`) | Galeria zdjęć + kalendarz wydarzeń z zapisami gości |
| **wtyczka Polska Wersja** (`pnb-auto-pl...zip`) | Cała strona po polsku, z przełącznikiem PL / EN |

> 💡 **Zanim zaczniesz — o co chodzi?** Twoja strona działa na **WordPressie** (najpopularniejszy
> system do stron). Logujesz się do „panelu" (kuchni strony), a goście widzą gotową stronę.
> Panel otwierasz wpisując w przeglądarce adres: **`twojastrona.pl/wp-admin`**

---

## ✅ KROK 1 — Wgraj wszystko (raz, 10 minut)

Robisz to **w tej kolejności**: najpierw motyw, potem 2 wtyczki.

### 1a. Wgraj motyw (wygląd)
1. Zaloguj się: wpisz w przeglądarce **`twojastrona.pl/wp-admin`** → podaj login i hasło
2. W menu po lewej kliknij: **Wygląd → Motywy** *(Appearance → Themes)*
3. Na górze kliknij **Dodaj nowy** *(Add New)* → **Wyślij motyw na serwer** *(Upload Theme)*
4. Kliknij **Wybierz plik**, wskaż plik `catsnboard-motyw...zip` → **Zainstaluj** → **Włącz** *(Activate)*

✅ Gotowe — strona ma teraz nowy wygląd. Strony (Home, Gallery, Kontakt...) **utworzą się same**.

### 1b. Wgraj 2 wtyczki
1. W menu po lewej: **Wtyczki → Dodaj nową** *(Plugins → Add New)*
2. Na górze: **Wyślij wtyczkę na serwer** *(Upload Plugin)* → **Wybierz plik**
3. Wskaż `pnb-blocks...zip` → **Zainstaluj** → **Włącz**
4. **Powtórz to samo** z drugim plikiem: `pnb-auto-pl...zip`

✅ Gotowe! Wejdź na swoją stronę (bez `/wp-admin`) — zobaczysz galerię zdjęć kotów,
kalendarz wydarzeń i przełącznik **PL / EN** w prawym górnym rogu. **Wszystko po polsku od razu.**

> 💡 **Chcesz panel po polsku?** Wejdź w **Użytkownicy → Profil → Język → Polski**
> *(Users → Profile → Language → Polski)*. Wtedy przyciski będą po polsku.
> ⚠️ **NIE zmieniaj** „Język witryny" w Ustawienia → Ogólne — to musi zostać **English**
> (polską wersję dla gości robi nasza wtyczka).

---

## 🖼️ KROK 2 — Galeria: jak zmienić zdjęcia

1. Menu po lewej: **Strony → Gallery → Edytuj** *(Pages → Gallery → Edit)*
2. Kliknij w galerię — pokażą się przyciski:
   - **Wybierz zdjęcia** — zdjęcia górnej taśmy (ta przewijana)
   - **Wybierz zdjęcia „Moments"** — zdjęcia dolnej sekcji (osobny zestaw!)
3. Zaznacz zdjęcia (możesz dodać własne z komputera) → **Aktualizuj galerię**
4. Kliknij niebieski **Zapisz / Aktualizuj** w prawym górnym rogu

Napisy i podpisy zmieniasz klikając prosto w tekst.

---

## 📅 KROK 3 — Wydarzenia: jak dodać samemu

1. Menu po lewej: **Events → Dodaj nowe** *(Events → Add New)*
2. Wpisz **tytuł i opis** (po angielsku — polski zrobi się sam, patrz KROK 5)
3. Po prawej uzupełnij: **datę, godzinę, miejsce, ile miejsc** (0 = bez limitu)
4. **Ustaw zdjęcie wydarzenia** — przycisk w bocznym panelu (nie wklejaj zdjęcia do opisu!)
5. **Opublikuj** — wydarzenie samo pojawi się na stronie

**Kto się zapisał?** Otwórz wydarzenie do edycji — lista gości jest w ramce
**Zapisani goście** (z przyciskiem eksportu do Excela). Dodatkowo dostajesz **e-mail** przy
każdym zapisie (adres ustawiasz w **Events → Settings**).

> ⚠️ **E-maile mogą wpadać do SPAMU** — tak działają serwery, nie wtyczka. Ale zapisy
> **zawsze** są w panelu, nic nie ginie. Chcesz pewnych maili? Poproś hosting o „SMTP"
> albo wgraj darmową wtyczkę **WP Mail SMTP**.

---

## 🤖 KROK 4 — Automat: strona sama pobiera wydarzenia (opcjonalne)

Wtyczka umie **sama pobierać wydarzenia** z internetu (z serwisu Eventbrite) i dodawać je na
Twoją stronę — bez ręcznego wpisywania. Jak nie chcesz, po prostu tego nie włączaj.

**Jak włączyć:**
1. Menu: **Events → Settings** *(Ustawienia)*
2. W polu **„Event source (Eventbrite URL)"** wklej adres listy wydarzeń z Eventbrite
3. **Zapisz**. Od teraz wtyczka **co jakiś czas sama sprawdza** i dodaje nowe wydarzenia
4. Chcesz sprawdzić od razu? Kliknij **„Sync now"** — pobierze bez czekania

**Co automat robi sam (bez Ciebie):**
- ✅ **Dodaje** nowe wydarzenia + pobiera im zdjęcia
- ✅ **Tłumaczy** je na polski (jeśli masz wpięty klucz — patrz KROK 5)
- ✅ **Zdejmuje do kosza** wydarzenia które zniknęły ze źródła
- ✅ **Szanuje Twoje decyzje** — jak sam usuniesz wydarzenie do kosza, automat go NIE przywróci
- ✅ **Nie nadpisuje Twoich poprawek** — zmienisz zaimportowane wydarzenie → Twoja wersja zostaje

> ⏱️ **Jak często sprawdza?** Automat budzi się **gdy ktoś wchodzi na stronę**. Jak strona ma
> ruch — działa sam. Jak jest cicha, poproś hosting o **„cron"** pukający w
> `twojastrona.pl/wp-cron.php` co 10 minut, albo użyj darmowej **cron-job.org** (wklejasz tam
> ten adres, bez żadnego kodu).

> ⚠️ **WAŻNE — prawo:** upewnij się że masz **prawo** pobierać dane z serwisu który podajesz.
> Wiele serwisów (w tym Eventbrite) zabrania tego w regulaminie. Jak jesteś **organizatorem**
> swoich wydarzeń — najbezpieczniej użyć oficjalnego API serwisu. Odpowiedzialność jest po
> stronie właściciela strony.

---

## 🇵🇱 KROK 5 — Polska wersja i klucz do tłumaczenia

**Dobra wiadomość:** teksty strony są **już po polsku** (słownik jest w paczce) — przełącznik
**PL / EN** działa od pierwszego dnia, bez niczego.

**Klucz API** potrzebujesz TYLKO po to, żeby **NOWE treści** (nowe wydarzenia, Twoje zmiany)
tłumaczyły się same. To „silnik" tłumaczenia — Claude AI. Płacisz tylko za to co tłumaczysz (grosze).

**Jak zdobyć klucz (krok po kroku, jak dla laika):**
1. Wejdź na **`console.anthropic.com`** → załóż konto (jak zakładanie maila)
2. Doładuj konto małą kwotą (np. 5 dolarów — starczy na długo) w zakładce **Billing**
3. Wejdź w **API Keys** *(Klucze API)* → **Create Key** *(Utwórz klucz)* → **skopiuj** długi kod
   (zaczyna się od `sk-ant-...`) — ⚠️ pokaże się **tylko raz**, zapisz go sobie

**Jak wpiąć klucz do strony:**
1. W panelu: **Ustawienia → PNB Auto PL** *(Settings → PNB Auto PL)*
2. **Wklej klucz** w pole → **Zapisz**
3. Kliknij **Testuj połączenie** — ma pokazać ✅
4. Kliknij **„Przetłumacz witrynę"** — dotłumaczy wszystko naraz (pasek pokaże postęp, 1-2 min)

**Od teraz działa samo:** zmienisz albo dopiszesz tekst → zapisz → polska wersja
zaktualizuje się sama (zapis potrwa 2-3 sekundy dłużej). Odśwież stronę (F5) żeby zobaczyć.

> 💰 **Bezpiecznik kosztów:** wtyczka ma dzienny limit — nawet gdyby coś poszło nie tak,
> nie wydasz więcej niż kilka złotych dziennie. Licznik zużycia widzisz w ustawieniach.

---

## 📞 KROK 6 — Twoje dane kontaktowe (telefon, adres)

Telefon, e-mail i adres w **nagłówku, stopce i na stronach Kontakt / Lokalizacja** ustawiasz
w JEDNYM miejscu: **Wygląd → Dostosuj → „Dane kontaktowe"** *(Appearance → Customize)*.
Wpisz swoje prawdziwe dane → **Opublikuj**.

> Dopóki pole jest puste, ta informacja **w ogóle się nie pokazuje** (nic nie zmyślamy).

---

## 🗄️ KROK 7 — Twoja baza danych (nic nie musisz robić)

**Baza danych** to magazyn gdzie Twoja strona trzyma WSZYSTKO: wydarzenia, zdjęcia, ustawienia,
tłumaczenia. **Masz ją już** — WordPress stworzył ją gdy powstała Twoja strona. **Nie budujesz
żadnej nowej.**

> ✅ **Nasze wtyczki NIE mieszają w Twojej bazie.** Dokładają tylko SWOJE rzeczy (oznaczone
> `pnb_`) — jak nowa szuflada obok Twoich. **Nie kasują i nie nadpisują** niczego Twojego:
> Twoje strony, wpisy i ustawienia zostają nietknięte. Jeśli masz już stronę „Events" albo
> „Gallery" — wtyczka jej **nie ruszy** (tworzy tylko te których nie masz).

**Zaglądać do bazy zwykle nie musisz.** Ale jak chcesz zobaczyć co w środku (albo hosting poprosi):

**Sposób 1 — przez hosting (najczęstszy):**
1. Zaloguj się do panelu swojego **hostingu** (tam gdzie kupiłeś stronę — np. cyber_Folks, home.pl, OVH)
2. Znajdź ikonę **„phpMyAdmin"** albo **„Bazy danych"**
3. Klikasz → widzisz listę „tabel" (magazynów). Możesz je przeglądać i eksportować (robić kopię)

**Sposób 2 — wtyczka w WordPressie:**
Wgraj darmową wtyczkę **„WP Data Access"** — podejrzysz bazę prosto z panelu, bez hostingu.

**Co gdzie siedzi** (gdybyś szukał):
| Tabela | Co trzyma |
|---|---|
| `wp_posts` | wydarzenia i strony |
| `wp_pnb_slownik_en_pl` | polskie tłumaczenia |
| `wp_options` | ustawienia (w tym Twój klucz API) |

> 🔒 **Ważne o kluczu API:** Twój klucz jest w bazie (tabela `wp_options`, pozycja
> `pnb_auto_pl_klucz`). Baza jest **Twoja i tylko Twoja** — nikt z zewnątrz jej nie widzi.
> Ale **nie pokazuj nikomu** tego klucza (to jak hasło do Twoich pieniędzy za tłumaczenie).

---

## 💡 Dobre rady i znane sprawy

- **Cache (przyspieszacz):** jak hosting ma wtyczkę przyspieszającą (np. LiteSpeed), po dużym
  tłumaczeniu kliknij w niej **„Purge All"** (wyczyść), żeby goście od razu widzieli świeżą wersję.
- **Zdjęcia:** wgrywaj do ~2500px szerokości — wtyczka sama zrobi miniatury.
- **Wtyczek nie trzeba aktualizować** — nie pobierają nic z internetu (poza tłumaczeniem u Claude).
- **Cofnij (Ctrl+Z) w edytorze** bywa kapryśne przy blokach — to przypadłość WordPressa, nie wtyczek.
  Jak coś pójdzie nie tak: nie zapisuj, tylko odśwież stronę edytora.

---

## ❓ Coś nie działa?

Napisz do nas — opisz **co klikasz i co się dzieje** (najlepiej ze zrzutem ekranu).
Zapisy gości i tłumaczenia są bezpieczne w bazie — nawet jak coś wygląda dziwnie, **nic nie ginie**.
