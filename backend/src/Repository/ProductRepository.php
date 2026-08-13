<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product\ProductVariant;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/** @phpstan-ignore missingType.generics */
class ProductRepository extends BaseProductRepository
{
    private const AVAILABLE_VARIANT_EXISTS = 'EXISTS (
        SELECT available_variant.id
        FROM ' . ProductVariant::class . ' available_variant
        WHERE available_variant.product = o
          AND available_variant.enabled = true
          AND (available_variant.tracked = false OR available_variant.onHand - available_variant.onHold > 0)
    )';

    #[\Override]
    public function createShopListQueryBuilder(
        ChannelInterface $channel,
        TaxonInterface $taxon,
        string $locale,
        array $sorting = [],
        bool $includeAllDescendants = false,
    ): QueryBuilder {
        return parent::createShopListQueryBuilder($channel, $taxon, $locale, $sorting, $includeAllDescendants)
            ->andWhere(self::AVAILABLE_VARIANT_EXISTS);
    }

    /**
     * @return list<ProductInterface>
     */
    #[\Override]
    public function findLatestByChannel(ChannelInterface $channel, string $locale, int $count): array
    {
        $products = parent::findLatestByChannel($channel, $locale, $count);
        $available = $this->availableIdsAmong($products);

        return array_values(
            array_filter(
                $products,
                static fn (ProductInterface $product): bool => isset($available[$product->getId()]),
            ),
        );
    }

    #[\Override]
    public function findOneByChannelAndSlug(ChannelInterface $channel, string $locale, string $slug): ?ProductInterface
    {
        $product = parent::findOneByChannelAndSlug($channel, $locale, $slug);

        if (null === $product) {
            return null;
        }

        return [] === $this->availableIdsAmong([$product]) ? null : $product;
    }

    /**
     * @param array<array-key, ProductInterface> $products
     *
     * @return array<int, int>
     */
    private function availableIdsAmong(array $products): array
    {
        if ([] === $products) {
            return [];
        }

        /** @var list<array{id: int}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.id')
            ->andWhere('o IN (:products)')
            ->andWhere(self::AVAILABLE_VARIANT_EXISTS)
            ->setParameter('products', $products)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'id', 'id');
    }
}
