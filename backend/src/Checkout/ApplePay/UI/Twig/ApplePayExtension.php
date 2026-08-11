<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\UI\Twig;

use App\Checkout\ApplePay\Infrastructure\Config\ApplePayConfiguration;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ApplePayExtension extends AbstractExtension
{
    private const FALLBACK_COUNTRY_CODE = 'PL';

    /**
     * @param RepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private readonly ApplePayConfiguration $configuration,
        private readonly CartContextInterface $cartContext,
        private readonly ChannelContextInterface $channelContext,
        #[Autowire(service: 'sylius.repository.payment_method')]
        private readonly RepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_apple_pay', $this->context(...)),
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    public function context(): array
    {
        $settings = $this->configuration->settings();
        $order = $this->cart();

        if (!$settings->isEnabled() || null === $order || !$this->paymentMethodEnabled($settings->paymentMethodCode)) {
            return ['enabled' => false];
        }

        $subtotal = $order->getTotal() - $order->getShippingTotal();

        return [
            'enabled' => $subtotal > 0,
            'merchantId' => $settings->merchantId,
            'displayName' => $settings->displayName,
            'countryCode' => $this->countryCode(),
            'currencyCode' => (string) $order->getCurrencyCode(),
            'subtotal' => number_format($subtotal / 100, 2, '.', ''),
        ];
    }

    private function cart(): ?OrderInterface
    {
        try {
            $order = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            return null;
        }

        if (!$order instanceof OrderInterface || $order->isEmpty()) {
            return null;
        }

        return $order;
    }

    private function paymentMethodEnabled(string $code): bool
    {
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);

        return $paymentMethod instanceof PaymentMethodInterface && $paymentMethod->isEnabled();
    }

    private function countryCode(): string
    {
        $channel = $this->channelContext->getChannel();

        if (!$channel instanceof ChannelInterface) {
            return self::FALLBACK_COUNTRY_CODE;
        }

        $countryCode = $channel->getShopBillingData()?->getCountryCode();

        return null !== $countryCode && '' !== $countryCode
            ? strtoupper($countryCode)
            : self::FALLBACK_COUNTRY_CODE;
    }
}
