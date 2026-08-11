<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductAvailabilityExtension extends AbstractExtension
{
    public function __construct(
        private readonly AvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_product_is_sold_out', $this->isSoldOut(...)),
        ];
    }

    public function isSoldOut(ProductInterface $product): bool
    {
        $variants = $product->getEnabledVariants();

        if ($variants->isEmpty()) {
            return true;
        }

        foreach ($variants as $variant) {
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            if ($this->availabilityChecker->isStockAvailable($variant)) {
                return false;
            }
        }

        return true;
    }
}
