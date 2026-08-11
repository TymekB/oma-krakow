<?php

declare(strict_types=1);

namespace App\SiteContent\Application\CommandHandler;

use App\Shared\Application\Event\EventPublisher;
use App\Shared\Domain\Clock;
use App\SiteContent\Application\Command\RestoreRevision;
use App\SiteContent\Application\Event\ContentRestoredFromRevision;
use App\SiteContent\Domain\Exception\RevisionNotFound;
use App\SiteContent\Domain\Repository\ContentRevisionRepository;
use App\SiteContent\Domain\Service\SiteContentEditor;
use App\SiteContent\Domain\ValueObject\RevisionId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.command_bus')]
final readonly class RestoreRevisionHandler
{
    public function __construct(
        private ContentRevisionRepository $revisions,
        private SiteContentEditor $editor,
        private EventPublisher $eventPublisher,
        private Clock $clock,
    ) {
    }

    public function __invoke(RestoreRevision $command): int
    {
        $revisionId = RevisionId::fromInt($command->revisionId);
        $revision = $this->revisions->findById($revisionId);

        if (null === $revision) {
            throw RevisionNotFound::withId($revisionId);
        }

        $now = $this->clock->now();
        $result = $this->editor->restoreTo($revision->snapshot(), $now);

        if ($result->isEmpty()) {
            return 0;
        }

        $this->eventPublisher->publish($result->events);
        $this->eventPublisher->publishOne(
            new ContentRestoredFromRevision(
                $revisionId->value,
                $result->changedKeysAsStrings(),
                $command->author,
                $now,
            ),
        );

        return count($result->changedKeys);
    }
}
