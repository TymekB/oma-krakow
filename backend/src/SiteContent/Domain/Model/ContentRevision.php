<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Model;

use App\SiteContent\Domain\ValueObject\Author;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use App\SiteContent\Domain\ValueObject\RevisionId;
use App\SiteContent\Domain\ValueObject\RevisionReason;

class ContentRevision
{
    private ?int $id = null; // @phpstan-ignore property.unusedType

    private ContentSnapshot $snapshot;

    /**
     * @var list<string> 
     */
    private array $changedKeys;

    private Author $author;

    private RevisionReason $reason;

    private \DateTimeImmutable $createdAt;

    /**
     * @param list<ContentKey> $changedKeys
     */
    private function __construct(
        ContentSnapshot $snapshot,
        array $changedKeys,
        Author $author,
        RevisionReason $reason,
        \DateTimeImmutable $now,
    ) {
        $this->snapshot = $snapshot;
        $this->changedKeys = array_map(static fn (ContentKey $key): string => $key->value, $changedKeys);
        $this->author = $author;
        $this->reason = $reason;
        $this->createdAt = $now;
    }

    /**
     * @param list<ContentKey> $changedKeys
     */
    public static function record(
        ContentSnapshot $snapshot,
        array $changedKeys,
        Author $author,
        RevisionReason $reason,
        \DateTimeImmutable $now,
    ): self {
        return new self($snapshot, $changedKeys, $author, $reason, $now);
    }

    public function id(): ?RevisionId
    {
        return null === $this->id ? null : RevisionId::fromInt($this->id);
    }

    public function snapshot(): ContentSnapshot
    {
        return $this->snapshot;
    }

    /**
     * @return list<ContentKey>
     */
    public function changedKeys(): array
    {
        return array_map(ContentKey::fromString(...), $this->changedKeys);
    }

    public function author(): Author
    {
        return $this->author;
    }

    public function reason(): RevisionReason
    {
        return $this->reason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function matches(ContentSnapshot $current): bool
    {
        return $this->snapshot->equals($current);
    }
}
