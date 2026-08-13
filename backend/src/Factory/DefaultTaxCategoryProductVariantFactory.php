<?php

declare(strict_types=1);

namespace App\Factory;

use Sylius\Component\Core\Model\ProductVariantInterface as CoreProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProductVariantFactoryInterface<ProductVariantInterface>
 */
#[AsDecorator(decorates: 'sylius.factory.product_variant')]
final readonly class DefaultTaxCategoryProductVariantFactory implements ProductVariantFactoryInterface
{
    /**
     * @param ProductVariantFactoryInterface<ProductVariantInterface> $decorated
     * @param RepositoryInterface<TaxCategoryInterface> $taxCategoryRepository
     */
    public function __construct(
        private ProductVariantFactoryInterface $decorated,
        #[Autowire(service: 'sylius.repository.tax_category')]
        private RepositoryInterface $taxCategoryRepository,
        #[Autowire(param: 'app.default_tax_category_code')]
        private string $defaultTaxCategoryCode,
    ) {
    }

    public function createNew(): ProductVariantInterface
    {
        return $this->withDefaultTaxCategory($this->decorated->createNew());
    }

    public function createForProduct(ProductInterface $product): ProductVariantInterface
    {
        return $this->withDefaultTaxCategory($this->decorated->createForProduct($product));
    }

    private function withDefaultTaxCategory(ProductVariantInterface $variant): ProductVariantInterface
    {
        if (!$variant instanceof CoreProductVariantInterface || null !== $variant->getTaxCategory()) {
            return $variant;
        }

        $taxCategory = $this->taxCategoryRepository->findOneBy(['code' => $this->defaultTaxCategoryCode]);

        if ($taxCategory instanceof TaxCategoryInterface) {
            $variant->setTaxCategory($taxCategory);
        }

        return $variant;
    }
}
