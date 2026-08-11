<?php

declare(strict_types=1);

namespace App\Review;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Review\Model\ReviewInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PurchaseConfirmation
{
    /** @var array<string, list<string>> */
    private array $buyerIdsByProductId = [];

    /**
     * @param class-string<OrderInterface> $orderClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%sylius.model.order.class%')]
        private readonly string $orderClass,
    ) {
    }

    public function isConfirmed(ReviewInterface $review): bool
    {
        $author = $review->getAuthor();
        $product = $review->getReviewSubject();

        if (null === $author || !$product instanceof ProductInterface) {
            return false;
        }

        $authorId = $author->getId();
        $productId = $product->getId();

        if (null === $authorId || null === $productId) {
            return false;
        }

        return in_array((string) $authorId, $this->buyerIds((string) $productId), true);
    }

    /**
     * @return list<string>
     */
    private function buyerIds(string $productId): array
    {
        if (isset($this->buyerIdsByProductId[$productId])) {
            return $this->buyerIdsByProductId[$productId];
        }

        $dql = sprintf(
            'SELECT DISTINCT IDENTITY(o.customer) FROM %s o
             JOIN o.items item
             JOIN item.variant variant
             WHERE IDENTITY(variant.product) = :productId
               AND o.paymentState = :paymentState
               AND o.state != :cancelledState
               AND o.customer IS NOT NULL',
            $this->orderClass,
        );

        $ids = $this->entityManager->createQuery($dql)
            ->setParameter('productId', $productId)
            ->setParameter('paymentState', OrderPaymentStates::STATE_PAID)
            ->setParameter('cancelledState', OrderInterface::STATE_CANCELLED)
            ->getSingleColumnResult();

        $buyerIds = [];

        foreach ($ids as $id) {
            if (is_int($id) || is_string($id)) {
                $buyerIds[] = (string) $id;
            }
        }

        return $this->buyerIdsByProductId[$productId] = $buyerIds;
    }
}
