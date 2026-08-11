<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Model;

enum PayUOrderStatus: string
{
    case New = 'NEW';
    case Pending = 'PENDING';
    case WaitingForConfirmation = 'WAITING_FOR_CONFIRMATION';
    case Completed = 'COMPLETED';
    case Canceled = 'CANCELED';
    case Rejected = 'REJECTED';

    public function paymentTransition(): PaymentTransition
    {
        return match ($this) {
            self::New, self::Pending => PaymentTransition::Process,
            self::WaitingForConfirmation => PaymentTransition::Authorize,
            self::Completed => PaymentTransition::Complete,
            self::Canceled => PaymentTransition::Cancel,
            self::Rejected => PaymentTransition::Fail,
        };
    }
}
