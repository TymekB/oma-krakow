<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Query;

use App\Shared\Application\Bus\Query;

/**
 * @implements Query<array<string, string>>
 */
final readonly class GetContentMap implements Query
{
}
