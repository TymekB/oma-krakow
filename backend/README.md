# OMA — backend (Sylius 2.2 + Symfony 7.4 + FrankenPHP)

Sklep gabinetu OMA. Cała aplikacja stoi na Dockerze, jeden port serwuje landing i sklep.

## Adresy

| Co | URL |
| --- | --- |
| Landing OMA (Angular) | http://localhost:8080/ |
| Sklep (Sylius) | http://localhost:8080/sklep/ |
| Panel admina | http://localhost:8080/admin/login |
| Mailpit (podgląd maili) | http://localhost:8025 |
| MySQL | localhost:3307 (`sylius` / `sylius`, baza `sylius_oma`) |

## Logowanie do panelu

```
login:  admin@oma-fizjo.pl
hasło:  oma2026!
```

Zmiana hasła: `docker compose exec app php bin/console sylius:admin-user:change-password`.

## Uruchomienie

```bash
docker compose up -d          # albo: make up
```

Pierwsze uruchomienie od zera (czysta baza, fixtures, assety):

```bash
make reset
```

## Stack

- **FrankenPHP 1.12** (Caddy + PHP 8.3 ZTS) w **worker mode** — 6 workerów, kernel Symfony bootuje raz
- **APP_ENV=prod** + opcache z `validate_timestamps=0`, preload i JIT (tracing)
- `var/` na wolumenie Dockera (bind mount na macOS był wąskim gardłem przy cache 116 MB)
- MySQL 8.0, Mailpit
- Statyki (`/build`, `/assets/img`, hashowane pliki Angulara) z `Cache-Control: immutable`

Efekt: strona sklepu ~35 ms/request zamiast ~190 ms na php-fpm + nginx w trybie dev.

### Tryb dev

```bash
make dev     # APP_ENV=dev, debug, bez worker mode
make up      # powrót na prod
```

W trybie prod zmiany w PHP/Twig wymagają `make restart` (opcache nie sprawdza timestampów).

## Struktura zmian względem czystego Syliusa

```
docker/frankenphp/     Dockerfile, Caddyfile, php.ini, entrypoint.sh
docker/sql/            demo-prices.sql (ceny produktów testowych)
config/packages/sylius_fixtures.yaml   suite "oma" — kanał, waluta, taksonomia, admin, 3 produkty
src/Menu/AdminMenuListener.php         usuwa z menu sekcje Sylius Plus
templates/bundles/SyliusShopBundle/    logo, baner, stopka, top bar
templates/bundles/SyliusAdminBundle/   logo, stopka, ukryta sekcja Role
public/oma/shop.css                    theme OMA dla sklepu
public/oma/admin.css                   theme OMA dla panelu
public/index.html + main-*.js + ...    zbudowany landing Angulara
```

## Landing

Landing to build Angulara z katalogu nadrzędnego, kopiowany do `public/`:

```bash
make landing && make restart
```

Caddy serwuje `index.html` na `/`, resztę ścieżek przejmuje Sylius.

## Sklep — dane

Kanał `OMA_WEB`, waluta PLN, locale `pl_PL`, strefa PL z VAT 23%.
Taksonomia: Pielęgnacja, Aromaterapia, Vouchery.

Produkty testowe (do podmiany):

| Produkt | Cena |
| --- | --- |
| Olejek do ciała — bergamotka, lawenda, pomarańcza | 89,00 zł |
| Olejek eteryczny — eukaliptus | 39,00 zł |
| Voucher — masaż relaksacyjny 60 min | 180,00 zł |

Przeładowanie danych: `make fixtures` (czyści bazę i ładuje suite `oma` od nowa).

## Metody płatności

W stopce sklepu są ikony Przelewy24, PayU, PayNow, Google Pay i Apple Pay — **to na razie
tylko grafika**. Realne bramki wymagają pluginów (`bitbag/przelewy24-plugin`,
`bitbag/payu-plugin`) i konfiguracji w panelu.

Aktywne metody płatności w Syliusie: przelew bankowy i płatność przy odbiorze.

## Do uzupełnienia przed produkcją

- dane firmy: adres, NIP, telefon, e-mail (placeholdery `00/0`, `+48 000 000 000`)
- `APP_SECRET` w `.env` — wygenerować nowy
- hasło admina i dane dostępowe do MySQL
- HTTPS: Caddy ma `auto_https off` (lokalnie po HTTP); na produkcji ustawić `SERVER_NAME` na domenę
- regulamin, polityka prywatności, koszty dostawy
