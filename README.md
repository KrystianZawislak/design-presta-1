# Design Presta 1

## Uruchomienie od zera

```bash
git clone https://github.com/KrystianZawislak/design-presta-1.git
cd design-presta-1
git checkout develop
docker-compose up -d
```

Instalacja PrestaShopa trwa ok. 2 minuty. Postęp można śledzić przez:

```bash
docker-compose logs -f prestashop
```

Sklep: http://localhost:1000
Panel admina: adres poda się na końcu logów instalacji (`docker-compose logs prestashop | grep backoffice`)

Dane logowania do panelu admina są ustawione w `docker-compose.yml` (`ADMIN_MAIL` / `ADMIN_PASSWD`).

## Po pierwszym uruchomieniu

1. `Moduły → Menedżer modułów` → zainstaluj moduł **Design Assets**
   (auto-rejestruje pliki CSS z `themes/hummingbird/assets/css/layout/`)
2. `Parametry zaawansowane → Wydajność` → wyłącz cache CSS i JS na czas developmentu
   (włączyć dopiero przed wdrożeniem na produkcję — patrz `CLAUDE.md`)

## Struktura repo

To repozytorium **nie zawiera pełnego kodu PrestaShopa** — tylko nasze customizacje.
Czysta Presta jest ściągana automatycznie przez obraz Dockera przy pierwszym
`docker-compose up`. Trackowane jest wyłącznie:

- `docker-compose.yml`, `.gitignore`, `CLAUDE.md`, `README.md`
- `prestashop/modules/designassets/`
- `prestashop/themes/hummingbird/assets/css/layout/`
- `prestashop/themes/hummingbird/assets/css/tokens.css`

## Branche

- `main` — czysty scaffold projektu (bez customizacji)
- `develop` — bieżąca praca
