<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Application\CommandHandler;

use App\Checkout\ApplePay\Application\Command\PlaceExpressOrder;
use App\Checkout\ApplePay\Domain\Port\ExpressCheckout;
use App\Checkout\ApplePay\Domain\ValueObject\ExpressOrder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.command_bus')]
final readonly class PlaceExpressOrderHandler
{
    public function __construct(
        private ExpressCheckout $expressCheckout,
    ) {
    }

    public function __invoke(PlaceExpressOrder $command): ExpressOrder
    {
        return $this->expressCheckout->place(
            $command->contact,
            $command->shippingMethodCode,
            $command->paymentToken,
        );
    }
}
