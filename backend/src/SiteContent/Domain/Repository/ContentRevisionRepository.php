<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Repository;

use App\SiteContent\Domain\Model\ContentRevision;
use App\SiteContent\Domain\ValueObject\RevisionId;

interface ContentRevisionRepository
{
    public function findById(RevisionId $id): ?ContentRevision;

    /**
     * @return list<ContentRevision>
     */
    public function findLatest(int $limit): array;

    public function add(ContentRevision $revision): void;
}
