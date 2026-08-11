<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Service;

use App\SiteContent\Domain\Model\SiteContent;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use App\SiteContent\Domain\ValueObject\ContentValue;

final readonly class SiteContentEditor
{
    public function __construct(private SiteContentRepository $repository)
    {
    }

    public function change(ContentKey $key, ContentValue $value, \DateTimeImmutable $now): SiteContent
    {
        $content = $this->repository->findByKey($key);

        if (null === $content) {
            $content = SiteContent::create($key, $value, $now);
            $this->repository->add($content);

            return $content;
        }

        $content->changeValue($value, $now);

        return $content;
    }

    public function restoreTo(ContentSnapshot $snapshot, \DateTimeImmutable $now): RestoreResult
    {
        $changedKeys = [];
        $events = [];

        foreach ($this->repository->findAll() as $content) {
            if ($snapshot->has($content->key())) {
                continue;
            }

            $content->markRemoved($now);
            $this->repository->remove($content);

            $changedKeys[] = $content->key();
            $events = [...$events, ...$content->releaseEvents()];
        }

        foreach ($snapshot->toArray() as $key => $value) {
            $contentKey = ContentKey::fromString($key);
            $contentValue = ContentValue::fromString($value);
            $existing = $this->repository->findByKey($contentKey);

            if (null !== $existing && $existing->value()->equals($contentValue)) {
                continue;
            }

            $content = $this->change($contentKey, $contentValue, $now);

            $changedKeys[] = $contentKey;
            $events = [...$events, ...$content->releaseEvents()];
        }

        return new RestoreResult($changedKeys, $events);
    }
}
