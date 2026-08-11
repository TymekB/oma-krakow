<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Model;

use App\Shared\Domain\Event\RecordsDomainEvents;
use App\SiteContent\Domain\Event\SiteContentChanged;
use App\SiteContent\Domain\Event\SiteContentRemoved;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentValue;

class SiteContent
{
    use RecordsDomainEvents;

    private ?int $id = null; // @phpstan-ignore property.onlyWritten,property.unusedType

    private ContentKey $key;

    private ContentValue $value;

    private \DateTimeImmutable $updatedAt;

    private function __construct(ContentKey $key, ContentValue $value, \DateTimeImmutable $now)
    {
        $this->key = $key;
        $this->value = $value;
        $this->updatedAt = $now;
    }

    public static function create(ContentKey $key, ContentValue $value, \DateTimeImmutable $now): self
    {
        $content = new self($key, $value, $now);
        $content->recordThat(new SiteContentChanged($key, null, $value, $now));

        return $content;
    }

    public function key(): ContentKey
    {
        return $this->key;
    }

    public function value(): ContentValue
    {
        return $this->value;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeValue(ContentValue $value, \DateTimeImmutable $now): void
    {
        if ($this->value->equals($value)) {
            return;
        }

        $previous = $this->value;
        $this->value = $value;
        $this->updatedAt = $now;

        $this->recordThat(new SiteContentChanged($this->key, $previous, $value, $now));
    }

    public function markRemoved(\DateTimeImmutable $now): void
    {
        $this->recordThat(new SiteContentRemoved($this->key, $now));
    }
}
