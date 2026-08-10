<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaymentMethodsExtension extends AbstractExtension
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ChannelContextInterface $channelContext,
        #[Autowire('%sylius_core.public_dir%')]
        private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_enabled_payment_methods', $this->enabledPaymentMethods(...)),
            new TwigFunction('file_exists', $this->publicFileExists(...)),
        ];
    }

    /**
     * @return list<PaymentMethodInterface>
     */
    public function enabledPaymentMethods(): array
    {
        $channel = $this->channelContext->getChannel();

        if (!$channel instanceof ChannelInterface) {
            return [];
        }

        return array_values($this->paymentMethodRepository->findEnabledForChannel($channel));
    }

    public function publicFileExists(string $path): bool
    {
        return is_file($this->publicDir . '/' . ltrim($path, '/'));
    }
}
