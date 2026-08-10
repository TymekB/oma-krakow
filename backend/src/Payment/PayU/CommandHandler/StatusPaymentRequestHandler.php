<?php

declare(strict_types=1);

namespace App\Payment\PayU\CommandHandler;

use App\Payment\PayU\Api\PayUApiException;
use App\Payment\PayU\Api\PayUClientInterface;
use App\Payment\PayU\Api\PayUCredentialsProvider;
use App\Payment\PayU\Command\StatusPaymentRequest;
use App\Payment\PayU\Payment\PaymentDetailsUpdater;
use App\Payment\PayU\Processor\PaymentTransitionProcessor;
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
        private PayUCredentialsProvider $credentialsProvider,
        private PayUClientInterface $client,
        private PaymentDetailsUpdater $paymentDetailsUpdater,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StatusPaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);
        $payment = $paymentRequest->getPayment();
        $orderId = $this->paymentDetailsUpdater->orderId($payment);

        if (null === $orderId) {
            $this->fail($paymentRequest, 'Payment has no PayU order identifier.');

            return;
        }

        try {
            $order = $this->client->retrieveOrder(
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

        $this->paymentDetailsUpdater->update($payment, $order);
        $this->paymentTransitionProcessor->process($paymentRequest);

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
}
