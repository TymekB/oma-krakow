<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\ValueObject;

final readonly class ExpressOrder
{
    public function __construct(
        public string $tokenValue,
        public int $total,
    ) {
    }
}
