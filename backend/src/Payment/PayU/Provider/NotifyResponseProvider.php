<?php

declare(strict_types=1);

namespace App\Payment\PayU\Provider;

use App\Payment\PayU\PayUGateway;
use Sylius\Bundle\PaymentBundle\Provider\GatewayFactoryNameProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\NotifyResponseProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Response;

#[AsDecorator('sylius.provider.payment_request.notify_response')]
final readonly class NotifyResponseProvider implements NotifyResponseProviderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private NotifyResponseProviderInterface $decorated,
        private GatewayFactoryNameProviderInterface $gatewayFactoryNameProvider,
    ) {
    }

    public function provide(PaymentRequestInterface $paymentRequest): Response
    {
        if (PayUGateway::FACTORY_NAME !== $this->gatewayFactoryNameProvider->provideFromPaymentRequest($paymentRequest)) {
            return $this->decorated->provide($paymentRequest);
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
