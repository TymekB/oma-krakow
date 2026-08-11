<?php

declare(strict_types=1);

namespace App\Tests\Payment\PayU\Api;

use App\Payment\PayU\Api\PayUOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\PaymentTransitions;

final class PayUOrderStatusTest extends TestCase
{
    #[DataProvider('statuses')]
    public function testShouldMapPayUStatusToPaymentTransition(string $status, string $expectedTransition): void
    {
        self::assertSame($expectedTransition, PayUOrderStatus::from($status)->paymentTransition());
    }

    #[DataProvider('unknownStatuses')]
    public function testShouldNotRecognizeUnknownStatus(string $status): void
    {
        self::assertNull(PayUOrderStatus::tryFrom($status));
    }

    /**
     * @return iterable<string, array{string}> 
     */
    public static function unknownStatuses(): iterable
    {
        yield 'unknown status' => ['SOMETHING_ELSE'];
        yield 'lowercase status' => ['completed'];
        yield 'empty status' => [''];
    }

    /**
     * @return iterable<string, array{string, string}> 
     */
    public static function statuses(): iterable
    {
        yield 'new' => ['NEW', PaymentTransitions::TRANSITION_PROCESS];
        yield 'pending' => ['PENDING', PaymentTransitions::TRANSITION_PROCESS];
        yield 'waiting for confirmation' => ['WAITING_FOR_CONFIRMATION', PaymentTransitions::TRANSITION_AUTHORIZE];
        yield 'completed' => ['COMPLETED', PaymentTransitions::TRANSITION_COMPLETE];
        yield 'canceled' => ['CANCELED', PaymentTransitions::TRANSITION_CANCEL];
        yield 'rejected' => ['REJECTED', PaymentTransitions::TRANSITION_FAIL];
    }
}
