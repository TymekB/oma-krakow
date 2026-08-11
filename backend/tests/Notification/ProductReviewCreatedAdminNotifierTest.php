<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\AdminRecipient;
use App\Notification\ProductReviewCreatedAdminNotifier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Review\Model\ReviewInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class ProductReviewCreatedAdminNotifierTest extends TestCase
{
    private const CONFIGURED_RECIPIENT = 'admin@oma-fizjo.pl';

    public function testShouldNotifyAdminAboutNewReview(): void
    {
        $sender = new RecordingSender();
        $review = $this->createMock(ReviewInterface::class);

        $this->notifier($sender)(new GenericEvent($review));

        self::assertSame([ProductReviewCreatedAdminNotifier::EMAIL_CODE], $sender->codes);
        self::assertSame([[self::CONFIGURED_RECIPIENT]], $sender->recipients);
        self::assertSame($review, $sender->data[0]['review']);
    }

    public function testShouldIgnoreSubjectsThatAreNotReviews(): void
    {
        $sender = new RecordingSender();

        $this->notifier($sender)(new GenericEvent(new \stdClass()));

        self::assertSame([], $sender->codes);
    }

    private function notifier(RecordingSender $sender): ProductReviewCreatedAdminNotifier
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));

        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('pl_PL');

        return new ProductReviewCreatedAdminNotifier(
            $sender,
            new AdminRecipient($channelContext, self::CONFIGURED_RECIPIENT),
            $localeContext,
        );
    }
}
