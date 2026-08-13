<?php

declare(strict_types=1);

namespace App\Product;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

final readonly class VariantAvailability
{
    public function __construct(private AvailabilityCheckerInterface $availabilityChecker)
    {
    }

    public function isAvailable(ProductVariantInterface $variant): bool
    {
        return $variant->isEnabled() && $this->availabilityChecker->isStockAvailable($variant);
    }

    public function hasAvailableVariant(ProductInterface $product): bool
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface && $this->isAvailable($variant)) {
                return true;
            }
        }

        return false;
    }

    public function isOptionValueAvailable(ProductInterface $product, ProductOptionValueInterface $optionValue): bool
    {
        foreach ($product->getVariants() as $variant) {
            if (!$variant instanceof ProductVariantInterface || !$variant->hasOptionValue($optionValue)) {
                continue;
            }

            if ($this->isAvailable($variant)) {
                return true;
            }
        }

        return false;
    }
}
