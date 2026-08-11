<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Port;

use App\Payment\PayU\Domain\Exception\PayUApiException;
use App\Payment\PayU\Domain\ValueObject\PayUCreatedOrder;
use App\Payment\PayU\Domain\ValueObject\PayUCredentials;

interface PayUApi
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws PayUApiException
     */
    public function createOrder(PayUCredentials $credentials, array $payload): PayUCreatedOrder;

    /**
     * @return array<array-key, mixed>
     *
     * @throws PayUApiException
     */
    public function retrieveOrder(PayUCredentials $credentials, string $orderId): array;
}
