<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Event;

use App\Shared\Application\Event\ApplicationEvent;

final readonly class ContentBatchEdited implements ApplicationEvent
{
    /**
     * @param list<string> $submittedKeys
     */
    public function __construct(
        public array $submittedKeys,
        public string $author,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
