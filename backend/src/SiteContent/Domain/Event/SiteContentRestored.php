<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\SiteContent\Domain\ValueObject\Author;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\RevisionId;

final readonly class SiteContentRestored implements DomainEvent
{
    /**
     * @param list<ContentKey> $restoredKeys
     */
    public function __construct(
        public RevisionId $revisionId,
        public array $restoredKeys,
        public Author $author,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
