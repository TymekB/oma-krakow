<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\AdminRecipient;
use App\Notification\OrderPlacedAdminNotifier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class OrderPlacedAdminNotifierTest extends TestCase
{
    private const CONFIGURED_RECIPIENT = 'admin@oma-fizjo.pl';

    private const CHANNEL_CONTACT = 'kontakt@oma-fizjo.pl';

    public function testShouldSendNotificationToConfiguredRecipient(): void
    {
        $sender = new RecordingSender();

        (new OrderPlacedAdminNotifier($sender, $this->recipient(self::CONFIGURED_RECIPIENT)))(
            new GenericEvent($this->order()),
        );

        self::assertSame([[self::CONFIGURED_RECIPIENT]], $sender->recipients);
        self::assertSame([OrderPlacedAdminNotifier::EMAIL_CODE], $sender->codes);
    }

    public function testShouldFallBackToChannelContactEmailWhenRecipientIsNotConfigured(): void
    {
        $sender = new RecordingSender();

        (new OrderPlacedAdminNotifier($sender, $this->recipient('')))(new GenericEvent($this->order()));

        self::assertSame([[self::CHANNEL_CONTACT]], $sender->recipients);
    }

    public function testShouldPassOrderChannelAndLocaleToTemplate(): void
    {
        $sender = new RecordingSender();
        $order = $this->order();

        (new OrderPlacedAdminNotifier($sender, $this->recipient(self::CONFIGURED_RECIPIENT)))(new GenericEvent($order));

        self::assertSame($order, $sender->data[0]['order']);
        self::assertSame($order->getChannel(), $sender->data[0]['channel']);
        self::assertSame('pl_PL', $sender->data[0]['localeCode']);
    }

    public function testShouldNotSendAnythingWhenNoRecipientIsKnown(): void
    {
        $sender = new RecordingSender();

        (new OrderPlacedAdminNotifier($sender, $this->recipient('   ')))(
            new GenericEvent($this->order(channelContact: null)),
        );

        self::assertSame([], $sender->codes);
    }

    public function testShouldIgnoreSubjectsThatAreNotOrders(): void
    {
        $sender = new RecordingSender();

        (new OrderPlacedAdminNotifier($sender, $this->recipient(self::CONFIGURED_RECIPIENT)))(
            new GenericEvent(new \stdClass()),
        );

        self::assertSame([], $sender->codes);
    }

    private function order(?string $channelContact = self::CHANNEL_CONTACT): OrderInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getContactEmail')->willReturn($channelContact);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getLocaleCode')->willReturn('pl_PL');

        return $order;
    }

    private function recipient(string $configured): AdminRecipient
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));

        return new AdminRecipient($channelContext, $configured);
    }
}
