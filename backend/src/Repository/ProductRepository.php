<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/** @phpstan-ignore missingType.generics */
class ProductRepository extends BaseProductRepository
{
    #[\Override]
    public function createShopListQueryBuilder(
        ChannelInterface $channel,
        TaxonInterface $taxon,
        string $locale,
        array $sorting = [],
        bool $includeAllDescendants = false,
    ): QueryBuilder {
        return parent::createShopListQueryBuilder($channel, $taxon, $locale, $sorting, $includeAllDescendants)
            ->andWhere('SIZE(o.variants) > 0');
    }

    /**
     * @return list<ProductInterface>
     */
    #[\Override]
    public function findLatestByChannel(ChannelInterface $channel, string $locale, int $count): array
    {
        return array_values(
            array_filter(
                parent::findLatestByChannel($channel, $locale, $count),
                static fn (ProductInterface $product): bool => $product->hasVariants(),
            ),
        );
    }

    #[\Override]
    public function findOneByChannelAndSlug(ChannelInterface $channel, string $locale, string $slug): ?ProductInterface
    {
        $product = parent::findOneByChannelAndSlug($channel, $locale, $slug);

        return null !== $product && $product->hasVariants() ? $product : null;
    }
}
