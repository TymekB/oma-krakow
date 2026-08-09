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

Landing osobno, z hot reloadem: `npm install && npm start` → http://localhost:4200

## Pułapki, o które łatwo się potknąć

**Kontener chodzi w `APP_ENV=prod` z `opcache.validate_timestamps=Off`.** PHP nie sprawdza dat plików, więc zmiany w PHP, Twigu i configu **nie wchodzą nawet po `cache:clear`** — trzeba `docker compose restart app`. Do pracy nad backendem użyj `make dev`, które przestawia stack na `APP_ENV=dev`.

**Zmiany w landingu wymagają `make landing`.** Bez tego `backend/public/` serwuje poprzedni build i wygląda to jak „zmiany nie działają".

**Yarn w `backend/`.** Rootowy `package.json` (Angular) deklaruje `packageManager: npm`, przez co yarn wychodził w górę drzewa i odmawiał startu — dlatego `backend/package.json` ma własne `packageManager: yarn@1.22.22`. Przy Node 23 instaluj z `--ignore-engines`.

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
public/assets/img/  zdjęcia gabinetu (zoptymalizowane JPG)
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
