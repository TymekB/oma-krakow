# OMA — fizjoterapia i terapia twarzy, Kraków

Strona wizytówka gabinetu OMA (Natalia Podgórska) wraz ze sklepem: fizjoterapia, ZOGA Face, ERT Marii Margo, masaż.

Projekt składa się z dwóch części:

| Część | Stack | Gdzie |
|---|---|---|
| Landing | Angular 22, standalone components, SCSS | katalog główny, `src/` |
| Sklep + panel | Sylius 2.2, Symfony 7.4, FrankenPHP, MySQL 8 | `backend/` |

Landing jest budowany do statyków i serwowany przez ten sam kontener co sklep (`/` → landing, `/sklep/` → Sylius, `/admin/` → panel).

## Uruchomienie

```bash
cd backend
make up            # docker compose: app (FrankenPHP), mysql, mailpit
make install       # composer install + yarn install + assets
make fixtures      # dane startowe (produkty, kanał, admin)
make landing       # build Angulara i wgranie go do backend/public
```

- Sklep: http://localhost:8080/sklep/
- Panel: http://localhost:8080/admin/ (`admin` / `oma2026!`)
- Maile: http://localhost:8025 (Mailpit — cały ruch z dev-a idzie tam)
- Kolejki: http://localhost:15673 (RabbitMQ, `guest` / `guest`)

`make up` podnosi też `rabbitmq` i `worker` (konsument messengera). Port `5672` nie jest publikowany
na hosta, bo aplikacja łączy się po sieci Compose — panel jest na `15673`, żeby nie kolidować
z innym RabbitMQ, który możesz mieć lokalnie.

Landing osobno, z hot reloadem: `npm install && npm start` → http://localhost:4200

## Pułapki, o które łatwo się potknąć

**Kontener chodzi w `APP_ENV=prod` z `opcache.validate_timestamps=Off`.** PHP nie sprawdza dat plików, więc zmiany w PHP, Twigu i configu **nie wchodzą nawet po `cache:clear`** — trzeba `docker compose restart app`. Do pracy nad backendem użyj `make dev`, które przestawia stack na `APP_ENV=dev`.

**Zmiany w landingu wymagają `make landing`.** Bez tego `backend/public/` serwuje poprzedni build i wygląda to jak „zmiany nie działają".

**Yarn w `backend/`.** Rootowy `package.json` (Angular) deklaruje `packageManager: npm`, przez co yarn wychodził w górę drzewa i odmawiał startu — dlatego `backend/package.json` ma własne `packageManager: yarn@1.22.22`. Przy Node 23 instaluj z `--ignore-engines`.

## Kolejki i promocje z datami

Transporty messengera na localu idą na **RabbitMQ** (`symfony/amqp-messenger`, rozszerzenie `amqp`
w obrazie). Nie jest to kosmetyka: Sylius planuje start i koniec **promocji katalogowej** przez
`DelayStamp` — wylicza opóźnienie do daty startu i do daty końca i wysyła komunikaty z tym
opóźnieniem. Respektują je tylko transporty asynchroniczne. Na `sync://` opóźnienie jest ignorowane,
komunikaty wykonują się od razu przy zapisie i **daty promocji przestają cokolwiek znaczyć**.

Dlatego promocje z terminem wymagają działającego konsumenta:

```bash
docker compose logs -f worker
docker compose exec app php bin/console messenger:stats
```

## Konfiguracja usług zewnętrznych

Klucze podaje się przez zmienne środowiskowe czytane w `backend/compose.yaml`:

```bash
OMA_GOOGLE_CLIENT_ID=... OMA_GOOGLE_CLIENT_SECRET=... docker compose up -d app
```

**Logowanie Google** — w Google Cloud Console dodaj *Authorized redirect URI*
`http://localhost:8080/sklep/connect/google/check`. Dopóki klucze są puste, przycisk „Kontynuuj z Google"
się nie renderuje, a `/sklep/connect/google` zwraca 404 (zamiast wysypywać się na pustym kluczu).

