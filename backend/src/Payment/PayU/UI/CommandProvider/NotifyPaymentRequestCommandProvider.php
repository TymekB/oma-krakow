<?php

declare(strict_types=1);

namespace App\Payment\PayU\UI\CommandProvider;

use App\Payment\PayU\Application\Command\NotifyPaymentRequest;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('oma.payu.command_provider', ['action' => PaymentRequestInterface::ACTION_NOTIFY])]
final class NotifyPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return PaymentRequestInterface::ACTION_NOTIFY === $paymentRequest->getAction();
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        return new NotifyPaymentRequest($paymentRequest->getId());
    }
}
