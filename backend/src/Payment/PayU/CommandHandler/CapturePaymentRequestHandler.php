<?php

declare(strict_types=1);

namespace App\Payment\PayU\CommandHandler;

use App\Payment\PayU\Api\PayUApiException;
use App\Payment\PayU\Api\PayUClientInterface;
use App\Payment\PayU\Api\PayUCredentialsProvider;
use App\Payment\PayU\Api\PayUOrderStatus;
use App\Payment\PayU\Command\CapturePaymentRequest;
use App\Payment\PayU\Payload\CreateOrderPayloadFactory;
use App\Payment\PayU\Payment\PaymentDetailsUpdater;
use App\Payment\PayU\Processor\PaymentTransitionProcessor;
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
        private PayUCredentialsProvider $credentialsProvider,
        private CreateOrderPayloadFactory $createOrderPayloadFactory,
        private PayUClientInterface $client,
        private PaymentDetailsUpdater $paymentDetailsUpdater,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
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
            $createdOrder = $this->client->createOrder(
                $credentials,
                $this->createOrderPayloadFactory->create($paymentRequest, $credentials),
            );
        } catch (PayUApiException $exception) {
            $this->logger->error(
                'PayU order creation failed.',
                [
                'paymentRequest' => $paymentRequest->getId(),
                'payment' => $paymentRequest->getPayment()->getId(),
                'exception' => $exception->getMessage(),
                ],
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

        $this->paymentDetailsUpdater->update(
            $paymentRequest->getPayment(),
            [
            'orderId' => $createdOrder->orderId,
            'extOrderId' => $createdOrder->extOrderId,
            'status' => PayUOrderStatus::New->value,
            ],
        );

        $this->paymentTransitionProcessor->process($paymentRequest);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
        );
    }
}
