<?php

declare(strict_types=1);

namespace App\SiteContent\Application\CommandHandler;

use App\Shared\Application\Event\EventPublisher;
use App\Shared\Domain\Clock;
use App\SiteContent\Application\Command\ChangeContentBatch;
use App\SiteContent\Application\Event\ContentBatchEdited;
use App\SiteContent\Domain\Service\SiteContentEditor;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.command_bus')]
final readonly class ChangeContentBatchHandler
{
    public function __construct(
        private SiteContentEditor $editor,
        private EventPublisher $eventPublisher,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeContentBatch $command): void
    {
        $now = $this->clock->now();
        $events = [];

        foreach ($command->values as $key => $value) {
            $content = $this->editor->change(
                ContentKey::fromString($key),
                ContentValue::fromString($value),
                $now,
            );

            $events = [...$events, ...$content->releaseEvents()];
        }

        $this->eventPublisher->publish($events);
        $this->eventPublisher->publishOne(
            new ContentBatchEdited(array_keys($command->values), $command->author, $now),
        );
    }
}
