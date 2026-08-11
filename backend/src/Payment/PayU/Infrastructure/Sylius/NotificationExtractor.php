<?php

declare(strict_types=1);

namespace App\Payment\PayU\Infrastructure\Sylius;

use App\Payment\PayU\Domain\Service\NotificationParser;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class NotificationExtractor
{
    public function __construct(private NotificationParser $parser)
    {
    }

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

        if (!is_string($content)) {
            return null;
        }

        return $this->parser->extractOrder($content);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function extractOrderFromContent(string $content): ?array
    {
        return $this->parser->extractOrder($content);
    }
}
