<?php

declare(strict_types=1);

namespace App\Tests\SiteContent\Domain;

use App\SiteContent\Domain\Model\SiteContent;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use App\SiteContent\Domain\ValueObject\ContentValue;

final class InMemorySiteContentRepository implements SiteContentRepository
{
    /**
     * @var array<string, SiteContent> 
     */
    private array $contents = [];

    /**
     * @param array<string, string> $values
     */
    public static function withValues(array $values, \DateTimeImmutable $now): self
    {
        $repository = new self();

        foreach ($values as $key => $value) {
            $content = SiteContent::create(
                ContentKey::fromString($key),
                ContentValue::fromString($value),
                $now,
            );
            $content->releaseEvents();
            $repository->add($content);
        }

        return $repository;
    }

    public function findByKey(ContentKey $key): ?SiteContent
    {
        return $this->contents[$key->value] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->contents);
    }

    public function snapshot(): ContentSnapshot
    {
        $values = [];

        foreach ($this->contents as $content) {
            $values[$content->key()->value] = $content->value()->value;
        }

        return ContentSnapshot::fromArray($values);
    }

    public function add(SiteContent $content): void
    {
        $this->contents[$content->key()->value] = $content;
    }

    public function remove(SiteContent $content): void
    {
        unset($this->contents[$content->key()->value]);
    }
}
