<?php

declare(strict_types=1);

namespace App\Payment\PayU\Infrastructure\Sylius;

use App\Payment\PayU\Domain\Model\PaymentTransition;
use App\Payment\PayU\Domain\Model\PayUOrderStatus;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentTransitions;

final readonly class PaymentTransitionProcessor
{
    public function __construct(
        private PaymentDetails $paymentDetails,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function process(PaymentRequestInterface $paymentRequest): ?PayUOrderStatus
    {
        $payment = $paymentRequest->getPayment();
        $rawStatus = $this->paymentDetails->status($payment);

        if (null === $rawStatus) {
            return null;
        }

        $status = PayUOrderStatus::tryFrom($rawStatus);

        if (null === $status) {
            return null;
        }

        $transition = $this->syliusTransition($status->paymentTransition());

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            return null;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);

        return $status;
    }

    private function syliusTransition(PaymentTransition $transition): string
    {
        return match ($transition) {
            PaymentTransition::Process => PaymentTransitions::TRANSITION_PROCESS,
            PaymentTransition::Authorize => PaymentTransitions::TRANSITION_AUTHORIZE,
            PaymentTransition::Complete => PaymentTransitions::TRANSITION_COMPLETE,
            PaymentTransition::Cancel => PaymentTransitions::TRANSITION_CANCEL,
            PaymentTransition::Fail => PaymentTransitions::TRANSITION_FAIL,
        };
    }
}
