<?php

declare(strict_types=1);

namespace App\Tests\Payment\PayU\Domain\Model;

use App\Payment\PayU\Domain\Model\PaymentTransition;
use App\Payment\PayU\Domain\Model\PayUOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayUOrderStatusTest extends TestCase
{
    #[DataProvider('statuses')]
    public function testShouldMapPayUStatusToPaymentTransition(
        string $status,
        PaymentTransition $expectedTransition,
    ): void {
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
     * @return iterable<string, array{string, PaymentTransition}>
     */
    public static function statuses(): iterable
    {
        yield 'new' => ['NEW', PaymentTransition::Process];
        yield 'pending' => ['PENDING', PaymentTransition::Process];
        yield 'waiting for confirmation' => ['WAITING_FOR_CONFIRMATION', PaymentTransition::Authorize];
        yield 'completed' => ['COMPLETED', PaymentTransition::Complete];
        yield 'canceled' => ['CANCELED', PaymentTransition::Cancel];
        yield 'rejected' => ['REJECTED', PaymentTransition::Fail];
    }
}
