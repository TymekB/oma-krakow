<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

trait RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