**Stripe (karta, BLIK, Przelewy24)** — metoda płatności `stripe` jest **wyłączona**, bo w bazie
zapisane są zaszyfrowane puste klucze. Żeby ją uruchomić: wpisz klucze testowe w panelu
(*Konfiguracja → Metody płatności → „Karta, BLIK, Przelewy24…"*), włącz metodę i włącz BLIK
w Stripe Dashboard dla PLN. Bramka jest ustawiona na `stripe_web_elements`, czyli osadzony
Payment Element — kod BLIK wpisuje się na stronie sklepu, bez przekierowania.

## Wdrożenie (produkcja)

Produkcja stoi na VPS Mikrus (`kate123`, 8 GB RAM / 2 vCPU, SSH na porcie `10123`) — projekt
Compose `oma-prod` w `/opt/oma-prod`, własna sieć i wolumeny. Serwer jest dedykowany dla OMA,
nie dzieli zasobów z innymi stackami.

Adres: **https://strong-cow5239.byst.re** (landing `/`, sklep `/sklep/`, panel `/admin/`).

Merge do `master` uruchamia `.github/workflows/deploy.yml`, który:

1. buduje obraz z `deploy/Dockerfile` (composer bez dev, `yarn encore production`, build Angulara)
   i wypycha go do `ghcr.io/tymekb/oma-krakow`,
2. kopiuje pliki stacku na serwer i wpisuje nowy tag obrazu do `/opt/oma-prod/.env`,
3. odpala `deploy/remote-deploy.sh` (migracje przy zatrzymanym workerze, `compose up --wait`,
   `image prune`),
4. odpala `deploy/smoke-test.sh` — stan kontenerów `app`/`worker`/`mysql` oraz `/`, `/sklep/`,
   `/admin/login`.

Obraz jest self-contained: `vendor/`, assety Encore i landing są w nim wypalone, więc na serwerze
nie ma kodu z repo ani bind-mountów. Trwałe dane to wolumeny: baza, `public/media`, klucze JWT
i sesje.

Skrypty wdrożeniowe są w plikach, nie inline w YAML-u, bo `docker compose run` przechwytuje stdin —
skrypt podany przez `bash -s` zostałby po części zjedzony przez kontener migracji i reszta kroków
nigdy by się nie wykonała (przy zerowym kodzie wyjścia, czyli fałszywie „zielono").

### Hostname kanału

Sylius rozpoznaje kanał po nagłówku `Host`. Kanał `OMA_WEB` ma w bazie `hostname` ustawiony na
domenę produkcyjną — bez tego `/sklep/` zwraca 404. Przy zmianie domeny trzeba zmienić dwie rzeczy:
`hostname` w `sylius_channel` oraz `OMA_DEFAULT_URI` w `.env`. Dlatego smoke test uderza z właściwym
nagłówkiem `Host`, a nie po `localhost`.

### Konfiguracja serwera

Sekrety żyją **tylko** w `/opt/oma-prod/.env` (nie w CI, nie w repo) — `APP_SECRET`, hasła do bazy,
`JWT_PASSPHRASE`, klucze Google/Stripe, `MAILER_DSN`. Po zmianie: `docker compose up -d`.

Hasło admina na produkcji jest inne niż `oma2026!` z fixtures — te ostatnie są w publicznym repo,
więc po zaseedowaniu zostało zmienione i nie ma go w repozytorium.

Ruch wchodzi przez proxy webowe Mikrusa: `domena 40123` rejestruje subdomenę kierującą na port
`40123`. TLS kończy się na Cloudflare, więc aplikacja dostaje HTTP z `X-Forwarded-Proto` — stąd
`TRUSTED_PROXIES` w `config/packages/prod/framework.yaml`. Uwaga: każde wywołanie `domena` bez
podanej nazwy generuje **nową** losową subdomenę, a subdomeny `byst.re` są przypisane do konkretnego
VPS-a — przy przenosinach API odpowiada `Domena już istnieje` i starą trzeba najpierw zwolnić
w panelu Mikrusa. Bezpośrednio wystawione porty TCP tej maszyny to `20123`/`30123` (wolne, ale bez
TLS-a, więc nieużywane).

### Wydajność na 2 vCPU / 8 GB

Limity pamięci (`mem_limit`) zostają, choć maszyna jest dedykowana: bez swapa (LXC) wyciek w PHP
ubija wtedy kontener, a nie cały serwer. Budżet jest ustawiony z dużym zapasem — przy normalnym
ruchu stack bierze ~600 MB z 8 GB (`app` ~290 MB, `worker` ~120 MB, `mysql` ~175 MB).

Włączony jest **worker mode** FrankenPHP (`FRANKENPHP_CONFIG` w `deploy/compose.prod.yaml`): kernel
Symfony bootuje raz na wątek, `OMA_PHP_WORKERS=4` przy `OMA_PHP_THREADS=8`. Zmierzone dla `/sklep/`
(`bench.sh /sklep/ 24 2`), porównanie ze starą maszyną (`matt197`, 1 vCPU / 2 GB, współdzielona):

| | matt197 | kate123 |
|---|---|---|
| throughput | 5,7 rps | 35,6 rps |
| p50 | 0,27 s | 0,03 s |
| p95 | 0,54 s | 0,08 s |

Sufit to ~33 rps przy `concurrency=8` i `16` (p95 odpowiednio 0,21 s i 0,33 s) — wysycają się dwa
vCPU, więc dokładanie workerów niczego już nie doda.

Transporty messengera idą na `doctrine://default`, a konsumuje je **kontener `worker`** z osobnym
limitem pamięci. Wcześniej robił to cron co 5 minut, bo na 1 vCPU stały worker był główną przyczyną
przeciążenia; tutaj kosztuje ~120 MB i nie ma powodu opóźniać promocji katalogowych o kilka minut.
`remote-deploy.sh` kasuje starego `/etc/cron.d/oma-messenger`, jeśli został po poprzednim wdrożeniu.

JIT (`tracing`, 64 MB) i `opcache` 256 MB są ustawione w `deploy/php-prod.ini`. `memory_limit` musi
zostać na 512 MB — niżej `opcache.preload` nie wchodzi i kontener wpada w pętlę restartów.

Do pomiarów: `bash /opt/oma-prod/bench.sh /sklep/ 24 2`.

## Testy

```bash
npm run e2e                    # Playwright: landing, sklep, checkout, panel (wymaga działającego stacku)
npm run e2e:report             # raport HTML z ostatniego uruchomienia

cd backend
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/ecs check --fix
```

CI (GitHub Actions) buduje landing, uruchamia ECS + PHPStan + PHPUnit + linty Twiga i kontenera
oraz build assetów. Testy e2e nie są w CI — wymagają pełnego stacku Dockera.

## Struktura landingu

```
src/app/
  core/          reveal.directive.ts (animacje scroll), site.data.ts (treści i cennik)
  layout/        navbar, footer
  sections/      hero, about, services, manifesto, gallery, pricing, testimonials, contact
  pages/home/    kompozycja sekcji (lazy-loaded route)
public/assets/img/  zdjęcia gabinetu (WebP, JPEG tylko dla og:image)
```

Cała treść tekstowa, cennik i dane kontaktowe są w jednym pliku: `src/app/core/site.data.ts`.

## Design system

Zmienne w `src/styles.scss`, odpowiedniki dla sklepu w `backend/public/oma/shop.css`,
dla panelu w `backend/public/oma/admin.css`:

- `--wine-900/800/700/600` — bordo z logotypu OMA (navbar, akcenty, CTA)
- `--paper` `#f4f3f0`, `--paper-alt` `#e8e6e2` — papierowe tła
- ziarno filmowe: proceduralny SVG (`feTurbulence`) nakładany przez `body::before`
  z `mix-blend-mode: multiply`; ciemne sekcje dostają drugą warstwę przez `screen`
- `--font-display` Cormorant Garamond (nagłówki), `--font-body` Jost (treść)
- motyw łuku (`--radius-arch`) nawiązuje do lustra i wnęk z gabinetu

## Do uzupełnienia przed publikacją

Dane w `site.data.ts` są przykładowe i wymagają potwierdzenia:

- adres, telefon, e-mail, godziny otwarcia (obecnie placeholdery `00/0`, `+48 000 000 000`)
- ceny i czasy trwania zabiegów
- opinie klientek (obecnie treści przykładowe)
- opisy ZOGA Face i ERT Marii Margo — warto zweryfikować z autorką metody

Formularz kontaktowy na landingu nie ma backendu — składa wiadomość i otwiera klienta pocztowego (`mailto:`).
