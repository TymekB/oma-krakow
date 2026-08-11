<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Application\QueryHandler;

use App\Checkout\ApplePay\Application\Query\GetShippingOptions;
use App\Checkout\ApplePay\Domain\Port\ExpressCheckout;
use App\Checkout\ApplePay\Domain\ValueObject\ShippingOption;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'oma.query_bus')]
final readonly class GetShippingOptionsHandler
{
    public function __construct(
        private ExpressCheckout $expressCheckout,
    ) {
    }

    /**
     * @return list<ShippingOption>
     */
    public function __invoke(GetShippingOptions $query): array
    {
        return $this->expressCheckout->shippingOptionsFor($query->contact);
    }
}
