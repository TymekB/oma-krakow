<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Repository;

use App\SiteContent\Domain\Model\SiteContent;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;

interface SiteContentRepository
{
    public function findByKey(ContentKey $key): ?SiteContent;

    /**
     * @return list<SiteContent>
     */
    public function findAll(): array;

    public function snapshot(): ContentSnapshot;

    public function add(SiteContent $content): void;

    public function remove(SiteContent $content): void;
}
