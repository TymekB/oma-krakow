<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEvent;

interface EventPublisher
{
    /**
     * @param list<DomainEvent|ApplicationEvent> $events
     */
    public function publish(array $events): void;

    public function publishOne(DomainEvent|ApplicationEvent $event): void;
}
