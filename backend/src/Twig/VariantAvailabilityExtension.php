<?php

declare(strict_types=1);

namespace App\Twig;

use App\Product\VariantAvailability;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class VariantAvailabilityExtension extends AbstractExtension
{
    public function __construct(private readonly VariantAvailability $availability)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_variant_sold_out', $this->isSoldOut(...)),
        ];
    }

    public function isSoldOut(?ProductVariantInterface $variant): bool
    {
        return null !== $variant && !$this->availability->isAvailable($variant);
    }
}
