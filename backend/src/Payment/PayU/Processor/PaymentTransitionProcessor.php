<?php

declare(strict_types=1);

namespace App\Payment\PayU\Processor;

use App\Payment\PayU\Api\PayUOrderStatus;
use App\Payment\PayU\Payment\PaymentDetailsUpdater;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentTransitions;

final readonly class PaymentTransitionProcessor
{
    public function __construct(
        private PaymentDetailsUpdater $paymentDetailsUpdater,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function process(PaymentRequestInterface $paymentRequest): void
    {
        $payment = $paymentRequest->getPayment();
        $status = $this->paymentDetailsUpdater->status($payment);

        if (null === $status) {
            return;
        }

        $transition = PayUOrderStatus::tryFrom($status)?->paymentTransition();

        if (null === $transition) {
            return;
        }

        if (!$this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
    }
}
