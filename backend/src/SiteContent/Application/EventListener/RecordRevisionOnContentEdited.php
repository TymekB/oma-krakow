<?php

declare(strict_types=1);

namespace App\SiteContent\Application\EventListener;

use App\Shared\Application\Bus\CommandBus;
use App\SiteContent\Application\Command\RecordRevision;
use App\SiteContent\Application\Event\ContentBatchEdited;
use App\SiteContent\Domain\ValueObject\RevisionReason;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.event_bus')]
final readonly class RecordRevisionOnContentEdited
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    public function __invoke(ContentBatchEdited $event): void
    {
        $this->commandBus->dispatch(
            new RecordRevision($event->submittedKeys, $event->author, RevisionReason::Edit),
        );
    }
}
