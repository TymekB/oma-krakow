<?php

declare(strict_types=1);

namespace App\Payment\PayU\UI\Http;

use App\Payment\PayU\Infrastructure\Sylius\GatewayConfigCredentialsProvider;
use App\Payment\PayU\Infrastructure\Sylius\NotificationExtractor;
use App\Payment\PayU\Domain\PayUGateway;
use App\Payment\PayU\Domain\Service\SignatureVerifier;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\PaymentBundle\Attribute\AsNotifyPaymentProvider;
use Sylius\Bundle\PaymentBundle\Provider\GatewayFactoryNameProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\NotifyPaymentProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

#[AsNotifyPaymentProvider]
final readonly class NotifyPaymentProvider implements NotifyPaymentProviderInterface
{
    private const SIGNATURE_HEADERS = ['OpenPayu-Signature', 'X-OpenPayU-Signature'];

    /**
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private GatewayFactoryNameProviderInterface $gatewayFactoryNameProvider,
        private GatewayConfigCredentialsProvider $credentialsProvider,
        private SignatureVerifier $signatureVerifier,
        private NotificationExtractor $notificationExtractor,
        #[Autowire(service: 'sylius.repository.payment_request')]
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request, PaymentMethodInterface $paymentMethod): bool
    {
        return PayUGateway::FACTORY_NAME === $this->gatewayFactoryNameProvider->provide($paymentMethod);
    }

    public function getPayment(Request $request, PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        $credentials = $this->credentialsProvider->provide($paymentMethod);
        $content = $request->getContent();

        if (!$this->signatureVerifier->verify($this->signature($request), $content, $credentials->signatureKey)) {
            $this->logger->warning(
                'PayU notification with an invalid signature.',
                [
                'paymentMethod' => $paymentMethod->getCode(),
                'clientIp' => $request->getClientIp(),
                ],
            );

            throw new AccessDeniedHttpException('Invalid PayU notification signature.');
        }

        $order = $this->notificationExtractor->extractOrderFromContent($content);
        $extOrderId = $order['extOrderId'] ?? null;

        if (!is_string($extOrderId) || !Uuid::isValid($extOrderId)) {
            throw new NotFoundHttpException('PayU notification has no known extOrderId.');
        }

        $paymentRequest = $this->paymentRequestRepository->find($extOrderId);

        if (null === $paymentRequest) {
            throw new NotFoundHttpException(sprintf('No payment request found for extOrderId "%s".', $extOrderId));
        }

        return $paymentRequest->getPayment();
    }

    private function signature(Request $request): ?string
    {
        foreach (self::SIGNATURE_HEADERS as $header) {
            $signature = $request->headers->get($header);

            if (null !== $signature && '' !== $signature) {
                return $signature;
            }
        }

        return null;
    }
}
