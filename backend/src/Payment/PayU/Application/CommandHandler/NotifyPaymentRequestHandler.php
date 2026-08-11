<?php

declare(strict_types=1);

namespace App\Payment\PayU\Application\CommandHandler;

use App\Payment\PayU\Application\Command\NotifyPaymentRequest;
use App\Payment\PayU\Domain\Event\PayUPaymentStatusChanged;
use App\Payment\PayU\Infrastructure\Sylius\NotificationExtractor;
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
final readonly class NotifyPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private NotificationExtractor $notificationExtractor,
        private PaymentDetails $paymentDetails,
        private PaymentTransitionProcessor $paymentTransitionProcessor,
        private StateMachineInterface $stateMachine,
        private EventPublisher $eventPublisher,
        private Clock $clock,
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

        $this->paymentDetails->update($paymentRequest->getPayment(), $order);
        $this->publishStatus($paymentRequest);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_COMPLETE,
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
