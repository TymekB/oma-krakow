<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\PriceTaxExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class PriceTaxExtensionTest extends TestCase
{
    private const CHANNEL_CODE = 'OMA_WEB';

    public function testShouldReportNoRateWhenVariantIsMissing(): void
    {
        // Given
        $resolver = $this->createMock(TaxRateResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $extension = new PriceTaxExtension($resolver, $this->channelRepository(null));

        // When
        $context = $extension->context(null, self::CHANNEL_CODE);

        // Then
        self::assertNull($context['rate']);
        self::assertFalse($context['included']);
    }

    public function testShouldReportNoRateWhenResolverFindsNothing(): void
    {
        // Given
        $extension = new PriceTaxExtension($this->resolver(null), $this->channelRepository(null));

        // When
        $context = $extension->context($this->createMock(ProductVariantInterface::class), self::CHANNEL_CODE);

        // Then
        self::assertNull($context['rate']);
    }

    public function testShouldReportNoRateForZeroPercentTax(): void
    {
        // Given
        $extension = new PriceTaxExtension($this->resolver($this->taxRate(0.0, false)), $this->channelRepository(null));

        // When
        $context = $extension->context($this->createMock(ProductVariantInterface::class), self::CHANNEL_CODE);

        // Then
        self::assertNull($context['rate'], 'stawka 0% nie ma czego przeliczac');
    }

    public function testShouldMarkStoredPriceAsNetWhenTaxIsAddedOnTop(): void
    {
        // Given
        $extension = new PriceTaxExtension($this->resolver($this->taxRate(0.23, false)), $this->channelRepository(null));

        // When
        $context = $extension->context($this->createMock(ProductVariantInterface::class), self::CHANNEL_CODE);

        // Then
        self::assertSame(0.23, $context['rate']);
        self::assertFalse($context['included'], 'cena w bazie jest netto, wiec lustro pokazuje brutto');
    }

    public function testShouldMarkStoredPriceAsGrossWhenTaxIsIncluded(): void
    {
        // Given
        $extension = new PriceTaxExtension($this->resolver($this->taxRate(0.23, true)), $this->channelRepository(null));

        // When
        $context = $extension->context($this->createMock(ProductVariantInterface::class), self::CHANNEL_CODE);

        // Then
        self::assertTrue($context['included'], 'cena w bazie jest brutto, wiec lustro pokazuje netto');
    }

    #[DataProvider('percentageCases')]
    public function testShouldFormatPercentageWithoutTrailingZeros(float $rate, string $expected): void
    {
        // Given
        $extension = new PriceTaxExtension($this->resolver($this->taxRate($rate, false)), $this->channelRepository(null));

        // When
        $context = $extension->context($this->createMock(ProductVariantInterface::class), self::CHANNEL_CODE);

        // Then
        self::assertSame($expected, $context['percentage']);
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function percentageCases(): iterable
    {
        yield 'VAT 23%' => [0.23, '23'];
        yield 'VAT 8%' => [0.08, '8'];
        yield 'VAT 5%' => [0.05, '5'];
        yield 'stawka z czescia dziesietna' => [0.085, '8,5'];
    }

    public function testShouldNarrowResolutionToChannelDefaultTaxZone(): void
    {
        // Given
        $zone = $this->createMock(ZoneInterface::class);
        $variant = $this->createMock(ProductVariantInterface::class);

        $resolver = $this->createMock(TaxRateResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with($variant, ['zone' => $zone])
            ->willReturn($this->taxRate(0.23, false));

        $extension = new PriceTaxExtension($resolver, $this->channelRepository($zone));

        // When
        $context = $extension->context($variant, self::CHANNEL_CODE);

        // Then
        self::assertSame(0.23, $context['rate']);
    }

    private function resolver(?TaxRateInterface $taxRate): TaxRateResolverInterface
    {
        $resolver = $this->createMock(TaxRateResolverInterface::class);
        $resolver->method('resolve')->willReturn($taxRate);

        return $resolver;
    }

    private function taxRate(float $amount, bool $includedInPrice): TaxRateInterface
    {
        $taxRate = $this->createMock(TaxRateInterface::class);
        $taxRate->method('getAmount')->willReturn($amount);
        $taxRate->method('isIncludedInPrice')->willReturn($includedInPrice);

        return $taxRate;
    }

    /**
     * @return RepositoryInterface<ChannelInterface>
     */
    private function channelRepository(?ZoneInterface $defaultTaxZone): RepositoryInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getDefaultTaxZone')->willReturn($defaultTaxZone);

        $repository = $this->createMock(RepositoryInterface::class);
        $repository->method('findOneBy')->willReturn($channel);

        return $repository;
    }
}
