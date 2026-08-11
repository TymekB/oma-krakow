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

Transporty messengera idą na **RabbitMQ na obu środowiskach** (`symfony/amqp-messenger`, rozszerzenie
`amqp` w obrazie). Nie jest to kosmetyka: Sylius planuje start i koniec **promocji katalogowej** przez
`DelayStamp` — wylicza opóźnienie do daty startu i do daty końca i wysyła komunikaty z tym
opóźnieniem. Respektują je tylko transporty asynchroniczne. Na `sync://` opóźnienie jest ignorowane,
komunikaty wykonują się od razu przy zapisie i **daty promocji przestają cokolwiek znaczyć**.

Jak to wygląda w RabbitMQ: komunikat z opóźnieniem nie leci od razu na kolejkę `main`, tylko na
tymczasową kolejkę `delay_main__<ms>_delay` z `x-message-ttl` równym opóźnieniu. Po wygaśnięciu TTL
broker przerzuca go przez dead-letter exchange na `main`, a kolejka opóźniająca kasuje się sama.
Dlatego zaplanowaną promocję widać w panelu RabbitMQ jako osobną kolejkę z jedną wiadomością:

```bash
docker compose exec rabbitmq rabbitmqctl list_queues name messages
```

Sprawdzone w tym stacku (RabbitMQ 3.13.7): opóźnienie do **roku** przechodzi bez problemu, więc
praktycznego limitu na daty promocji nie ma (błąd `PRECONDITION_FAILED` na `x-expires` pojawia się
przy wartościach rzędu 10 lat).

**Promocje z terminem wymagają działającego konsumenta** — i to jest najczęstsza przyczyna „daty nie
działają". Worker chodzi z `--time-limit=3600`, czyli po godzinie kończy pracę z kodem 0. Bez
`restart: unless-stopped` nie wstaje ponownie: komunikaty spokojnie lądują na `main` i tam zostają,
bo nikt ich nie zdejmuje. Oba compose'y mają tę politykę ustawioną — jeśli kiedyś ją usuniesz, daty
promocji przestaną działać po pierwszej godzinie od startu stacku.

```bash
docker compose ps -a worker                      # Exited = promocje z datami nie zadziałają
docker compose logs -f worker
docker compose exec app php bin/console messenger:stats
```

Uwaga na `list_queues consumers` — transport AMQP Symfony **odpytuje** kolejkę (`basic_get`), a nie
subskrybuje, więc zdrowy worker pokazuje tam `0`. Żywotność sprawdzaj stanem kontenera i logiem
`Consuming messages from transports`; tak robi to `deploy/smoke-test.sh`.

Wiadomość, której worker nie potrafi zdeserializować (np. wrzucona ręcznie, bez podpisu), wywala
`messenger:consume` i przy `restart: unless-stopped` daje pętlę restartów. Kolejkę czyści się wtedy
`rabbitmqctl purge_queue main`.

**`hostname: rabbitmq` w obu compose'ach musi zostać.** RabbitMQ trzyma dane w katalogu nazwanym po
węźle (`mnesia/rabbit@<hostname>`), a bez jawnego hostname'a węzeł nazywa się od ID kontenera. Każde
**odtworzenie** kontenera (zmiana obrazu, portu, healthchecku — czyli i część wdrożeń) dawało wtedy
nową nazwę węzła i pusty katalog danych: wolumen zostawał, ale wszystkie trwałe kolejki i zaplanowane
komunikaty stawały się sierotami. Objaw jest zdradliwy, bo nic nie krzyczy — po prostu daty promocji
przestają działać, a `list_queues` pokazuje tylko `main` i `catalog_promotion_removal`. Sprawdzenie:

```bash
docker compose exec rabbitmq rabbitmqctl eval 'node().'   # ma być rabbit@rabbitmq
```

Jeśli zaplanowane komunikaty jednak przepadną, wejdź w panelu w każdą promocję z datą i zapisz ją —
`CatalogPromotionAnnouncer` wysyła wtedy start i koniec od nowa.

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

