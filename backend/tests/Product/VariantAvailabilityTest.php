<?php

declare(strict_types=1);

namespace App\Tests\Product;

use App\Product\VariantAvailability;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;
use Sylius\Component\Product\Model\ProductOptionValueInterface;

final class VariantAvailabilityTest extends TestCase
{
    #[DataProvider('variantCases')]
    public function testShouldTellWhetherVariantCanBeBought(bool $enabled, bool $tracked, int $onHand, int $onHold, bool $expected): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());
        $variant = $this->variant($enabled, $tracked, $onHand, $onHold);

        // When
        $result = $availability->isAvailable($variant);

        // Then
        self::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{bool, bool, int, int, bool}>
     */
    public static function variantCases(): iterable
    {
        yield 'nieśledzony jest zawsze dostępny' => [true, false, 0, 0, true];
        yield 'śledzony z zapasem' => [true, true, 5, 2, true];
        yield 'śledzony z ostatnią sztuką' => [true, true, 1, 0, true];
        yield 'śledzony, cały stan zarezerwowany' => [true, true, 1, 1, false];
        yield 'śledzony bez stanu' => [true, true, 0, 0, false];
        yield 'wyłączony wariant, choć ma stan' => [false, true, 10, 0, false];
        yield 'wyłączony i nieśledzony' => [false, false, 0, 0, false];
    }

    public function testShouldFindProductAvailableWhenAnyVariantIs(): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());
        $product = $this->product([
            $this->variant(true, true, 1, 1),
            $this->variant(true, true, 100, 0),
        ]);

        // When & Then
        self::assertTrue($availability->hasAvailableVariant($product));
    }

    public function testShouldFindProductUnavailableWhenEveryVariantIsSoldOut(): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());
        $product = $this->product([
            $this->variant(true, true, 1, 1),
            $this->variant(true, true, 0, 0),
            $this->variant(false, false, 0, 0),
        ]);

        // When & Then
        self::assertFalse($availability->hasAvailableVariant($product));
    }

    public function testShouldFindProductUnavailableWithoutAnyVariant(): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());

        // When & Then
        self::assertFalse($availability->hasAvailableVariant($this->product([])));
    }

    public function testShouldMarkOptionValueSoldOutWhenItsOnlyVariantIs(): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());
        $soldOut = $this->createMock(ProductOptionValueInterface::class);
        $inStock = $this->createMock(ProductOptionValueInterface::class);

        $product = $this->product([
            $this->variantWithOptionValue($soldOut, true, true, 1, 1),
            $this->variantWithOptionValue($inStock, true, true, 100, 0),
        ]);

        // When & Then
        self::assertFalse($availability->isOptionValueAvailable($product, $soldOut));
        self::assertTrue($availability->isOptionValueAvailable($product, $inStock));
    }

    public function testShouldKeepOptionValueAvailableWhenAnotherVariantCarriesIt(): void
    {
        // Given
        $availability = new VariantAvailability(new AvailabilityChecker());
        $optionValue = $this->createMock(ProductOptionValueInterface::class);

        $product = $this->product([
            $this->variantWithOptionValue($optionValue, true, true, 0, 0),
            $this->variantWithOptionValue($optionValue, true, true, 3, 0),
        ]);

        // When & Then
        self::assertTrue($availability->isOptionValueAvailable($product, $optionValue));
    }

    private function variant(bool $enabled, bool $tracked, int $onHand, int $onHold): ProductVariantInterface&MockObject
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('isEnabled')->willReturn($enabled);
        $variant->method('isTracked')->willReturn($tracked);
        $variant->method('getOnHand')->willReturn($onHand);
        $variant->method('getOnHold')->willReturn($onHold);

        return $variant;
    }

    private function variantWithOptionValue(
        ProductOptionValueInterface $optionValue,
        bool $enabled,
        bool $tracked,
        int $onHand,
        int $onHold,
    ): ProductVariantInterface {
        $variant = $this->variant($enabled, $tracked, $onHand, $onHold);
        $variant->method('hasOptionValue')->willReturnCallback(
            static fn (ProductOptionValueInterface $candidate): bool => $candidate === $optionValue,
        );

        return $variant;
    }

    /**
     * @param list<ProductVariantInterface> $variants
     */
    private function product(array $variants): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getVariants')->willReturn(new ArrayCollection($variants));

        return $product;
    }
}
