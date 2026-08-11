<?php

declare(strict_types=1);

namespace App\Payment\PayU\Payload;

use App\Payment\PayU\Api\PayUApiException;
use App\Payment\PayU\Api\PayUCredentials;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CreateOrderPayloadFactory
{
    private const NOTIFY_ROUTE = 'sylius_payment_method_notify';

    private const FALLBACK_CUSTOMER_IP = '127.0.0.1';

    private const MAX_TEXT_LENGTH = 255;

    private const PAY_BY_LINK_TYPE = 'PBL';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'sylius_shop.provider.order_pay.after_pay_url')]
        private UrlProviderInterface $afterPayUrlProvider,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PaymentRequestInterface $paymentRequest, PayUCredentials $credentials): array
    {
        $payment = $paymentRequest->getPayment();

        if (!$payment instanceof PaymentInterface) {
            throw new PayUApiException('PayU supports only core payments.');
        }

        $order = $payment->getOrder();

        if (!$order instanceof OrderInterface) {
            throw new PayUApiException('Payment is not attached to an order.');
        }

        $amount = $payment->getAmount();

        if (null === $amount || $amount <= 0) {
            throw new PayUApiException('Payment amount must be greater than zero.');
        }

        $currencyCode = $payment->getCurrencyCode() ?? $order->getCurrencyCode();

        if (null === $currencyCode) {
            throw new PayUApiException('Payment has no currency code.');
        }

        $extOrderId = $paymentRequest->getId();

        if (null === $extOrderId) {
            throw new PayUApiException('Payment request has to be persisted before creating a PayU order.');
        }

        $paymentMethodCode = $paymentRequest->getMethod()->getCode();

        if (null === $paymentMethodCode) {
            throw new PayUApiException('Payment method has no code.');
        }

        $payload = [
            'notifyUrl' => $this->urlGenerator->generate(
                self::NOTIFY_ROUTE,
                ['code' => $paymentMethodCode],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'continueUrl' => $this->afterPayUrlProvider->getUrl(
                $paymentRequest,
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
            'customerIp' => $this->customerIp(),
            'merchantPosId' => $credentials->posId,
            'description' => $this->description($order),
            'currencyCode' => $currencyCode,
            'totalAmount' => (string) $amount,
            'extOrderId' => $extOrderId,
            'products' => $this->products($order, $amount),
        ];

        $buyer = $this->buyer($order);

        if ([] !== $buyer) {
            $payload['buyer'] = $buyer;
        }

        if (null !== $credentials->payMethod) {
            $payload['payMethods'] = [
                'payMethod' => [
                    'type' => self::PAY_BY_LINK_TYPE,
                    'value' => $credentials->payMethod,
                ],
            ];
        }

        return $payload;
    }

    /**
     * @return list<array{name: string, unitPrice: string, quantity: string}>
     */
    private function products(OrderInterface $order, int $amount): array
    {
        $products = [];
        $total = 0;

        foreach ($order->getItems() as $item) {
            $unitPrice = $item->getFullDiscountedUnitPrice();
            $quantity = $item->getQuantity();

            if ($unitPrice <= 0 || $quantity <= 0) {
                return $this->singleProduct($order, $amount);
            }

            $products[] = [
                'name' => $this->text($item->getProductName() ?? $item->getVariantName() ?? 'Produkt'),
                'unitPrice' => (string) $unitPrice,
                'quantity' => (string) $quantity,
            ];

            $total += $unitPrice * $quantity;
        }

        $shippingTotal = $order->getShippingTotal();

        if ($shippingTotal > 0) {
            $products[] = [
                'name' => 'Dostawa',
                'unitPrice' => (string) $shippingTotal,
                'quantity' => '1',
            ];

            $total += $shippingTotal;
        }

        if ([] === $products || $total !== $amount) {
            return $this->singleProduct($order, $amount);
        }

        return $products;
    }

    /**
     * @return list<array{name: string, unitPrice: string, quantity: string}>
     */
    private function singleProduct(OrderInterface $order, int $amount): array
    {
        return [[
            'name' => $this->description($order),
            'unitPrice' => (string) $amount,
            'quantity' => '1',
        ]];
    }

    /**
     * @return array<string, string>
     */
    private function buyer(OrderInterface $order): array
    {
        $customer = $order->getCustomer();
        $email = $customer?->getEmail();

        if (null === $email || '' === $email) {
            return [];
        }

        $address = $order->getBillingAddress() ?? $order->getShippingAddress();

        $buyer = ['email' => $this->text($email)];

        $firstName = $address?->getFirstName() ?? $customer->getFirstName();
        $lastName = $address?->getLastName() ?? $customer->getLastName();
        $phoneNumber = $address?->getPhoneNumber() ?? $customer->getPhoneNumber();
        $localeCode = $order->getLocaleCode();

        if (null !== $firstName && '' !== $firstName) {
            $buyer['firstName'] = $this->text($firstName);
        }

        if (null !== $lastName && '' !== $lastName) {
            $buyer['lastName'] = $this->text($lastName);
        }

        if (null !== $phoneNumber && '' !== $phoneNumber) {
            $buyer['phone'] = $this->text($phoneNumber);
        }

        if (null !== $localeCode && '' !== $localeCode) {
            $buyer['language'] = substr($localeCode, 0, 2);
        }

        return $buyer;
    }

    private function description(OrderInterface $order): string
    {
        return $this->text(sprintf('OMA — zamówienie %s', $order->getNumber() ?? $order->getTokenValue() ?? ''));
    }

    private function customerIp(): string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp() ?? self::FALLBACK_CUSTOMER_IP;
    }

    private function text(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_TEXT_LENGTH);
    }
}