**Sentry (monitoring błędów) — tylko produkcja.** Bundle `sentry/sentry-symfony` jest
w `config/bundles.php` włączony dla `prod`, ale sam wpis nic nie daje: lokalny docker też chodzi na
`APP_ENV=prod` (`OMA_ENV`), więc jedyne, co trzyma Sentry wyłączone na localu, to **pusty DSN**.
Dlatego `SENTRY_DSN` w `.env` jest puste i **nie wpisujemy go w `backend/.env.local`** — lokalne
błędy nie mają lecieć do projektu ani przepalać limitu zdarzeń. Pusty DSN = brak klienta = SDK nic
nie wysyła i nigdy nie wywala aplikacji. W `compose.yaml` tej zmiennej celowo nie ma, tak jak przy
kluczach Google.

Prawdziwy DSN żyje tylko na produkcji: `OMA_SENTRY_DSN` w `/opt/oma-prod/.env`, stąd
`SENTRY_DSN: ${OMA_SENTRY_DSN:-}` w `deploy/compose.prod.yaml`.

Konfiguracja w `config/packages/sentry.yaml`: `enable_logs: true` (logi obok wyjątków), `environment`
z `%kernel.environment%` oraz `ignore_exceptions` z 404/405 — bez tego każdy bot skanujący sklep
generuje zdarzenie. W `deploy/php-prod.ini` jest `zend.exception_ignore_args = Off`, bez tego stack
trace w Sentry nie ma argumentów funkcji.

Sprawdzenie DSN-u **na serwerze produkcyjnym** (wysyła prawdziwe zdarzenie do projektu):

```bash
docker compose exec app php bin/console sentry:test
```

### Apple Pay express checkout (adres z arkusza zamiast formularza) — ODŁOŻONE

> **Świadomie wyłączone, nie porzucone.** Ten wariant wymaga **Apple Developer Program za 99 USD
> rocznie** (opłata cykliczna — po niewznowieniu certyfikat Merchant Identity przestaje działać
> i przycisk znika) i decyzją właściciela sklepu na to nie idziemy. Kod zostaje, bo nic nie kosztuje:
> `APPLE_PAY_MERCHANT_ID` jest puste, więc `ApplePaySettings::isEnabled()` zwraca `false`, szablony
> nie renderują nic, a endpointów nikt nie woła. Jeden wpis w `.env` włącza to z powrotem.
>
> **Jeśli wracasz do express checkoutu, prawdopodobnie nie chcesz tej drogi.** Apple-specyficzny
> jest tu wyłącznie adapter `AppleMerchantValidator` (walidacja merchanta certyfikatem). Reszta —
> port `ExpressCheckout`, `SyliusExpressCheckout`, endpointy wysyłki i zamówienia, umiejscowienie
> zachęty u góry koszyka — jest wallet-agnostyczna i posłuży bez zmian. Tańsze warianty:
>
> | Droga | Koszt wejścia | Uwaga |
> |---|---|---|
> | **Stripe** | 0 zł | Stripe waliduje merchanta za Ciebie — bez konta Apple Developer i bez Merchant ID. Podmieniasz adapter za portem `ApplePayMerchantValidator`. Stripe jest już w projekcie, wyłączony na pustych kluczach |
> | **Google Pay przez PayU** | 0 zł | `PayUPayMethod::GooglePay = 'ap'` już istnieje. Tryb `TEST` działa bez rejestracji. Pokrywa Chrome, Edge, Firefox, Androida — i Safari też |
> | Apple Pay (ta sekcja) | 99 USD/rok | plus zgłoszenie do PayU o Apple Pay z tokenami |
>
> Niezależnie od portfela: **PayU nie ma gotowego „fast checkout"**, który pomija formularz adresu —
> adres zawsze pochodzi z portfela i to nasz kod składa z niego zamówienie. PayU oferuje natomiast
> tokenizację kart (`TOKC_`, one-click dla powracających), co skraca krok płatności i warto to
> rozważyć osobno.

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

`supportedNetworks` w arkuszu to celowo tylko `visa` i `masterCard` — PayU obsługuje pod 3DS wyłącznie
te dwie sieci. Dopisanie `maestro` sprawiało, że klient mógł wybrać w Wallet kartę, którą PayU potem
odrzuca, i bez powodu wpadał w fallback.

Endpointy da się testować bez Safari i bez certyfikatów — `e2e/apple-pay-express.spec.ts` woła je
z sesji koszyka takim payloadem, jaki wysyła arkusz (łącznie z okrojonym kontaktem, który Apple daje
na etapie wyboru wysyłki).

