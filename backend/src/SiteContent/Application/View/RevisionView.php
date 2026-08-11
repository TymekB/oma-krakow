<?php

declare(strict_types=1);

namespace App\SiteContent\Application\View;

use App\SiteContent\Domain\Model\ContentRevision;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;

final readonly class RevisionView
{
    /**
     * @param list<string> $changedKeys
     */
    private function __construct(
        public ?int $id,
        public string $author,
        public string $reason,
        public array $changedKeys,
        public \DateTimeImmutable $createdAt,
        public bool $isCurrent,
    ) {
    }

    public static function fromRevision(ContentRevision $revision, ContentSnapshot $current): self
    {
        return new self(
            $revision->id()?->value,
            $revision->author()->value,
            $revision->reason()->value,
            array_map(static fn (ContentKey $key): string => $key->value, $revision->changedKeys()),
            $revision->createdAt(),
            $revision->matches($current),
        );
    }
}
