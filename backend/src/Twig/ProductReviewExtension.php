<?php

declare(strict_types=1);

namespace App\Twig;

use App\Review\PurchaseConfirmation;
use Sylius\Component\Review\Model\ReviewInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProductReviewExtension extends AbstractExtension
{
    public function __construct(
        private readonly PurchaseConfirmation $purchaseConfirmation,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_review_confirmed_by_purchase', $this->isConfirmedByPurchase(...)),
        ];
    }

    public function isConfirmedByPurchase(ReviewInterface $review): bool
    {
        return $this->purchaseConfirmation->isConfirmed($review);
    }
}
