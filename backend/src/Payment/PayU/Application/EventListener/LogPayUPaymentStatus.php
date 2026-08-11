<?php

declare(strict_types=1);

namespace App\Payment\PayU\Application\EventListener;

use App\Payment\PayU\Domain\Event\PayUPaymentStatusChanged;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.event_bus')]
final readonly class LogPayUPaymentStatus
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(PayUPaymentStatusChanged $event): void
    {
        $this->logger->info(
            'Status płatności PayU zmieniony.',
            [
            'payment' => $event->paymentId,
            'payu_status' => $event->status->value,
            'transition' => $event->transition->value,
            'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
            ],
        );
    }
}
