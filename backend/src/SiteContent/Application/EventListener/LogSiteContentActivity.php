<?php

declare(strict_types=1);

namespace App\SiteContent\Application\EventListener;

use App\SiteContent\Application\Event\ContentRestoredFromRevision;
use App\SiteContent\Domain\Event\SiteContentChanged;
use App\SiteContent\Domain\Event\SiteContentRemoved;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class LogSiteContentActivity
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsMessageHandler(bus: 'oma.event_bus')]
    public function onContentChanged(SiteContentChanged $event): void
    {
        $this->logger->info(
            'Treść strony zmieniona.', [
            'key' => $event->key->value,
            'first_value' => $event->isFirstValue(),
            'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
            ]
        );
    }

    #[AsMessageHandler(bus: 'oma.event_bus')]
    public function onContentRemoved(SiteContentRemoved $event): void
    {
        $this->logger->info(
            'Treść strony usunięta.', [
            'key' => $event->key->value,
            'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
            ]
        );
    }

    #[AsMessageHandler(bus: 'oma.event_bus')]
    public function onContentRestored(ContentRestoredFromRevision $event): void
    {
        $this->logger->info(
            'Treść strony przywrócona z wersji.', [
            'revision' => $event->revisionId,
            'author' => $event->author,
            'restored_keys' => $event->restoredKeys,
            'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
            ]
        );
    }
}
