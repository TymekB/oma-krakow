<?php

declare(strict_types=1);

namespace App\Payment\PayU\Api;

use Sylius\Component\Payment\PaymentTransitions;

enum PayUOrderStatus: string
{
    case New = 'NEW';
    case Pending = 'PENDING';
    case WaitingForConfirmation = 'WAITING_FOR_CONFIRMATION';
    case Completed = 'COMPLETED';
    case Canceled = 'CANCELED';
    case Rejected = 'REJECTED';

    public function paymentTransition(): string
    {
        return match ($this) {
            self::New, self::Pending => PaymentTransitions::TRANSITION_PROCESS,
            self::WaitingForConfirmation => PaymentTransitions::TRANSITION_AUTHORIZE,
            self::Completed => PaymentTransitions::TRANSITION_COMPLETE,
            self::Canceled => PaymentTransitions::TRANSITION_CANCEL,
            self::Rejected => PaymentTransitions::TRANSITION_FAIL,
        };
    }
}
