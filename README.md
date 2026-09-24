# Design Presta 1

## Status projektu — MVP

To nie jest gotowy sklep — to mockup/design headera (głównie desktop), reszta
to niezmieniony Hummingbird. Szczegóły niżej w `## Header`.

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

Repo trzyma tylko customizacje (patrz `.gitignore` — reszta `prestashop/` jest
ignorowana): moduł `designassets`, `freeshippingbar`, `megamenu`, wybrane pliki
motywu `hummingbird` (CSS, `header.tpl`, `ps_customersignin`/`ps_shoppingcart`/
`ps_languageselector`). Czysta Presta jest douzupełniana przez obraz Dockera przy
pierwszym `docker-compose up` — entrypoint kopiuje ją przez `cp -n` (no-clobber),
więc nigdy nie nadpisze plików, które już leżą w repo.

## Header

Custom header (przełącznik Kobieta/Dziecko/Mężczyzna, mega menu, rozwijane menu
w hamburgerze ze scrollem) to **wersja desktopowa** (≥992px):

![Header desktop](docs/screenshots/header-desktop.png)

Poniżej ~992px pasek nagłówka to standardowy, niecustomizowany layout Hummingbirda
(logo, lupka, ulubione, konto, koszyk) — cała customizacja (switcher, rozwijane
podkategorie) żyje **wewnątrz hamburgera** po jego otwarciu, nie w samym pasku.

## Branche

- `main` — stabilny stan projektu (squash z `develop`)
- `develop` — bieżąca praca
