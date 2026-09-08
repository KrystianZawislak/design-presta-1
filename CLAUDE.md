# Zasady pracy nad projektem

Ten projekt traktujemy jak **prawdziwe zamówienie komercyjne** — z realnym klientem,
realnym terminem i realnymi wymaganiami, które trafi na produkcję. Nie jak plac zabaw
do nauki Dockera. Poniższe zasady obowiązują we wszystkich zmianach w kodzie.

## 1. Robimy to porządnie, od razu

- Zero podejścia "na razie zróbmy na sztywno, byle działało" — jeśli coś ma docelowo
  być konfigurowalne (np. z BO), robimy to od razu jako konfigurowalne, a nie hardcode
  do poprawienia "kiedyś".
- Jeśli tymczasowe uproszczenie jest naprawdę potrzebne (np. do szybkiego testu),
  mówimy o tym wprost w rozmowie — nigdy nie zostawiamy tego cicho jako gotowe
  rozwiązanie.
- Idziemy krok po kroku: mała zmiana → sprawdzenie że działa → kolejna zmiana.
  Nie mnożymy niedokończonych wątków naraz.
- Zanim uznamy zadanie za zrobione, ono faktycznie działa (przetestowane), a nie
  "powinno działać".

## 2. KISS — Keep It Super Simple

- Najprostsze rozwiązanie, które poprawnie spełnia wymaganie, wygrywa z "eleganckim"
  nadmiarowym rozwiązaniem.
- Nie budujemy abstrakcji, modułów ani konfiguracji "na przyszłość, bo może się przyda" —
  tylko pod realną, aktualną potrzebę.

## 3. DRY — Don't Repeat Yourself

- Nie kopiujemy tego samego kodu/logiki w kilka miejsc — wydzielamy funkcję,
  komponent, hook, ustawienie w bazie (Configuration) itp.
- Przed dodaniem czegoś nowego sprawdzamy, czy PrestaShop już nie ma na to
  gotowego mechanizmu (hook, moduł, Configuration) zamiast pisać to od zera.

## 4. Zero komentarzy w kodzie

- Żadnych komentarzy w kodzie. Żadnych `// TODO`, `// FIXME`, `{* opis *}` ani
  jakichkolwiek innych. Kod ma być czytelny sam z siebie (nazwy zmiennych/funkcji).
- Wszelkie wyjaśnienia, zastrzeżenia, uwagi "do zrobienia później" — tylko w rozmowie,
  nigdy w pliku.

## 5. Cache — wyłączone w dev, włączone na produkcji

- Na czas developmentu WYŁĄCZONE mają być trzy przełączniki w
  `Parametry zaawansowane → Wydajność`:
  - `PS_CSS_THEME_CACHE`
  - `PS_JS_THEME_CACHE`
  - `PS_SMARTY_CACHE` (cache całych wyrenderowanych stron — najłatwiej o nim zapomnieć,
    bo nie jest w tej samej sekcji co CSS/JS, a jego skutek to sztywna, stara wersja
    całej strony mimo zmian w kodzie)
- Przed wdrożeniem na produkcję ktoś musi świadomie włączyć wszystkie trzy z powrotem —
  bez tego sklep jedzie bez łączenia/minifikacji CSS/JS i bez cache'owania stron.

## 6. Bezpieczeństwo — bez wyjątków

- Żadnych luk bezpieczeństwa nie zostawiamy "na potem": SQL injection, XSS, brak
  walidacji/sanityzacji inputu, dane wrażliwe (hasła, klucze) w kodzie czy w repo,
  słabe/domyślne hasła w konfiguracji produkcyjnej.
- Dane dostępowe (hasła do bazy, klucze API) nie trafiają na sztywno do plików
  śledzonych w repo — używamy zmiennych środowiskowych / plików ignorowanych przez git.
- Jeśli podczas pracy zauważymy istniejącą lukę (nawet nie w zakresie bieżącego
  zadania) — zgłaszamy to wprost, zamiast przechodzić obok.
