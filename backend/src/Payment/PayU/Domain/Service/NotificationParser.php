<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Service;

final readonly class NotificationParser
{
    /**
     * @return array<array-key, mixed>|null
     */
    public function extractOrder(string $content): ?array
    {
        if ('' === $content) {
            return null;
        }

        try {
            $notification = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($notification)) {
            return null;
        }

        $order = $notification['order'] ?? null;

        return is_array($order) ? $order : null;
    }
}
