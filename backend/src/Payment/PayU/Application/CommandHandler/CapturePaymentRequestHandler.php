<?php

declare(strict_types=1);

namespace App\Payment\PayU\Application\CommandHandler;

use App\Payment\PayU\Application\Command\CapturePaymentRequest;
use App\Payment\PayU\Domain\Event\PayUPaymentStatusChanged;
use App\Payment\PayU\Domain\Exception\PayUApiException;
use App\Payment\PayU\Domain\Model\PayUOrderStatus;
use App\Payment\PayU\Domain\Port\PayUApi;
use App\Payment\PayU\Infrastructure\Sylius\CreateOrderPayloadFactory;
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
final readonly class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private GatewayConfigCredentialsProvider $credentialsProvider,
        private CreateOrderPayloadFactory $createOrderPayloadFactory,
        private PayUApi $payU,
        private PaymentDetails $paymentDetails,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
        private EventPublisher $eventPublisher,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CapturePaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);

        if (PaymentRequestInterface::STATE_PROCESSING === $paymentRequest->getState()) {
            return;
        }

        try {
            $credentials = $this->credentialsProvider->provide($paymentRequest->getMethod());
            $createdOrder = $this->payU->createOrder(
                $credentials,
                $this->createOrderPayloadFactory->create($paymentRequest, $credentials),
            );
        } catch (PayUApiException $exception) {
            $this->logger->error(
                'PayU order creation failed.', [
                'paymentRequest' => $paymentRequest->getId(),
                'payment' => $paymentRequest->getPayment()->getId(),
                'exception' => $exception->getMessage(),
                ]
            );

            $paymentRequest->setResponseData(['error' => $exception->getMessage()]);
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );

            return;
        }

        $paymentRequest->setResponseData(['redirectUri' => $createdOrder->redirectUri]);

        $this->paymentDetails->update(
            $paymentRequest->getPayment(), [
            'orderId' => $createdOrder->orderId,
            'extOrderId' => $createdOrder->extOrderId,
            'status' => PayUOrderStatus::New->value,
            ]
        );

        $this->publishStatus($paymentRequest);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
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
