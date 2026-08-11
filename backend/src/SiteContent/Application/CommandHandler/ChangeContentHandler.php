<?php

declare(strict_types=1);

namespace App\SiteContent\Application\CommandHandler;

use App\Shared\Application\Event\EventPublisher;
use App\Shared\Domain\Clock;
use App\SiteContent\Application\Command\ChangeContent;
use App\SiteContent\Domain\Service\SiteContentEditor;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentValue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.command_bus')]
final readonly class ChangeContentHandler
{
    public function __construct(
        private SiteContentEditor $editor,
        private EventPublisher $eventPublisher,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeContent $command): void
    {
        $content = $this->editor->change(
            ContentKey::fromString($command->key),
            ContentValue::fromString($command->value),
            $this->clock->now(),
        );

        $this->eventPublisher->publish($content->releaseEvents());
    }
}
