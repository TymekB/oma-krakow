<?php

declare(strict_types=1);

namespace App\SiteContent\Application\QueryHandler;

use App\SiteContent\Application\Query\GetRevisionHistory;
use App\SiteContent\Application\View\RevisionView;
use App\SiteContent\Domain\Model\ContentRevision;
use App\SiteContent\Domain\Repository\ContentRevisionRepository;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.query_bus')]
final readonly class GetRevisionHistoryHandler
{
    public function __construct(
        private ContentRevisionRepository $revisions,
        private SiteContentRepository $contents,
    ) {
    }

    /**
     * @return list<RevisionView>
     */
    public function __invoke(GetRevisionHistory $query): array
    {
        $current = $this->contents->snapshot();

        return array_map(
            static fn (ContentRevision $revision): RevisionView => RevisionView::fromRevision($revision, $current),
            $this->revisions->findLatest($query->limit),
        );
    }
}
