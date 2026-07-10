# Cats'N'Board — wtyczki WordPress 🐾

**Produkt = dwie wtyczki** do samodzielnego zarządzania stroną kociego pensjonatu:
galeria premium, kalendarz wydarzeń z zapisami gości i **automatyczna polska wersja** strony.
Prostym językiem, bez potrzeby informatyka.

> **Motyw w paczce jest TYLKO DO TESTÓW** — to odtworzona kopia wyglądu strony klienta
> (poligon, na którym wygodnie sprawdzić wtyczki). **Nie wgrywać go na prawdziwą stronę klienta.**

---

## Jak to działa (w skrócie)

Klient wgrywa 2 wtyczki na swój WordPress. Wtyczki dokładają swoje do jego bazy (prefiks `pnb_`),
niczego nie kasują. Automat sam co 10 minut zwozi wydarzenia z Eventbrite, dodaje zdjęcia i tłumaczy
je na polski — bez niczyjej ręki.

![Architektura produktu](dokumentacja-techniczna/diagramy/1-architektura-produktu.png)

## Jak to wygląda

Kalendarz wydarzeń ze zdjęciami, filtrami i zapisami — po polsku:

![Wydarzenia na stronie](zrzuty/07-wydarzenia-front.png)

---

## Co jest w paczce

| Plik | Opis |
|------|------|
| **`pnb-blocks-…zip`** | Wtyczka „PNB Galeria i Wydarzenia" — galeria premium + kalendarz wydarzeń z zapisami gości (2 bloki Gutenberga, edycja w podglądzie). |
| **`pnb-auto-pl-…zip`** | Wtyczka „PNB Polska Wersja (AI)" — strona po polsku z przełącznikiem PL/EN; zmiany treści tłumaczą się same po zapisie. |
| **`catsnboard-motyw-…zip`** | Motyw **do testów** — wygląd strony (hero, sekcje, animacje). Nie dla produkcji. |
| **`INSTRUKCJA-DLA-KLIENTA.md`** | Instrukcja obsługi krok po kroku (dotyczy TYLKO wtyczek). **Zacznij od niej.** |

## 📚 Pełna dokumentacja

W folderze **[`dokumentacja-techniczna/`](dokumentacja-techniczna/)** — dwie ścieżki + schematy:

| Dokument | Dla kogo |
|---|---|
| **[Instrukcja dla klienta](INSTRUKCJA-DLA-KLIENTA.md)** (ze zrzutami krok po kroku) | Właściciel strony, bez wiedzy technicznej |
| **[Instrukcja techniczna](dokumentacja-techniczna/INSTRUKCJA-TECHNICZNA.md)** (architektura, pliki, baza) | Informatyk / osoba wdrażająca |
| **[Diagramy](dokumentacja-techniczna/diagramy/)** (architektura · przepływ · pliki · odporność) | Jak działa system + z czego zbudowany |

---

## Instalacja na stronie TESTOWEJ (skrót)

1. **Motyw** (tylko test!): *Wygląd → Motywy → Dodaj → Wyślij* → `catsnboard-motyw-…zip` → Włącz
   (motyw sam utworzy podstrony)
2. **Wtyczki**: *Wtyczki → Dodaj nową → Wyślij* → oba zipy wtyczek → Włącz
   (galeria zamieni się na premium, powstanie kalendarz z przykładowymi wydarzeniami)
3. **Polska wersja** działa od razu (gotowe tłumaczenia w paczce). Klucz API
   (*Ustawienia → PNB Auto PL*) podłącz po to, żeby TWOJE zmiany tłumaczyły się same.

> ⚠️ Jeśli na stronie jest WPML — wyłącz go przed włączeniem Polskiej Wersji (szczegóły w instrukcji).

Pełna instalacja krok po kroku ze zrzutami: **[instrukcja dla klienta](INSTRUKCJA-DLA-KLIENTA.md)**.
