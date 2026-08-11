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

**Skrypty sklepu nie mogą wykonać się drugi raz.** Sylius renderuje hook `#javascripts` na końcu
`<body>`, a Turbo Drive przy każdej nawigacji na nowo wykonuje skrypty z body. Nasz bundle
(`app-shop-entry`) startuje w `bootstrap.js` własną aplikację Stimulusa, więc każde wejście na stronę
produktu dokładało kolejną instancję — jedno kliknięcie „Dodaj do koszyka" dodawało tyle sztuk, ile
było nawigacji (zgłoszone jako „klikam raz, dodaje 6"). Dlatego tag skryptu ma
`data-turbo-eval="false"`, a inline'owy skrypt w `templates/shop/javascripts.html.twig` pilnuje się
flagą na `window`. Pilnuje tego test `shop.spec.ts` → „po kilku nawigacjach Turbo jedno kliknięcie
dodaje jedną sztukę". Panelu to nie dotyczy — admin chodzi na pełnych przeładowaniach.

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

Klucze bramek podaje się przez zmienne środowiskowe czytane w `backend/compose.yaml`:

```bash
OMA_PAYU_POS_ID=... docker compose up -d app
```

**Sekrety trzymamy w `backend/.env.local`** — repozytorium jest publiczne, a `backend/.env` i reszta
`.env.*` są w nim wersjonowane, więc mogą zawierać wyłącznie puste wartości albo publiczne klucze
testowe. `.env.local` jest w `.gitignore`, katalog projektu montujemy do kontenera, a Symfony
nadpisuje nim `.env` — dlatego wystarczy `docker compose up -d app`, bez dodatkowych flag:

```bash
# backend/.env.local
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

Kluczy Google celowo nie ma w `compose.yaml` w formie `${OMA_GOOGLE_CLIENT_ID:-}` — pusta zmienna
środowiskowa kontenera wygrywa z `.env.local` i Symfony nigdy by go nie wczytał.

**Logowanie Google** — w Google Cloud Console dodaj *Authorized redirect URI*
`http://localhost:8080/sklep/connect/google/check` oraz produkcyjny odpowiednik na `/sklep/connect/google/check`.
Dopóki klucze są puste, przycisk „Kontynuuj z Google" się nie renderuje, a `/sklep/connect/google`
zwraca 404 (zamiast wysypywać się na pustym kluczu). Dopóki ekran zgody OAuth jest w trybie
*Testing*, zalogują się tylko konta z listy użytkowników testowych.

**PayU (główna bramka)** — metoda `payu` jest włączona i pierwsza na liście, na kluczach
**publicznego POS-u testowego PayU** (sandbox, PLN: `pos_id`/`client_id` `300746`). Klucze siedzą
w `.env` i w `compose.yaml` (`OMA_PAYU_*`), a w bazie w zaszyfrowanym `gatewayConfig` — produkcyjne
podmienia się w panelu (*Konfiguracja → Metody płatności → PayU*) albo przez `OMA_PAYU_*` przy
`make reset`. Środowisko wybiera się polem *Środowisko* (`sandbox` → `secure.snd.payu.com`,
`production` → `secure.payu.com`).

Przepływ: checkout → `capture` tworzy zamówienie w PayU (REST `v2_1/orders`) → redirect na
`redirectUri` → powrót na `continueUrl` (`/sklep/order/after-pay/{hash}`) odpytuje status zamówienia
→ webhook `POST /payment-methods/payu` domyka płatność. Webhook weryfikuje nagłówek
`OpenPayu-Signature` (`md5(body + drugi klucz)`); zły albo brakujący podpis to 403 i nic się nie zapisuje.
Karta testowa: `4444333322221111`, `12/29`, CVV `123` (odrzucenie: `5000105018126595`).

**PayU na localu nie dostanie webhooka** — `notifyUrl` wskazuje na `localhost:8080`, więc PayU nie ma
jak się dobić. Płatność i tak zmieni stan po powrocie z bramki (odpytanie statusu na `continueUrl`).
Do testów webhooka albo tunel (`cloudflared`/`ngrok` + `OMA_DEFAULT_URI`), albo ręcznie podpisany POST
na `/payment-methods/payu`.

Metody PayU można dosiać do istniejącej bazy bez czyszczenia danych (osobna suita fixtur, bez purgera):

```bash
docker compose exec app php bin/console sylius:fixtures:load payu -n
```

**Apple Pay** to druga metoda na tej samej bramce (`payu_apple_pay`), z ustawionym polem
*Wymuszona metoda płatności* = Apple Pay. Zamówienie leci do PayU z `payMethods.payMethod = {type: PBL, value: jp}`
i arkusz Apple Pay pokazuje **PayU na swojej domenie** — dlatego nie potrzebujemy ani certyfikatu
Merchant Identity, ani pliku `apple-developer-merchantid-domain-association.txt`, ani zgody Apple
Developer Program. To jedyna wersja Apple Pay, którą da się tu zrobić bez tych rzeczy; wariant
z tokenem (Apple Pay JS + `authorizationCode`) wymaga certyfikatów od PayU i konta Apple Developer.

Uwaga na wartości PayU: **`jp` to Apple Pay, a `ap` to Google Pay** (wbrew intuicji — potwierdzone
przez `GET /api/v2_1/paymethods` na sandboxie, gdzie oba są `ENABLED`).

Apple Pay pokazuje się w checkoucie tylko wtedy, gdy przeglądarka je obsługuje — skrypt
w `templates/shop/apple_pay/script.html.twig` chowa metodę, gdy `window.ApplePaySession.canMakePayments()`
jest niedostępne (Chrome, Firefox, Windows). Sprawdzenie musi być po stronie klienta, bo o dostępności
decyduje urządzenie i Wallet, a nie User-Agent.

Do faktycznego zapłacenia potrzebne są: Safari na urządzeniu Apple, region wspierający Apple Pay,
[konto sandbox testera](https://developer.apple.com/apple-pay/sandbox-testing/) (App Store Connect →
Users and Access → Sandbox → Testers), karta testowa Apple dodana ręcznie do Wallet oraz **HTTPS**
(czyli nie `localhost` — potrzebny tunel i `OMA_DEFAULT_URI`).

### Apple Pay express checkout (adres z arkusza zamiast formularza)

Moduł `src/Checkout/ApplePay/` pozwala kupić bez wypełniania adresu: arkusz Apple Pay oddaje kontakt
i adres wysyłki, a my składamy z tego zamówienie i od razu wchodzimy w płatność.

W koszyku przycisk siedzi **u góry** — zaraz pod nagłówkiem, nad listą produktów i nad podsumowaniem
(`shop/apple_pay/express.html.twig`, priorytet 90 w hooku `sylius_shop.cart.index.content.form.sections`,
czyli za `flashes` 200 i `header` 100, przed `general` 0). Chodzi o to, żeby najszybsza ścieżka była
pierwszą rzeczą, jaką klient widzi, zanim zacznie klikać „Przejdź do kasy" i formularz adresu.
Drugie miejsce to nad formularzem adresu w checkoucie — dla kogoś, kto już tam trafił. Oba hooki
w `config/packages/oma_apple_pay.yaml`.

Cała zachęta w koszyku startuje z `hidden` i odsłania ją JS dopiero po `ApplePaySession.canMakePayments()`
— na Chrome czy Windows nie zostaje nagłówek obiecujący płatność bez przycisku, którym można ją zrobić.

Trzy endpointy (`src/Checkout/ApplePay/UI/Http/Controller/ApplePayController.php`):

| Endpoint | Po co |
|---|---|
| `POST /sklep/apple-pay/merchant-session` | walidacja merchanta u Apple, certyfikatem Merchant Identity |
| `POST /sklep/apple-pay/shipping-methods` | metody wysyłki i ich ceny dla adresu wybranego w arkuszu |
| `POST /sklep/apple-pay/order` | adres z arkusza → adres + wysyłka + płatność → `completed`, zwraca URL płatności |

**To wymaga rzeczy, których jeszcze nie mamy** — bez nich arkusz się nie otworzy:

1. Apple Developer Program, Merchant ID i **certyfikat Merchant Identity** (`APPLE_PAY_CERT_PATH`,
   `APPLE_PAY_CERT_KEY_PATH`, `APPLE_PAY_CERT_PASSPHRASE`),
2. plik `apple-developer-merchantid-domain-association.txt` z Apple wrzucony do
   `backend/public/.well-known/` (Caddy serwuje go statycznie),
3. HTTPS na zarejestrowanej domenie — Apple Pay JS nie działa na `localhost`,
4. Apple Pay włączone na POS PayU **z tokenami** (osobne zgłoszenie do supportu PayU).

Dopóki `APPLE_PAY_MERCHANT_ID` jest puste, przycisk się nie renderuje i sklep zachowuje się jak wcześniej.

Punkt 4 ma zabezpieczenie: token z arkusza leci do PayU jako `payMethods.payMethod.authorizationCode`,
a gdy PayU go odrzuci (na sandboxie `ERROR_VALUE_INVALID` / `APPLE_PAY_CLIENT_ERROR`, bo POS nie ma
włączonych tokenów), `CapturePaymentRequestHandler` **ponawia zamówienie bez tokenu** jako pay-by-link.
Klient traci na tym jedno dodatkowe potwierdzenie na stronie PayU, ale checkout nie pada, a adres i tak
przyszedł z arkusza. Po włączeniu tokenów u PayU fallback przestanie się odpalać sam z siebie.

Endpointy da się testować bez Safari i bez certyfikatów — `e2e/apple-pay-express.spec.ts` woła je
z sesji koszyka takim payloadem, jaki wysyła arkusz (łącznie z okrojonym kontaktem, który Apple daje
na etapie wyboru wysyłki).

**Stripe (karta, BLIK, Przelewy24)** — metoda płatności `stripe` jest **wyłączona**, bo w bazie
zapisane są zaszyfrowane puste klucze. Żeby ją uruchomić: wpisz klucze testowe w panelu
(*Konfiguracja → Metody płatności → „Karta, BLIK, Przelewy24…"*), włącz metodę i włącz BLIK
w Stripe Dashboard dla PLN. Bramka jest ustawiona na `stripe_web_elements`, czyli osadzony
Payment Element — kod BLIK wpisuje się na stronie sklepu, bez przekierowania.

## Maile o zamówieniach i sekcja „Zdarzenia"

Po złożeniu zamówienia na adres administratora leci mail z numerem zamówienia, danymi kupującego
(imię, e-mail, telefon), listą kupionych produktów, sumą oraz metodą i stanem płatności, plus
przycisk otwierający zamówienie w panelu. Wysyłką zajmuje się `App\Notification\OrderPlacedAdminNotifier`
podpięty pod `sylius.order.post_complete` — czyli **ten sam moment, w którym klient dostaje swoje
potwierdzenie**: zamówienie jest złożone, ale niekoniecznie opłacone. Dlatego w mailu jest stan
płatności, a nie założenie, że pieniądze wpłynęły.

Adres odbiorcy: `ADMIN_NOTIFICATION_EMAIL`, a gdy puste — **adres kontaktowy kanału**
(*Konfiguracja → Kanały*). Na produkcji zmiennej nie ma w `compose.prod.yaml` celowo, z tego samego
powodu co klucze Google: pusta zmienna kontenera wygrałaby z `.env.local`.

*Konfiguracja → **Zdarzenia*** (`/admin/zdarzenia`) pozwala włączyć i wyłączyć każdy z maili:
powiadomienie dla administratora, potwierdzenie zamówienia i wysyłki dla klienta oraz wiadomość
z formularza kontaktowego. Ustawienia siedzą w `oma_notification_setting`; brak wiersza znaczy
„włączone", więc świeża baza zachowuje się jak dotąd.

Blokada działa w **dekoratorze `sylius.email_sender`** (`App\Notification\EnabledEmailsSender`), a nie
w poszczególnych listenerach — dzięki temu jedno miejsce gasi maile niezależnie od tego, czy wysyła
je nasz kod, czy Sylius. Świadomie **nie da się wyłączyć** maili od resetu hasła i weryfikacji konta:
wyłączenie ich zablokowałoby rejestrację i odzyskiwanie dostępu. Ręczne „wyślij ponownie"
z widoku zamówienia też działa mimo wyłączonego zdarzenia — to jawna decyzja obsługi, nie automat.

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

**Worker mode FrankenPHP jest wyłączony i musi taki zostać** — psuł dodawanie do koszyka. Kernel
Symfony żyje wtedy między requestami, a `Sylius\Component\Channel\Context\CachedPerRequestChannelContext`
trzyma encję kanału w `SplObjectStorage` kluczowanym obiektem `Request`. Klasa zakłada, że proces
kończy się razem z requestem; w worker mode runner FrankenPHP po każdym przebiegu woła
`gc_collect_cycles()`, EntityManager jest resetowany, a kontekst potrafi oddać **odłączony** kanał.
Efekt: `POST /sklep/_components/sylius_shop:product:add_to_cart_form/addToCart` kończy się 500 z
`ORMInvalidArgumentException: A new entity was found through the relationship 'Order#channel'`
przy tworzeniu nowego koszyka. Widać to tylko jako migające 500 w XHR-ze Live Componentu, a koszyk
zostaje w połowicznym stanie — przez co `/sklep/checkout/` zwraca potem 404.

To jedyna klasa w Syliusie z tym wzorcem (`grep -rl SplObjectStorage vendor/sylius`), więc łatka
punktowa jest możliwa, ale nie warto jej trzymać na produkcji bez potrzeby — bez worker mode
i tak jest z zapasem. Zmierzone dla `/sklep/` (`bench.sh /sklep/ 24 2`):

| | matt197 (1 vCPU) | kate123 bez worker mode | kate123 z worker mode |
|---|---|---|---|
| throughput | 5,7 rps | 21,0 rps | 34,6 rps (ale psuje koszyk) |
| p50 | 0,27 s | 0,09 s | 0,03 s |
| p95 | 0,54 s | 0,10 s | 0,07 s |

Regresję łapie `e2e/shop.spec.ts` → „dodanie produktu do koszyka nie zwraca 5xx". Uwaga na starą
wersję tego testu: asercja na nazwie produktu brała pierwszy `h1`, którym jest pusty tytuł
offcanvasu, więc test przechodził mimo wywalonego koszyka.

Transporty messengera idą na `doctrine://default`, a konsumuje je **kontener `worker`** z osobnym
limitem pamięci. Wcześniej robił to cron co 5 minut, bo na 1 vCPU stały worker był główną przyczyną
przeciążenia; tutaj kosztuje ~120 MB i nie ma powodu opóźniać promocji katalogowych o kilka minut.
`remote-deploy.sh` kasuje starego `/etc/cron.d/oma-messenger`, jeśli został po poprzednim wdrożeniu.

`opcache` ma 256 MB (`deploy/php-prod.ini`), ale **JIT zostaje wyłączony mimo wolnej pamięci** —
zmierzony A/B na tym samym obrazie dał 31,2–32,5 rps z `jit=tracing` wobec 33,3–35,0 rps z `jit=off`.
W worker mode gorące ścieżki i tak siedzą już w opcache, a JIT dokłada tylko narzut.
`memory_limit` musi zostać na 512 MB — niżej `opcache.preload` nie wchodzi i kontener wpada
w pętlę restartów.

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

`e2e/payu.spec.ts` przechodzi checkout do końca i sprawdza, czy zamówienie ląduje w sandboxie PayU —
czyli **wymaga internetu** i tworzy prawdziwe zamówienie testowe w PayU. Sandbox PayU odcina nadmiarowy
ruch (HTTP 429), więc nie warto go puszczać w pętli.

E2e trzeba puszczać na stacku w `APP_ENV=prod` (domyślnym). W `make dev` requesty są na tyle wolniejsze,
że asercje `toHaveURL` wchodzą w timeout.

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
