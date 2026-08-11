<?php

declare(strict_types=1);

namespace App\SiteContent\Application\QueryHandler;

use App\SiteContent\Application\Query\GetContentMap;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.query_bus')]
final readonly class GetContentMapHandler
{
    public function __construct(private SiteContentRepository $contents)
    {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(GetContentMap $query): array
    {
        return $this->contents->snapshot()->toArray();
    }
}
