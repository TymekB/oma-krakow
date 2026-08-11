<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\Product\ProductVariant;
use App\Notification\AdminRecipient;
use App\Notification\ProductOutOfStockAdminNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Inventory\Checker\AvailabilityChecker;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class ProductOutOfStockAdminNotifierTest extends TestCase
{
    private const CONFIGURED_RECIPIENT = 'admin@oma-fizjo.pl';

    /**
     * @param array<string, array<int, mixed>> $changeSet
     */
    #[DataProvider('stockChanges')]
    public function testShouldNotifyOnlyAtTheMomentStockRunsOut(
        bool $tracked,
        int $onHand,
        int $onHold,
        array $changeSet,
        bool $shouldNotify,
    ): void {
        // Given
        $sender = new RecordingSender();
        $notifier = $this->notifier($sender);
        $variant = $this->variant($tracked, $onHand, $onHold);

        // When
        $notifier->rememberIfSoldOut($variant, $changeSet);
        $notifier->postFlush($this->postFlush());

        // Then
        self::assertSame($shouldNotify ? [ProductOutOfStockAdminNotifier::EMAIL_CODE] : [], $sender->codes);
    }

    /**
     * @return iterable<string, array{bool, int, int, array<string, array<int, mixed>>, bool}>
     */
    public static function stockChanges(): iterable
    {
        yield 'ostatnia sztuka zarezerwowana zamowieniem' => [true, 1, 1, ['onHold' => [0, 1]], true];
        yield 'stan wyzerowany w panelu' => [true, 0, 0, ['onHand' => [3, 0]], true];
        yield 'wlaczone sledzenie przy zerowym stanie' => [true, 0, 0, ['tracked' => [false, true]], true];
        yield 'zostala jeszcze jedna sztuka' => [true, 2, 1, ['onHold' => [0, 1]], false];
        yield 'produkt bez sledzenia stanu' => [false, 0, 0, ['onHand' => [1, 0]], false];
        yield 'stan byl juz zerowy' => [true, 0, 0, ['onHold' => [5, 5]], false];
        yield 'uzupelnienie stanu' => [true, 5, 0, ['onHand' => [0, 5]], false];
    }

    public function testShouldSendOneMailPerSoldOutVariant(): void
    {
        // Given
        $sender = new RecordingSender();
        $notifier = $this->notifier($sender);
        $first = $this->variant(true, 1, 1);
        $second = $this->variant(true, 0, 0);

        // When
        $notifier->rememberIfSoldOut($first, ['onHold' => [0, 1]]);
        $notifier->rememberIfSoldOut($second, ['onHand' => [2, 0]]);
        $notifier->postFlush($this->postFlush());

        // Then
        self::assertCount(2, $sender->codes);
        self::assertSame([[self::CONFIGURED_RECIPIENT], [self::CONFIGURED_RECIPIENT]], $sender->recipients);
        self::assertSame($first, $sender->data[0]['variant']);
        self::assertSame($second, $sender->data[1]['variant']);
    }

    public function testShouldForgetVariantsAfterNotifying(): void
    {
        // Given
        $sender = new RecordingSender();
        $notifier = $this->notifier($sender);
        $notifier->rememberIfSoldOut($this->variant(true, 1, 1), ['onHold' => [0, 1]]);

        // When
        $notifier->postFlush($this->postFlush());
        $notifier->postFlush($this->postFlush());

        // Then
        self::assertCount(1, $sender->codes, 'kolejny flush nie powtarza maila');
    }

    private function variant(bool $tracked, int $onHand, int $onHold): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setTracked($tracked);
        $variant->setOnHand($onHand);
        $variant->setOnHold($onHold);

        return $variant;
    }

    private function postFlush(): PostFlushEventArgs
    {
        return new PostFlushEventArgs($this->createMock(EntityManagerInterface::class));
    }

    private function notifier(RecordingSender $sender): ProductOutOfStockAdminNotifier
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('pl_PL');

        return new ProductOutOfStockAdminNotifier(
            $sender,
            new AdminRecipient($channelContext, self::CONFIGURED_RECIPIENT),
            $localeContext,
            new AvailabilityChecker(),
        );
    }
}
