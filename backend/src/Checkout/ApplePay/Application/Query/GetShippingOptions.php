<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Application\Query;

use App\Checkout\ApplePay\Domain\ValueObject\ApplePayContact;
use App\Checkout\ApplePay\Domain\ValueObject\ShippingOption;
use App\Shared\Application\Bus\Query;

/**
 * @implements Query<list<ShippingOption>>
 */
final readonly class GetShippingOptions implements Query
{
    public function __construct(
        public ApplePayContact $contact,
    ) {
    }
}
