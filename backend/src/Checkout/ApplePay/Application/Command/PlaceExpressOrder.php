<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Application\Command;

use App\Checkout\ApplePay\Domain\ValueObject\ApplePayContact;
use App\Checkout\ApplePay\Domain\ValueObject\ExpressOrder;
use App\Shared\Application\Bus\ResultingCommand;

/**
 * @implements ResultingCommand<ExpressOrder>
 */
final readonly class PlaceExpressOrder implements ResultingCommand
{
    public function __construct(
        public ApplePayContact $contact,
        public ?string $shippingMethodCode = null,
        public ?string $paymentToken = null,
    ) {
    }
}
