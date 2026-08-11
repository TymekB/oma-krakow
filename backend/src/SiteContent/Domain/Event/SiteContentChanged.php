<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentValue;

final readonly class SiteContentChanged implements DomainEvent
{
    public function __construct(
        public ContentKey $key,
        public ?ContentValue $previousValue,
        public ContentValue $currentValue,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function isFirstValue(): bool
    {
        return null === $this->previousValue;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
