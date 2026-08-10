<?php

declare(strict_types=1);

namespace App\Payment\PayU\CommandHandler;

use App\Payment\PayU\Command\NotifyPaymentRequest;
use App\Payment\PayU\Payload\NotificationExtractor;
use App\Payment\PayU\Payment\PaymentDetailsUpdater;
use App\Payment\PayU\Processor\PaymentTransitionProcessor;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private NotificationExtractor $notificationExtractor,
        private PaymentDetailsUpdater $paymentDetailsUpdater,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyPaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);
        $order = $this->notificationExtractor->extractOrder($paymentRequest);

        if (null === $order) {
            $this->logger->warning(
                'PayU notification without an order payload.',
                [
                'paymentRequest' => $paymentRequest->getId(),
                ],
            );

            $paymentRequest->setResponseData(['error' => 'Notification has no order payload.']);
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );

            return;
        }

        $this->paymentDetailsUpdater->update($paymentRequest->getPayment(), $order);
        $this->paymentTransitionProcessor->process($paymentRequest);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
    }
}
