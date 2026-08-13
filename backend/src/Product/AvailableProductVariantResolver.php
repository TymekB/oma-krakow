<?php

declare(strict_types=1);

namespace App\Product;

use Sylius\Component\Core\Model\ProductVariantInterface as CoreProductVariantInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Autoconfigure(tags: [['name' => 'sylius.product_variant_resolver', 'priority' => 0]])]
final readonly class AvailableProductVariantResolver implements ProductVariantResolverInterface
{
    /**
     * @param ProductVariantRepositoryInterface<CoreProductVariantInterface> $productVariantRepository
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.product_variant')]
        private ProductVariantRepositoryInterface $productVariantRepository,
        private VariantAvailability $availability,
    ) {
    }

    public function getVariant(ProductInterface $subject): ?ProductVariantInterface
    {
        foreach ($this->enabledVariants($subject) as $variant) {
            if ($variant instanceof CoreProductVariantInterface && $this->availability->isAvailable($variant)) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @return iterable<ProductVariantInterface>
     */
    private function enabledVariants(ProductInterface $subject): iterable
    {
        if (null === $subject->getId()) {
            return $subject->getEnabledVariants();
        }

        return $this->productVariantRepository->findBy(
            ['product' => $subject, 'enabled' => true],
            ['position' => 'ASC', 'id' => 'ASC'],
        );
    }
}
