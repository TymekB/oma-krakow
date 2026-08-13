# OMA Kraków — zasady pracy w repo

Sklep na Sylius 2.x (`backend/`), landing w Angularze (`src/`), testy E2E w Playwright (`e2e/`).
Opis funkcji biznesowych i decyzji produktowych trzymamy w `README.md` — ten plik mówi **jak** pisać kod.

## Architektura: DDD + Hexagonal + CQRS + Event-Driven

Każda nowa funkcja biznesowa to **moduł** w `backend/src/<Modul>/` z czterema warstwami.
Wzorce do naśladowania: `SiteContent/`, `Payment/PayU/`, `Checkout/ApplePay/`.

```
backend/src/<Modul>/
    Domain/            Model/ ValueObject/ Event/ Exception/ Repository/ Service/ Port/
    Application/       Command/ CommandHandler/ Query/ QueryHandler/ Event/ EventListener/ View/
    Infrastructure/    Doctrine/ Http/ Sylius/
    UI/                Http/Controller/ Http/Request/ Twig/
```

### Domain
- **ZERO** zależności od Symfony, Doctrine, Syliusa i innych modułów. Import z `App\<InnyModul>\` w `Domain/` to błąd.
- Rich model — agregat ma zachowania i chroni swoje niezmienniki. Modyfikacja stanu tylko przez metody agregatu, nigdy setterem z zewnątrz.
- Value Objects zamiast prymitywów (`ContentKey`, `ContentValue`, …): `final readonly`, walidacja w konstruktorze, named constructor (`fromString()`, `create()`), porównanie przez wartość.
- Porty (interfejsy) definiujemy **tu**, nie w `Infrastructure/`. Repozytorium bez suffiksu `Interface` (`SiteContentRepository`), implementacja w `Infrastructure/Doctrine/Repository/` z prefiksem `Doctrine`.
- Mapowanie Doctrine dla modelu domenowego przez XML/atrybuty w `Infrastructure/Doctrine/Mapping/` — model zostaje czysty.

### Application
- Handlery są **cienkie**: rozpakuj Command → zawołaj domenę → opublikuj zdarzenia. Logika biznesowa w handlerze to błąd, jej miejsce jest w agregacie albo w `Domain/Service/`.
- Commandy i Query: `final readonly`, implementują `App\Shared\Application\Bus\Command` / `Query`, walidują się same (`webmozart/assert` albo Symfony Asserts).
- Handler: `#[AsMessageHandler(bus: 'oma.command_bus')]` / `'oma.query_bus'`.
- `View/` mapuje domenę na tablicę dla HTTP — statyczne `fromX()`. Formaty daty i JSON nie mają wstępu do domeny.

### Infrastructure
- Tylko adaptery: Doctrine, HTTP, adaptery do Syliusa. Wiązanie port → adapter w `config/services.yaml` (sekcja „Porty → adaptery").
- Test dobrej separacji: podmiana bazy albo dostawcy płatności nie rusza `Domain/`.

### UI
- Kontroler przyjmuje Request DTO, dispatchuje Command/Query, zwraca `View`. Nie zawiera logiki.
- `#[Route]` i `#[IsGranted]` jako atrybuty. Kontroler nie łapie wyjątków mapowanych globalnie.

### Zdarzenia
- **Domain Event** (`Domain/Event/`) — coś się stało w agregacie (`SiteContentChanged`). Agregat nagrywa je traitem `RecordsDomainEvents`, handler zwalnia przez `releaseEvents()` i publikuje `EventPublisher`.
- **Application Event** (`Application/Event/`) — skutek przypadku użycia, nie zmiana w agregacie (`ContentBatchEdited`). Implementuje `ApplicationEvent`.
- Side-effecty (maile, logi, rewizje) to `Application/EventListener/`, **nigdy** kod w domenie ani w handlerze komendy.
- `oma.event_bus` ma `dispatch_after_current_bus`, więc listenery widzą dane po commicie transakcji. Nie obchodź tego ręcznym `flush()`.

### Komunikacja między modułami
Wyłącznie przez `Application` — dispatch Command/Query na magistrali albo nasłuch na Application Event. Nigdy import z `Domain/` innego modułu.

### Kiedy moduł, a kiedy nie
Punkty rozszerzeń narzucone przez Syliusa zostają płasko tam, gdzie ich szuka framework: `Entity/`,
`Form/Extension/`, `Grid/`, `Menu/`, `Twig/`, `Security/` i dekoratory fabryk w `Factory/`.
Dekorator fabryki Syliusa nie udaje agregatu. Ale gdy funkcja ma własne reguły biznesowe, stan
i cykl życia — idzie do modułu, nie do `src/Service/`.

## Komentarze w kodzie

Bez komentarzy opisowych — narracyjnych `//` i bloków tłumaczących, co robi kod. Nazwy klas, metod
i zmiennych mają to wyjaśniać. Dotyczy też docblocków w stylu „ta metoda liczy X".

Dozwolone: PHPDoc wymagany przez PHPStan (`@param`, `@return`, `@throws`, `@var`, typy generyczne
i array shapes), atrybuty, oraz krótka notka `TODO`/`FIXME`. Wyjątkowo zostaje komentarz opisujący
**nieoczywisty powód** decyzji, gdy naprawdę nie da się go wyrazić nazwą (jak przy `dispatch_after_current_bus`
w `oma_messenger.yaml`) — nie jest to zaproszenie do opisywania oczywistości.

## PHP 8.3 / Symfony 7.4 / Sylius 2.2 / PHPStan level 9

`declare(strict_types=1)` w każdym pliku. `final readonly`, constructor promotion, atrybuty zamiast
adnotacji, enumy, match, `#[Override]`, `#[Autowire]`, `#[AsDecorator]`. Zakaz: `@`, `eval()`,
`extract()`, `die()`/`exit()` w produkcji, `==`, nadużywane `empty()`.

## Quality check

Lokalny PHP to 8.1, a projekt wymaga 8.3 — **wszystko przez kontener**:

```bash
docker exec oma-shop-app-1 sh -lc 'cd /app && vendor/bin/phpstan analyse --no-progress'
docker exec oma-shop-app-1 sh -lc 'cd /app && vendor/bin/phpunit'
docker exec oma-shop-app-1 sh -lc 'cd /app && vendor/bin/ecs check'
docker exec oma-shop-app-1 sh -lc 'php bin/console doctrine:migrations:migrate --no-interaction'
```

Kontener trzyma cache `prod`, więc po zmianie konfiguracji DI: `php bin/console cache:clear`
i `docker restart oma-shop-app-1` — inaczej testy E2E badają stary kontener usług.

E2E wymagają Node 20+, a domyślny w systemie to 16:

```bash
PATH=/opt/homebrew/opt/node@23/bin:$PATH npx playwright test e2e/admin.spec.ts
```

## Testy

- Nazwy metod od `testShould…`, struktura `// Given` / `// When` / `// Then` (dla wyjątków `// When & Then`).
- 3+ podobnych przypadków → `#[DataProvider]` z opisowymi kluczami, nie kopiowane testy.
- Obiekty domenowe w testach z fixture/Object Mother, nie składane inline.
- Zmiana widoczna w panelu albo w sklepie dostaje test w `e2e/`.
