<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Application\Event\ApplicationEvent;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class MessengerEventPublisher implements EventPublisher
{
    public function __construct(
        #[Autowire(service: 'oma.event_bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->publishOne($event);
        }
    }

    public function publishOne(DomainEvent|ApplicationEvent $event): void
    {
        $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
    }
}
