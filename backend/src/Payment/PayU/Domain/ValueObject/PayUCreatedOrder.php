<?php

declare(strict_types=1);

namespace App\Payment\PayU\Api;

final readonly class PayUCreatedOrder
{
    public function __construct(
        public string $orderId,
        public string $extOrderId,
        public string $redirectUri,
    ) {
    }
}
