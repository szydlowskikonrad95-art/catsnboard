# Aktualizacje — jak wydajemy i dostarczamy nowe wersje

Dokument dla osoby utrzymującej produkt. Klientowi wystarcza skrót w
[INSTRUKCJA-DLA-KLIENTA.md](../INSTRUKCJA-DLA-KLIENTA.md) (sekcja „Dobre rady").

## Jak wydawane są wersje

- Wersjonowanie **SemVer** (MAJOR.MINOR.PATCH) per komponent; wersja paczki = tag `vX.Y.Z`.
- Każda zmiana przechodzi cykl **branch → test → PR → merge** (historia w Pull Requests).
- Wydanie: `CHANGELOG.md` (Keep a Changelog) → build paczki z bramkami → tag → **GitHub Release**
  z paczką `catsnboard-vX.Y.Z.zip` jako załącznikiem. Najnowsza wersja = release oznaczony **Latest**.

## Jak dostarczyć aktualizację klientowi (ręcznie — obecny kanał)

1. Pobierz paczkę z release **Latest** (Releases → `catsnboard-vX.Y.Z.zip`).
2. Prześlij klientowi zip wtyczki (albo całą paczkę z instrukcją).
3. Klient (albo Ty): panel → **Wtyczki → Dodaj nową → Wyślij wtyczkę** → wskaż zip →
   WordPress wykryje istniejącą wtyczkę i zapyta → **„Zastąp bieżącą wersję przesłaną"**.
4. Ustawienia, wydarzenia, słownik tłumaczeń i zapisy gości **zostają** (dane żyją w bazie,
   nie w plikach wtyczki).

**Rollback:** załączniki starszych release'ów zostają na GitHubie na zawsze — wgranie
poprzedniego zipa tą samą drogą cofa wersję (dane w bazie nietknięte).

## Dlaczego wtyczki NIE aktualizują się same (świadoma decyzja)

Repozytorium jest **prywatne**. Automatyczny updater (np. plugin-update-checker) musiałby mieć
na stronie klienta **token dostępu do repo** — sekret w cudzej bazie, dający wgląd w całe
repozytorium. Przy skali „jeden klient" ryzyko > wygoda. Wtyczki nie łączą się z żadnym
serwerem aktualizacji (jedyny ruch wychodzący: Eventbrite — import, Claude API — tłumaczenie).

## Plan na skalę (3–5+ klientów)

Gdy klientów będzie kilku, ręczne zipy zaczną boleć — wtedy:

1. **Publiczny endpoint aktualizacji** (statyczny JSON-manifest + zip na hostingu/Pages) +
   biblioteka [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)
   w trybie „własny serwer" — bez tokenów u klientów, wtyczki widzą aktualizację w panelu
   jak każdą inną.
2. Alternatywnie: osobne **publiczne** repo tylko z paczkami wydań (kod zostaje prywatny).

Decyzja odłożona świadomie — wraca przy pierwszym skalowaniu.
