<?php

declare(strict_types=1);

namespace App\Payment\PayU\Application\CommandHandler;

use App\Payment\PayU\Application\Command\StatusPaymentRequest;
use App\Payment\PayU\Domain\Event\PayUPaymentStatusChanged;
use App\Payment\PayU\Domain\Exception\PayUApiException;
use App\Payment\PayU\Domain\Port\PayUApi;
use App\Payment\PayU\Infrastructure\Sylius\GatewayConfigCredentialsProvider;
use App\Payment\PayU\Infrastructure\Sylius\PaymentDetails;
use App\Payment\PayU\Infrastructure\Sylius\PaymentTransitionProcessor;
use App\Shared\Application\Event\EventPublisher;
use App\Shared\Domain\Clock;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StatusPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private GatewayConfigCredentialsProvider $credentialsProvider,
        private PayUApi $payU,
        private PaymentDetails $paymentDetails,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
        private EventPublisher $eventPublisher,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StatusPaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);
        $payment = $paymentRequest->getPayment();
        $orderId = $this->paymentDetails->orderId($payment);

        if (null === $orderId) {
            $this->fail($paymentRequest, 'Payment has no PayU order identifier.');

            return;
        }

        try {
            $order = $this->payU->retrieveOrder(
                $this->credentialsProvider->provide($paymentRequest->getMethod()),
                $orderId,
            );
        } catch (PayUApiException $exception) {
            $this->logger->error(
                'PayU order status could not be retrieved.',
                [
                'paymentRequest' => $paymentRequest->getId(),
                'payuOrderId' => $orderId,
                'exception' => $exception->getMessage(),
                ],
            );

            $this->fail($paymentRequest, $exception->getMessage());

            return;
        }

        $this->paymentDetails->update($payment, $order);
        $this->publishStatus($paymentRequest);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }

    private function fail(PaymentRequestInterface $paymentRequest, string $reason): void
    {
        $paymentRequest->setResponseData(['error' => $reason]);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_FAIL,
        );
    }

    private function publishStatus(PaymentRequestInterface $paymentRequest): void
    {
        $status = $this->paymentTransitionProcessor->process($paymentRequest);

        if (null === $status) {
            return;
        }

        $this->eventPublisher->publishOne(
            new PayUPaymentStatusChanged(
                $paymentRequest->getPayment()->getId(),
                $status,
                $status->paymentTransition(),
                $this->clock->now(),
            ),
        );
    }
}
