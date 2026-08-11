<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\SiteContent\Domain\ValueObject\ContentKey;

final readonly class SiteContentRemoved implements DomainEvent
{
    public function __construct(
        public ContentKey $key,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