**Stripe (karta, BLIK, Przelewy24)** — metoda płatności `stripe` jest **wyłączona**, bo w bazie
zapisane są zaszyfrowane puste klucze. Żeby ją uruchomić: wpisz klucze testowe w panelu
(*Konfiguracja → Metody płatności → „Karta, BLIK, Przelewy24…"*), włącz metodę i włącz BLIK
w Stripe Dashboard dla PLN. Bramka jest ustawiona na `stripe_web_elements`, czyli osadzony
Payment Element — kod BLIK wpisuje się na stronie sklepu, bez przekierowania.

## Powiadomienia dla administratora i sekcja „Zdarzenia"

Trzy zdarzenia w sklepie wysyłają maila na adres administratora — każde ma własny notifier
w `App\Notification\` i wspólne rozwiązywanie odbiorcy (`AdminRecipient`):

| Zdarzenie | Wyzwalacz | Co jest w mailu |
|---|---|---|
| Nowe zamówienie | `sylius.order.post_complete` | numer, kupujący, produkty, suma, metoda i stan płatności |
| Nowy użytkownik | `sylius.customer.post_register` | imię, e-mail, telefon, data założenia konta |
| Nowa opinia | `sylius.product_review.post_create` | ocena, tytuł, treść, autor, produkt |

Zamówienie leci **w tym samym momencie, w którym klient dostaje swoje potwierdzenie**: jest złożone,
ale niekoniecznie opłacone. Dlatego w mailu jest stan płatności, a nie założenie, że pieniądze
wpłynęły. Każdy mail ma przycisk otwierający rekord w panelu; link buduje `url()`, czyli bierze
`DEFAULT_URI`, a nie hostname kanału — inaczej na localu wychodziłby adres bez portu.

Adres odbiorcy: `ADMIN_NOTIFICATION_EMAIL`, a gdy puste — **adres kontaktowy kanału**
(*Konfiguracja → Kanały*). Na produkcji zmiennej nie ma w `compose.prod.yaml` celowo, z tego samego
powodu co klucze Google: pusta zmienna kontenera wygrałaby z `.env.local`.

*Konfiguracja → **Zdarzenia*** (`/admin/zdarzenia`) pozwala włączyć i wyłączyć każdy z maili —
trzy powyższe plus potwierdzenie zamówienia i wysyłki dla klienta oraz wiadomość z formularza
kontaktowego. Ustawienia siedzą w `oma_notification_setting`; brak wiersza znaczy „włączone",
więc świeża baza zachowuje się jak dotąd.

Blokada działa w **dekoratorze `sylius.email_sender`** (`App\Notification\EnabledEmailsSender`), a nie
w poszczególnych listenerach — dzięki temu jedno miejsce gasi maile niezależnie od tego, czy wysyła
je nasz kod, czy Sylius. Świadomie **nie da się wyłączyć** maili od resetu hasła i weryfikacji konta:
wyłączenie ich zablokowałoby rejestrację i odzyskiwanie dostępu. Ręczne „wyślij ponownie"
z widoku zamówienia też działa mimo wyłączonego zdarzenia — to jawna decyzja obsługi, nie automat.

## Ceny netto i brutto w panelu

> **Uwaga na konfigurację podatków.** Obie stawki VAT w bazie mają `included_in_price = 0`, czyli
> **cena wpisana w panelu jest ceną netto**, a 23% dolicza się dopiero w koszyku. Produkt z ceną
> `39,00` kosztuje klienta `47,97` i taką kwotę widać dopiero w podsumowaniu koszyka — na karcie
> produktu i liście widnieje `39,00`. Dla sklepu B2C w Polsce to najpewniej nie jest to, o co chodzi
> (konsument ma widzieć cenę końcową), ale zmiana tego to decyzja biznesowa, nie techniczna:
> przestawienie `included_in_price` na `1` **nie przelicza istniejących cen**, tylko zmienia ich
> znaczenie, więc `39,00` z dnia na dzień stanie się ceną brutto i przychód spadnie o 23%. Jeśli
> ceny mają zostać takie, jakie klient płaci dziś, trzeba je najpierw przemnożyć przez `1,23`.

Póki co panel pomaga w tym żyć: pod **każdym** polem ceny (*Cena*, *Cena wyjściowa*, *Cena
minimalna*) w zakładce **Ceny** siedzi lustrzane pole z kwotą po drugiej stronie VAT-u, opisane
`brutto (VAT 23%)`. Działa **w obie strony** — wpiszesz netto, dostajesz brutto; wpiszesz brutto,
przelicza się netto i to ono trafia do zapisu. Lustro nie ma atrybutu `name`, więc nigdy nie leci
w formularzu; jedynym źródłem prawdy zostaje pole Syliusa.

Kierunek nie jest zaszyty na sztywno — bierze się z `included_in_price` rozwiązanej stawki. Gdy
kiedyś przestawicie sklep na ceny brutto, ten sam widget sam zacznie pokazywać `netto (VAT 23%)`,
bez zmiany kodu.

Jak to jest złożone:

| Element | Rola |
|---|---|
| `src/Twig/PriceTaxExtension.php` | `oma_price_tax(variant, channel_code)` — rozwiązuje stawkę przez `TaxRateResolverInterface`, zawężoną do domyślnej strefy podatkowej kanału |
| `templates/bundles/SyliusAdminBundle/shared/helper/channel_pricings.html.twig` | opakowuje panel w `data-oma-price-tax` ze stawką, kierunkiem i etykietą |
| `templates/admin/javascripts.html.twig` | wstrzykuje lustro i pilnuje przeliczeń w obie strony |

Lustro wstrzykuje JS, a nie Twig, bo trzy pola cen renderuje hook (`body` przekazywane do makra),
więc nadpisywanie pojedynczych pól w makrze byłoby martwym kodem. Stąd też `MutationObserver` —
formularz produktu to Live Component i po każdym przerysowaniu lustro trzeba wstawić od nowa.

Stawka pochodzi z **zapisanej** kategorii podatkowej wariantu. Jeśli zmienisz ją w zakładce
*Podatki* i jeszcze nie zapiszesz, lustro liczy po staremu aż do zapisu.

Pokrywa to `e2e/admin-price-net-gross.spec.ts` (oba kierunki, zaokrąglanie groszy, puste pole)
oraz `tests/Twig/PriceTaxExtensionTest.php` (rozwiązywanie stawki, kierunek, formatowanie procentu).

## Zweryfikowane opinie

Opinię o produkcie może wystawić **tylko zalogowany klient**, a opinia autora, który ten produkt
faktycznie kupił, dostaje badge **„Zweryfikowano zakupem"**.

Blokada dla gości siedzi w `access_control` (`security.yaml`) na ścieżce formularza
`/sklep/produkty/{slug}/recenzje/nowa`, a nie w szablonie — adres da się zgadnąć, więc samo ukrycie
przycisku niczego by nie chroniło. Gość, który wejdzie tam wprost, ląduje na logowaniu i po
zalogowaniu wraca na formularz (zapamiętany target path). Trzy miejsca z przyciskiem
„Dodaj swoją recenzję" (podsumowanie produktu, akordeon opinii, lista opinii) renderują dla gościa link
„Zaloguj się, aby dodać opinię" z `_target_path` — jeden szablon
`shop/product_review/add_review_link.html.twig` podpięty pod trzy hooki Syliusa.

Skutek uboczny wymuszenia logowania: autorem jest zawsze konto klienta, więc z formularza znika pole
na e-mail, a w mailu do administratora autor opinii jest zawsze rozpoznany.

Badge wylicza `App\Review\PurchaseConfirmation` — sprawdza, czy autor jest wśród klientów z
**opłaconym i nieanulowanym** zamówieniem zawierającym ten produkt. Lista kupujących jest trzymana
w pamięci per produkt na czas requestu, bo lista opinii pyta o to samo dla każdego wiersza. Twig
sięga po to przez `oma_review_confirmed_by_purchase(review)`.

Testy: `e2e/reviews.spec.ts` — gość odbity na logowanie, zalogowany dodaje opinię bez pola e-mail,
badge pojawia się po opłaconym zamówieniu i nie pojawia się bez zakupu.

## Crony i ich monitoring (Healthchecks)

Do tej pory sklep nie miał żadnego zadania cyklicznego — stary `/etc/cron.d/oma-messenger` zniknął,
gdy konsument messengera stał się stałym serwisem. Teraz w Compose są trzy joby i pilnowanie ich
działania:

| Slug | Kiedy | Co robi |
|---|---|---|
| `expired-carts` | 03:15 | `sylius:remove-expired-carts` — sprząta porzucone koszyki |
| `db-backup` | 03:00 | `bin/oma-db-backup` — `mysqldump` do wolumenu `db_backup`, retencja 7 dni |
| `unpaid-orders` | 03:30 | `sylius:cancel-unpaid-orders` — anuluje nieopłacone zamówienia |

Harmonogram trzyma **Ofelia** (serwis `cron`) — zadania są etykietami `ofelia.job-exec.*` na serwisie
`app`, więc widać je obok aplikacji i nie ma osobnego crontaba ani przebudowy obrazu. Ofelia wchodzi
do działającego kontenera przez `docker exec`, dlatego montuje `docker.sock` tylko do odczytu.

Cron u Ofelii ma **sześć pól** (pierwsze to sekundy), więc `0 0 3 * * *` to 03:00:00. Kontener dostaje
`TZ=Europe/Warsaw` i checki w Healthchecks mają tę samą strefę — inaczej Ofelia liczyłaby w UTC,
a monitoring czekałby na ping dwie godziny wcześniej i sypał fałszywymi alarmami. Zmieniając
harmonogram zmień go w obu miejscach.

Każde zadanie jest owinięte w `bin/oma-cron <slug> <komenda>`: pinguje `/start`, uruchamia komendę,
a potem pinguje kod wyjścia i przesyła wyjście komendy jako treść. Healthchecks alarmuje więc
w dwóch przypadkach: **komenda padła** albo **cron w ogóle się nie odpalił**. Bez `HEALTHCHECKS_PING_URL`
wrapper po prostu odpala komendę, więc lokalne wywołania ręczne działają jak dotąd.

Kontener Healthchecks dostaje `LANG=C.UTF-8` i to nie jest ozdoba: uwsgi startuje osadzony
interpreter Pythona bez ustawionego locale, więc `SITE_NAME` z półpauzą dekodowało się na surogaty
i **każda strona panelu zwracała 500** (pingi i API działały normalnie, bo nie renderują szablonu).

Panel Healthchecks: http://localhost:8010 (na produkcji tylko przez `127.0.0.1`, wystaw tunelem).
Pierwsze uruchomienie zakłada konto, projekt, klucz pingowania i trzy checki z właściwymi
harmonogramami:

```bash
cd backend                                            # albo /opt/oma-prod na serwerze
OMA_HC_ADMIN_PASSWORD='...' ../deploy/healthchecks-bootstrap.sh
# → PING_URL=http://healthchecks:8000/ping/<klucz>
```

Marginesy (*grace*) są dobrane do tego, ile kosztuje spóźnienie: kopia bazy ma godzinę, sprzątanie
koszyków i anulowanie zamówień po dwie. Oba sprzątające joby są dobowe, bo progi Syliusa to 2 i 5 dni
— częstsze uruchamianie nic nie zmienia, a wąskie okno alertu tylko szumi.

Zwrócony `PING_URL` wpisz do `.env` jako `OMA_HC_PING_URL` i przeładuj `app` oraz `cron`. Skrypt jest
idempotentny — klucz raz wygenerowany zostaje, a kolejne uruchomienia tylko poprawiają harmonogramy
checków. Alerty idą mailem na konto administratora Healthchecks (kanał `email` zakłada się razem
z kontem); w devie łapie je Mailpit, na produkcji ustaw `OMA_HC_EMAIL_*`.

Nowe zadanie dodaje się w dwóch miejscach: etykieta `ofelia.job-exec.<slug>` w Compose plus wpis
w `JOBS` w `deploy/healthchecks-bootstrap.sh`. Bez wpisu w skrypcie check i tak powstanie przy
pierwszym pingu (`create=1`), ale z domyślnym okresem 1 dnia — dlatego harmonogramy trzymamy w kodzie.

Kopie bazy leżą w wolumenie `db_backup` (`sylius_oma-<data>.sql.gz`). To kopia **lokalna**, na tym
samym dysku co baza — chroni przed pomyłką w danych, nie przed utratą serwera. Wysyłka poza VPS jest
do zrobienia osobno.

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
4. odpala `deploy/smoke-test.sh` — stan kontenerów `app`/`worker`/`mysql`/`rabbitmq`, potwierdzenie
   że worker wszedł w pętlę konsumowania, oraz `/`, `/sklep/`, `/admin/login`.

Obraz jest self-contained: `vendor/`, assety Encore i landing są w nim wypalone, więc na serwerze
nie ma kodu z repo ani bind-mountów. Trwałe dane to wolumeny: baza, `public/media`, klucze JWT
i sesje.

Skrypty wdrożeniowe są w plikach, nie inline w YAML-u, bo `docker compose run` przechwytuje stdin —
skrypt podany przez `bash -s` zostałby po części zjedzony przez kontener migracji i reszta kroków
nigdy by się nie wykonała (przy zerowym kodzie wyjścia, czyli fałszywie „zielono").

### RabbitMQ na produkcji

Produkcja stoi na tym samym brokerze co local — to świadoma decyzja o parytecie środowisk: kolejki
zachowują się identycznie tam, gdzie testujesz, i tam, gdzie sprzedajesz.

Wymagania po stronie serwera:

- **`OMA_RABBITMQ_PASSWORD` w `/opt/oma-prod/.env`** (obowiązkowe — bez niego `compose config` się nie
  złoży). Użyj hasła alfanumerycznego: trafia do DSN-a `amqp://user:haslo@rabbitmq:5672/...`, więc
  `@`, `:` albo `/` rozjechałyby URL. Login to `OMA_RABBITMQ_USER` (domyślnie `oma`); użytkownik
  `guest` nie jest używany.
- Pamięć: `OMA_RABBITMQ_MEM` (domyślnie `512m`) plus watermark w `deploy/rabbitmq-oma.conf`
  (`256MiB` absolutnie, nie relatywnie — wartość relatywna liczyłaby się od RAM-u maszyny i przy
  `mem_limit` skończyłaby się OOM-em).
- Panel RabbitMQ wisi na `127.0.0.1:15672`, czyli tylko przez tunel SSH — Mikrus wystawia publicznie
  jedynie dwa porty TCP i broker do nich nie należy.

**Przy pierwszym wdrożeniu po tej zmianie** transport przechodzi z `doctrine://default` na AMQP.
Komunikaty zaplanowane wcześniej siedzą w tabeli `messenger_messages` i nikt ich już nie odbierze —
nowy worker patrzy tylko na RabbitMQ. Sprawdź, czy coś tam czeka:

```bash
docker compose exec -T mysql mysql -usylius -p"$OMA_DB_PASSWORD" sylius_oma \
  -e "select queue_name, count(*), min(available_at) from messenger_messages group by queue_name"
```

Jeśli są tam zaplanowane starty/końce promocji, po wdrożeniu wejdź w panelu w każdą promocję z datą
i zapisz ją — Sylius przy zapisie wysyła zdarzenia od nowa (`CatalogPromotionAnnouncer`), tym razem
na RabbitMQ. Tabelę `messenger_messages` można potem wyczyścić.

### Hostname kanału

Sylius rozpoznaje kanał po nagłówku `Host`. Kanał `OMA_WEB` ma w bazie `hostname` ustawiony na
domenę produkcyjną — bez tego `/sklep/` zwraca 404. Przy zmianie domeny trzeba zmienić dwie rzeczy:
`hostname` w `sylius_channel` oraz `OMA_DEFAULT_URI` w `.env`. Dlatego smoke test uderza z właściwym
nagłówkiem `Host`, a nie po `localhost`.

### Konfiguracja serwera

Sekrety żyją **tylko** w `/opt/oma-prod/.env` (nie w CI, nie w repo) — `APP_SECRET`, hasła do bazy,
`JWT_PASSPHRASE`, klucze Google/Stripe, `MAILER_DSN`, `OMA_SENTRY_DSN`. Po zmianie: `docker compose up -d`.

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
ruchu stack bierze ~600 MB z 8 GB (`app` ~290 MB, `worker` ~120 MB, `mysql` ~175 MB), do czego
dochodzi `rabbitmq` z limitem `512m` i watermarkiem `256MiB`.

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
