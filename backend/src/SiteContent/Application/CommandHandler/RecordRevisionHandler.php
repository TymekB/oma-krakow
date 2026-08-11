<?php

declare(strict_types=1);

namespace App\SiteContent\Application\CommandHandler;

use App\Shared\Domain\Clock;
use App\SiteContent\Application\Command\RecordRevision;
use App\SiteContent\Domain\Model\ContentRevision;
use App\SiteContent\Domain\Repository\ContentRevisionRepository;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use App\SiteContent\Domain\ValueObject\Author;
use App\SiteContent\Domain\ValueObject\ContentKey;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.command_bus')]
final readonly class RecordRevisionHandler
{
    public function __construct(
        private SiteContentRepository $contents,
        private ContentRevisionRepository $revisions,
        private Clock $clock,
    ) {
    }

    public function __invoke(RecordRevision $command): void
    {
        $this->revisions->add(
            ContentRevision::record(
                $this->contents->snapshot(),
                array_map(ContentKey::fromString(...), $command->changedKeys),
                Author::fromString($command->author),
                $command->reason,
                $this->clock->now(),
            ),
        );
    }
}
