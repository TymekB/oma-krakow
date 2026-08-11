<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Event;

use App\Payment\PayU\Domain\Model\PaymentTransition;
use App\Payment\PayU\Domain\Model\PayUOrderStatus;
use App\Shared\Domain\Event\DomainEvent;

final readonly class PayUPaymentStatusChanged implements DomainEvent
{
    public function __construct(
        public ?int $paymentId,
        public PayUOrderStatus $status,
        public PaymentTransition $transition,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
