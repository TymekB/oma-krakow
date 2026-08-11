<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\ValueObject;

final readonly class ShippingOption
{
    public function __construct(
        public string $code,
        public string $label,
        public int $amount,
        public ?string $detail = null,
    ) {
    }
}
