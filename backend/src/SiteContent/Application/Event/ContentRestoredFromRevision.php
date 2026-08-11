<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Event;

use App\Shared\Application\Event\ApplicationEvent;

final readonly class ContentRestoredFromRevision implements ApplicationEvent
{
    /**
     * @param list<string> $restoredKeys
     */
    public function __construct(
        public int $revisionId,
        public array $restoredKeys,
        public string $author,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
