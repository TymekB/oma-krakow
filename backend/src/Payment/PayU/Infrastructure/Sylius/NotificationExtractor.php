<?php

declare(strict_types=1);

namespace App\Payment\PayU\Payload;

use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class NotificationExtractor
{
    /**
     * @return array<array-key, mixed>|null
     */
    public function extractOrder(PaymentRequestInterface $paymentRequest): ?array
    {
        $payload = $paymentRequest->getPayload();

        if (!is_array($payload)) {
            return null;
        }

        $httpRequest = $payload['http_request'] ?? null;

        if (!is_array($httpRequest)) {
            return null;
        }

        $content = $httpRequest['content'] ?? null;

        if (!is_string($content) || '' === $content) {
            return null;
        }

        return $this->extractOrderFromContent($content);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function extractOrderFromContent(string $content): ?array
    {
        try {
            $notification = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($notification)) {
            return null;
        }

        $order = $notification['order'] ?? null;

        if (!is_array($order)) {
            return null;
        }

        return $order;
    }
}
