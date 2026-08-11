<?php

declare(strict_types=1);

namespace App\Payment\PayU\Infrastructure\Sylius;

use App\Payment\PayU\Domain\PayUGateway;
use Sylius\Component\Payment\Model\PaymentInterface;

final readonly class PaymentDetails
{
    /**
     * @param array<array-key, mixed> $payuOrder
     */
    public function update(PaymentInterface $payment, array $payuOrder): void
    {
        $details = $payment->getDetails();
        $current = $details[PayUGateway::DETAILS_KEY] ?? [];

        $payment->setDetails(
            array_merge(
                $details,
                [
                PayUGateway::DETAILS_KEY => array_merge(is_array($current) ? $current : [], $payuOrder),
                ],
            ),
        );
    }

    public function status(PaymentInterface $payment): ?string
    {
        $payuDetails = $payment->getDetails()[PayUGateway::DETAILS_KEY] ?? null;

        if (!is_array($payuDetails)) {
            return null;
        }

        $status = $payuDetails['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function orderId(PaymentInterface $payment): ?string
    {
        $payuDetails = $payment->getDetails()[PayUGateway::DETAILS_KEY] ?? null;

        if (!is_array($payuDetails)) {
            return null;
        }

        $orderId = $payuDetails['orderId'] ?? null;

        return is_string($orderId) && '' !== $orderId ? $orderId : null;
    }
}
