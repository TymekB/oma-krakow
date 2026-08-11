# Architektura kodu własnego

Kod pisany na potrzeby OMA (katalog `src/`, poza wygenerowanymi encjami Syliusa) jest
ułożony w kontekstach z podziałem na warstwy: **DDD + Hexagonal + CQRS + Event Driven**.

```
src/
├── Shared/                     Shared Kernel — magistrale, zdarzenia, zegar
├── SiteContent/                Kontekst: treść landingu i historia zmian
├── Payment/PayU/               Kontekst: płatności PayU
├── Entity/                     Encje Syliusa (wymagane przez framework)
├── Controller/, Form/, Grid/, Menu/, Review/, Security/, Twig/
                                punkty rozszerzeń Syliusa — zostają na miejscu
```

## Warstwy

| Warstwa | Zawiera | Może zależeć od |
| --- | --- | --- |
| `Domain` | agregaty, Value Objecty, zdarzenia domenowe, serwisy domenowe, **porty** | niczego (czysty PHP) |
| `Application` | komendy, zapytania, handlery, zdarzenia aplikacyjne, listenery, widoki | `Domain` |
| `Infrastructure` | **adaptery**: Doctrine, HTTP, Sylius | `Domain`, `Application` |
| `UI` | kontrolery HTTP, formularze, providery Syliusa | `Application` |

Porty (interfejsy) mieszkają w domenie, adaptery w infrastrukturze. Wiązanie
port → adapter jest w jednym miejscu: `config/services.yaml`, sekcja
„Porty → adaptery".

## CQRS

Trzy własne magistrale Symfony Messenger (`config/packages/oma_messenger.yaml`):

| Magistrala | Middleware | Do czego |
| --- | --- | --- |
| `oma.command_bus` | `dispatch_after_current_bus`, `validation`, `doctrine_transaction` | komendy — zmiana stanu, jedna transakcja na przypadek użycia |
| `oma.query_bus` | `validation` | zapytania — zwracają widoki, nie zmieniają stanu |
| `oma.event_bus` | `allow_no_handlers`, `dispatch_after_current_bus` | zdarzenia |

Porty magistral: `Shared\Application\Bus\{CommandBus,QueryBus}`,
adaptery: `Shared\Infrastructure\Bus\Messenger*`.

Zapytania są generyczne — `Query<TResult>` niesie typ wyniku, więc `QueryBus::ask()`
zwraca konkretny typ pod PHPStanem na poziomie 9:

```php
/** @implements Query<array<string, string>> */
final readonly class GetContentMap implements Query {}
```

Komendy zwracają `void`. Wyjątkiem jest `ResultingCommand<TResult>` — dla przypadków,
gdzie wołający potrzebuje wyniku operacji (np. liczby przywróconych kluczy).

## Zdarzenia

Rozróżnienie jak w regułach CR:

- **zdarzenia domenowe** (`Domain/Event/`) — *coś się stało* w agregacie
  (`SiteContentChanged`, `SiteContentRemoved`, `PayUPaymentStatusChanged`).
  Agregat rejestruje je przez trait `RecordsDomainEvents`, handler odbiera je
  przez `releaseEvents()` i publikuje.
- **zdarzenia aplikacyjne** (`Application/Event/`) — skutek przypadku użycia
  (`ContentBatchEdited`, `ContentRestoredFromRevision`).

Publikacja idzie przez port `EventPublisher`. Adapter stempluje wiadomości
`DispatchAfterCurrentBusStamp`, więc **zdarzenia wychodzą po commicie transakcji**,
a nie w jej trakcie. Dlatego `dispatch_after_current_bus` musi być na obu
magistralach — komend i zdarzeń.

Listener, który sam musi coś zapisać, wysyła komendę na `oma.command_bus`
(patrz `RecordRevisionOnContentEdited` → `RecordRevision`). Dzięki temu zapis
ma własną transakcję.

## SiteContent

Historia rewizji jest napędzana zdarzeniami: handler wsadowej edycji publikuje
`ContentBatchEdited`, listener zamienia to na komendę `RecordRevision`.
Pojedynczy zapis (`PUT /admin/landing-content/{key}`) nie publikuje tego zdarzenia,
więc — tak jak dotychczas — nie tworzy wpisu w historii. Przywrócenie wersji
też nie dopisuje wpisu.

Walidacja klucza i treści siedzi w Value Objectach (`ContentKey`, `ContentValue`),
nie w kontrolerze. Kontroler mapuje wyjątki domenowe na kody HTTP.

Model domenowy nie ma atrybutów Doctrine — mapowanie jest w XML-u
(`SiteContent/Infrastructure/Doctrine/Mapping/`), a Value Objecty są zapisywane
przez własne typy DBAL (`Infrastructure/Doctrine/Type/`). Schemat bazy się nie zmienił:
te same tabele `oma_landing_content` i `oma_landing_revision`.

## Payment/PayU — gdzie leży granica heksagonu

Uczciwe postawienie sprawy: **modelem płatności jest tu model Syliusa**
(`PaymentInterface`, `PaymentRequestInterface`, maszyna stanów). Nie da się go
schować za portem bez pisania atrapy frameworka, więc granica heksagonu jest
postawiona tam, gdzie faktycznie coś izoluje:

- **port `PayUApi`** — system zewnętrzny; adapterem jest `Infrastructure\Http\PayUHttpClient`.
  Podmiana klienta HTTP nie rusza reszty.
- **domena bez frameworka** — `PayUOrderStatus` mapuje się na własny enum
  `PaymentTransition`, nie na stałe `Sylius\...\PaymentTransitions`. Tłumaczenie
  na maszynę stanów Syliusa jest w `Infrastructure\Sylius\PaymentTransitionProcessor`.
  Podobnie `SignatureVerifier` i `NotificationParser` są czystym PHP i mają testy jednostkowe.
- **adaptery Syliusa** — `Infrastructure/Sylius/` (odczyt konfiguracji bramki,
  `details` płatności, budowa payloadu, wyciąganie notyfikacji).
- **UI** — formularz konfiguracji bramki, command providery, providery odpowiedzi HTTP.

Handlery w `Application/CommandHandler/` nadal przyjmują typy Syliusa i wołają
adaptery z `Infrastructure/Sylius/` — to świadomy kompromis, nie przeoczenie.
Alternatywą byłyby porty, które i tak w sygnaturach niosłyby klasy Syliusa,
czyli pozorna czystość.

## Czego celowo nie ruszono

- `src/Entity/` — puste klasy wymagane przez `sylius_resource`; nie ma tam logiki.
- `Grid/`, `Form/Extension/`, `Menu/`, `Twig/`, `Review/`, `Security/` — punkty
  rozszerzeń frameworka. Z definicji są adapterami i są tam, gdzie Sylius ich szuka.
