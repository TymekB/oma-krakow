<?php

declare(strict_types=1);

namespace App\Tests\Payment\PayU\Payload;

use App\Payment\PayU\Api\PayUCredentials;
use App\Payment\PayU\Api\PayUEnvironment;
use App\Payment\PayU\Api\PayUPayMethod;
use App\Payment\PayU\Payload\CreateOrderPayloadFactory;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateOrderPayloadFactoryTest extends TestCase
{
    private const PAYMENT_REQUEST_HASH = '019fed82-14fa-7cc4-8425-8134655be8d4';

    public function testShouldBuildPayloadFromOrderItemsAndShipping(): void
    {
        // Given
        $factory = $this->createFactory();
        $paymentRequest = $this->createPaymentRequest(amount: 12_000, itemUnitPrice: 5_000, itemQuantity: 2, shippingTotal: 2_000);

        // When
        $payload = $factory->create($paymentRequest, $this->createCredentials());

        // Then
        self::assertSame('12000', $payload['totalAmount']);
        self::assertSame('PLN', $payload['currencyCode']);
        self::assertSame('300746', $payload['merchantPosId']);
        self::assertSame(self::PAYMENT_REQUEST_HASH, $payload['extOrderId']);
        self::assertSame('http://localhost:8080/payment-methods/payu', $payload['notifyUrl']);
        self::assertSame('http://localhost:8080/sklep/order/after-pay/hash', $payload['continueUrl']);
        self::assertSame('10.0.0.7', $payload['customerIp']);
        self::assertSame(
            [
                ['name' => 'Olejek', 'unitPrice' => '5000', 'quantity' => '2'],
                ['name' => 'Dostawa', 'unitPrice' => '2000', 'quantity' => '1'],
            ],
            $payload['products'],
        );
        self::assertSame(
            [
                'email' => 'kupujacy@oma.local',
                'firstName' => 'Natalia',
                'lastName' => 'Podgórska',
                'phone' => '+48000000000',
                'language' => 'pl',
            ],
            $payload['buyer'],
        );
        self::assertArrayNotHasKey('payMethods', $payload);
    }

    public function testShouldFallBackToSingleProductWhenItemsDoNotSumUpToPaymentAmount(): void
    {
        // Given
        $factory = $this->createFactory();
        $paymentRequest = $this->createPaymentRequest(amount: 9_999, itemUnitPrice: 5_000, itemQuantity: 2, shippingTotal: 0);

        // When
        $payload = $factory->create($paymentRequest, $this->createCredentials());

        // Then
        self::assertSame(
            [['name' => 'OMA — zamówienie 0012', 'unitPrice' => '9999', 'quantity' => '1']],
            $payload['products'],
        );
    }

    public function testShouldPreselectPayMethodWhenGatewayForcesOne(): void
    {
        // Given
        $factory = $this->createFactory();
        $paymentRequest = $this->createPaymentRequest(amount: 12_000, itemUnitPrice: 5_000, itemQuantity: 2, shippingTotal: 2_000);

        // When
        $payload = $factory->create($paymentRequest, $this->createCredentials(PayUPayMethod::ApplePay));

        // Then
        self::assertSame(
            ['payMethod' => ['type' => 'PBL', 'value' => 'jp']],
            $payload['payMethods'],
        );
    }

    private function createFactory(): CreateOrderPayloadFactory
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('http://localhost:8080/payment-methods/payu');

        $afterPayUrlProvider = $this->createMock(UrlProviderInterface::class);
        $afterPayUrlProvider
            ->method('getUrl')
            ->willReturn('http://localhost:8080/sklep/order/after-pay/hash');

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => '10.0.0.7']));

        return new CreateOrderPayloadFactory($urlGenerator, $afterPayUrlProvider, $requestStack);
    }

    private function createCredentials(?PayUPayMethod $payMethod = null): PayUCredentials
    {
        return new PayUCredentials(
            PayUEnvironment::Sandbox,
            '300746',
            '300746',
            'secret',
            'signature-key',
            $payMethod?->value,
        );
    }

    private function createPaymentRequest(
        int $amount,
        int $itemUnitPrice,
        int $itemQuantity,
        int $shippingTotal,
    ): PaymentRequestInterface {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getFullDiscountedUnitPrice')->willReturn($itemUnitPrice);
        $item->method('getQuantity')->willReturn($itemQuantity);
        $item->method('getProductName')->willReturn('Olejek');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('kupujacy@oma.local');

        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('Natalia');
        $address->method('getLastName')->willReturn('Podgórska');
        $address->method('getPhoneNumber')->willReturn('+48000000000');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));
        $order->method('getShippingTotal')->willReturn($shippingTotal);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getLocaleCode')->willReturn('pl_PL');
        $order->method('getNumber')->willReturn('0012');
        $order->method('getCurrencyCode')->willReturn('PLN');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn($amount);
        $payment->method('getCurrencyCode')->willReturn('PLN');
        $payment->method('getOrder')->willReturn($order);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn('payu');

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);
        $paymentRequest->method('getMethod')->willReturn($paymentMethod);
        $paymentRequest->method('getId')->willReturn(self::PAYMENT_REQUEST_HASH);

        return $paymentRequest;
    }
}
