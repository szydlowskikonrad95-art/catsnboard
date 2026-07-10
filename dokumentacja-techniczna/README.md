# 📚 Dokumentacja Cats'N'Board

Ten folder zawiera pełną dokumentację produktu — dwie ścieżki, zależnie od tego, kto czyta:

| Plik | Dla kogo | Co znajdziesz |
|---|---|---|
| **[INSTRUKCJA-DLA-KLIENTA.md](../INSTRUKCJA-DLA-KLIENTA.md)** | Właściciel strony (bez wiedzy technicznej) | Instalacja krok po kroku, ze zrzutami, prostym językiem |
| **[INSTRUKCJA-TECHNICZNA.md](INSTRUKCJA-TECHNICZNA.md)** | Informatyk / osoba wdrażająca | Architektura, separacja plików, baza, wymagania, uwagi |
| **[AKTUALIZACJE.md](AKTUALIZACJE.md)** | Osoba utrzymująca | Jak wydajemy i dostarczamy nowe wersje, rollback, plan na skalę |
| **[diagramy/](diagramy/)** | Wszyscy | 4 schematy PNG: architektura, przepływ, pliki wtyczek, odporność |

## Foldery

- **`diagramy/`** — 4 schematy PNG: architektura, przepływ importu, pliki wtyczek, odporność
- **`diagramy-zrodla/`** — edytowalne źródła HTML diagramów (+ wspólny `style.css`)

(Zrzuty ekranu do instrukcji klienta są w folderze `zrzuty/` w korzeniu repozytorium.)

## Wymagania

- **WordPress** 6.0+ · **PHP** 7.4+ · **MySQL/MariaDB** (baza WordPressa)
- **WP-Cron** (import wydarzeń) — na stronie z małym ruchem wymaga zewnętrznego cron pukającego w `wp-cron.php`
- **Claude API** (Anthropic) — opcjonalnie, do tłumaczenia nowych treści (klucz `sk-ant-…`)
- **Eventbrite** — źródło wydarzeń dla importera (adres listy w ustawieniach)

## Szybki start (skrót)

1. Kopia zapasowa strony
2. Wgraj **2 wtyczki** (`pnb-blocks`, `pnb-auto-pl`) przez *Wtyczki → Dodaj → Wyślij zip*
3. Podłącz klucz API (*Ustawienia → PNB Auto PL*) i źródło wydarzeń (*Events → Settings*)
4. Gotowe — automat sam importuje i tłumaczy wydarzenia co 10 minut

Motyw w paczce (`catsnboard-motyw`) jest **tylko do testów** — nie wgrywaj go na produkcyjną stronę klienta.
