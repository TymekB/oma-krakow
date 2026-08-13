<?php

declare(strict_types=1);

namespace App\Tests\Product;

use App\Product\AvailableProductVariantResolver;
use App\Product\VariantAvailability;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;

final class AvailableProductVariantResolverTest extends TestCase
{
    private const PRODUCT_ID = 29;

    public function testShouldSkipSoldOutVariantsAndReturnTheFirstAvailableOne(): void
    {
        // Given
        $soldOut = $this->variant(true, true, 1, 1);
        $available = $this->variant(true, true, 100, 0);
        $resolver = $this->resolver([$soldOut, $available]);

        // When
        $variant = $resolver->getVariant($this->product(self::PRODUCT_ID));

        // Then
        self::assertSame($available, $variant);
    }

    public function testShouldReturnNullWhenEveryVariantIsSoldOut(): void
    {
        // Given
        $resolver = $this->resolver([$this->variant(true, true, 1, 1), $this->variant(true, true, 0, 0)]);

        // When
        $variant = $resolver->getVariant($this->product(self::PRODUCT_ID));

        // Then
        self::assertNull($variant, 'brak dostępnego wariantu oddaje decyzję domyślnemu resolverowi');
    }

    public function testShouldReturnNullWithoutAnyVariant(): void
    {
        // Given
        $resolver = $this->resolver([]);

        // When & Then
        self::assertNull($resolver->getVariant($this->product(self::PRODUCT_ID)));
    }

    public function testShouldFallBackToEnabledVariantsForUnsavedProduct(): void
    {
        // Given
        $available = $this->variant(true, true, 4, 0);

        $repository = $this->createMock(ProductVariantRepositoryInterface::class);
        $repository->expects(self::never())->method('findBy');

        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(null);
        $product->method('getEnabledVariants')->willReturn(new ArrayCollection([$available]));

        $resolver = new AvailableProductVariantResolver($repository, new VariantAvailability(new AvailabilityChecker()));

        // When
        $variant = $resolver->getVariant($product);

        // Then
        self::assertSame($available, $variant);
    }

    public function testShouldAskRepositoryForEnabledVariantsInPositionOrder(): void
    {
        // Given
        $product = $this->product(self::PRODUCT_ID);

        $repository = $this->createMock(ProductVariantRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findBy')
            ->with(['product' => $product, 'enabled' => true], ['position' => 'ASC', 'id' => 'ASC'])
            ->willReturn([]);

        $resolver = new AvailableProductVariantResolver($repository, new VariantAvailability(new AvailabilityChecker()));

        // When & Then
        self::assertNull($resolver->getVariant($product));
    }

    /**
     * @param list<ProductVariantInterface> $variants
     */
    private function resolver(array $variants): AvailableProductVariantResolver
    {
        $repository = $this->createMock(ProductVariantRepositoryInterface::class);
        $repository->method('findBy')->willReturn($variants);

        return new AvailableProductVariantResolver($repository, new VariantAvailability(new AvailabilityChecker()));
    }

    private function variant(bool $enabled, bool $tracked, int $onHand, int $onHold): ProductVariantInterface
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isEnabled')->willReturn($enabled);
        $variant->method('isTracked')->willReturn($tracked);
        $variant->method('getOnHand')->willReturn($onHand);
        $variant->method('getOnHold')->willReturn($onHold);

        return $variant;
    }

    private function product(int $id): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }
}
