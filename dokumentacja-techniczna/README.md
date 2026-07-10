# 📚 Dokumentacja Cats'N'Board

Ten folder zawiera pełną dokumentację produktu — dwie ścieżki, zależnie od tego, kto czyta:

| Plik | Dla kogo | Co znajdziesz |
|---|---|---|
| **[INSTRUKCJA-PROSTA.md](INSTRUKCJA-PROSTA.md)** | Właściciel strony (bez wiedzy technicznej) | Instalacja krok po kroku, z obrazkami, prostym językiem |
| **[INSTRUKCJA-TECHNICZNA.md](INSTRUKCJA-TECHNICZNA.md)** | Informatyk / osoba wdrażająca | Architektura, separacja plików, baza, wymagania, uwagi |
| **[diagramy/](diagramy/)** | Wszyscy | 4 schematy PNG: architektura, przepływ, pliki wtyczek, odporność |

## Foldery

- **`diagramy/`** — 4 schematy PNG: architektura, przepływ importu, pliki wtyczek, odporność
- **`diagramy-zrodla/`** — edytowalne źródła HTML diagramów (+ wspólny `style.css`)
- **`zrzuty/`** — zrzuty ekranu paneli i strony (użyte w instrukcji prostej)

## Szybki start (skrót)

1. Kopia zapasowa strony
2. Wgraj **2 wtyczki** (`pnb-blocks`, `pnb-auto-pl`) przez *Wtyczki → Dodaj → Wyślij zip*
3. Podłącz klucz API (*Ustawienia → PNB Auto PL*) i źródło wydarzeń (*Events → Settings*)
4. Gotowe — automat sam zwozi i tłumaczy wydarzenia co 10 minut

Motyw w paczce (`catsnboard-motyw`) jest **tylko do testów** — nie wgrywaj go na produkcyjną stronę klienta.
