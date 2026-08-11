<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Service;

use App\Shared\Domain\Event\DomainEvent;
use App\SiteContent\Domain\ValueObject\ContentKey;

final readonly class RestoreResult
{
    /**
     * @param list<ContentKey>  $changedKeys
     * @param list<DomainEvent> $events
     */
    public function __construct(
        public array $changedKeys,
        public array $events,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->changedKeys;
    }

    /**
     * @return list<string>
     */
    public function changedKeysAsStrings(): array
    {
        return array_map(static fn (ContentKey $key): string => $key->value, $this->changedKeys);
    }
}
