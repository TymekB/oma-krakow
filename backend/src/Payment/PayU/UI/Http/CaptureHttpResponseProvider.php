<?php

declare(strict_types=1);

namespace App\Payment\PayU\UI\Http;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag('oma.payu.http_response_provider', ['action' => PaymentRequestInterface::ACTION_CAPTURE])]
final readonly class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState() &&
            null !== $this->redirectUri($paymentRequest);
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        $redirectUri = $this->redirectUri($paymentRequest);

        if (null === $redirectUri) {
            throw new \LogicException('PayU did not provide a redirect URI.');
        }

        return new RedirectResponse($redirectUri, Response::HTTP_SEE_OTHER);
    }

    private function redirectUri(PaymentRequestInterface $paymentRequest): ?string
    {
        $redirectUri = $paymentRequest->getResponseData()['redirectUri'] ?? null;

        return is_string($redirectUri) && '' !== $redirectUri ? $redirectUri : null;
    }
}
