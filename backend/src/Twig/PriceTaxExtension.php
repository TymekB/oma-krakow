<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PriceTaxExtension extends AbstractExtension
{
    /**
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param FactoryInterface<ProductVariantInterface> $productVariantFactory
     */
    public function __construct(
        private readonly TaxRateResolverInterface $taxRateResolver,
        #[Autowire(service: 'sylius.repository.channel')]
        private readonly RepositoryInterface $channelRepository,
        #[Autowire(service: 'sylius.factory.product_variant')]
        private readonly FactoryInterface $productVariantFactory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_price_tax', $this->context(...)),
            new TwigFunction('oma_new_variant_price_tax', $this->contextForNewVariant(...)),
        ];
    }

    /**
     * @return array{rate: float|null, included: bool, percentage: string}
     */
    public function contextForNewVariant(string $channelCode): array
    {
        return $this->context($this->productVariantFactory->createNew(), $channelCode);
    }

    /**
     * @return array{rate: float|null, included: bool, percentage: string}
     */
    public function context(?ProductVariantInterface $variant, string $channelCode): array
    {
        if (null === $variant) {
            return $this->unknown();
        }

        $taxRate = $this->taxRateResolver->resolve($variant, $this->criteriaFor($channelCode));

        if (null === $taxRate) {
            return $this->unknown();
        }

        $rate = $taxRate->getAmount();

        if ($rate <= 0.0) {
            return $this->unknown();
        }

        return [
            'rate' => $rate,
            'included' => $taxRate->isIncludedInPrice(),
            'percentage' => rtrim(rtrim(number_format($rate * 100, 2, ',', ''), '0'), ','),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function criteriaFor(string $channelCode): array
    {
        $channel = $this->channelRepository->findOneBy(['code' => $channelCode]);

        if (!$channel instanceof ChannelInterface) {
            return [];
        }

        $zone = $channel->getDefaultTaxZone();

        return null === $zone ? [] : ['zone' => $zone];
    }

    /**
     * @return array{rate: null, included: false, percentage: string}
     */
    private function unknown(): array
    {
        return ['rate' => null, 'included' => false, 'percentage' => ''];
    }
}
