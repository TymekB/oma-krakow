<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Query;

use App\Shared\Application\Bus\Query;
use App\SiteContent\Application\View\RevisionView;

/**
 * @implements Query<list<RevisionView>>
 */
final readonly class GetRevisionHistory implements Query
{
    public const DEFAULT_LIMIT = 30;

    public function __construct(public int $limit = self::DEFAULT_LIMIT)
    {
    }
}
