<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Factory\DefaultTaxCategoryProductVariantFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Model\ProductVariantInterface as BaseProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class DefaultTaxCategoryProductVariantFactoryTest extends TestCase
{
    private const DEFAULT_CODE = 'standard';

    public function testShouldSetDefaultTaxCategoryOnNewVariant(): void
    {
        // Given
        $taxCategory = $this->createMock(TaxCategoryInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getTaxCategory')->willReturn(null);
        $variant->expects(self::once())->method('setTaxCategory')->with($taxCategory);
        $factory = $this->factory($variant, $taxCategory);

        // When
        $result = $factory->createNew();

        // Then
        self::assertSame($variant, $result);
    }

    public function testShouldSetDefaultTaxCategoryOnVariantCreatedForProduct(): void
    {
        // Given
        $taxCategory = $this->createMock(TaxCategoryInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getTaxCategory')->willReturn(null);
        $variant->expects(self::once())->method('setTaxCategory')->with($taxCategory);
        $factory = $this->factory($variant, $taxCategory);

        // When
        $result = $factory->createForProduct($this->createMock(ProductInterface::class));

        // Then
        self::assertSame($variant, $result);
    }

    public function testShouldKeepTaxCategoryAlreadySetByDecoratedFactory(): void
    {
        // Given
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getTaxCategory')->willReturn($this->createMock(TaxCategoryInterface::class));
        $variant->expects(self::never())->method('setTaxCategory');

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->expects(self::never())->method('findOneBy');

        $factory = new DefaultTaxCategoryProductVariantFactory(
            $this->decorated($variant),
            $repository,
            self::DEFAULT_CODE,
        );

        // When
        $result = $factory->createNew();

        // Then
        self::assertSame($variant, $result);
    }

    public function testShouldLeaveTaxCategoryEmptyWhenDefaultCodeIsUnknown(): void
    {
        // Given
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getTaxCategory')->willReturn(null);
        $variant->expects(self::never())->method('setTaxCategory');
        $factory = $this->factory($variant, null);

        // When
        $result = $factory->createNew();

        // Then
        self::assertSame($variant, $result);
    }

    private function factory(ProductVariantInterface $variant, ?TaxCategoryInterface $taxCategory): DefaultTaxCategoryProductVariantFactory
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['code' => self::DEFAULT_CODE])
            ->willReturn($taxCategory);

        return new DefaultTaxCategoryProductVariantFactory(
            $this->decorated($variant),
            $repository,
            self::DEFAULT_CODE,
        );
    }

    /**
     * @return ProductVariantFactoryInterface<BaseProductVariantInterface>
     */
    private function decorated(ProductVariantInterface $variant): ProductVariantFactoryInterface
    {
        $decorated = $this->createMock(ProductVariantFactoryInterface::class);
        $decorated->method('createNew')->willReturn($variant);
        $decorated->method('createForProduct')->willReturn($variant);

        return $decorated;
    }
}
