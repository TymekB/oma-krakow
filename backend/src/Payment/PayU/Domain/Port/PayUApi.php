<?php

declare(strict_types=1);

namespace App\Payment\PayU\Api;

interface PayUClientInterface
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
