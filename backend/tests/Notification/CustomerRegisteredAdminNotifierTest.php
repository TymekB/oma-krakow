<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\AdminRecipient;
use App\Notification\CustomerRegisteredAdminNotifier;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final class CustomerRegisteredAdminNotifierTest extends TestCase
{
    private const CHANNEL_CONTACT = 'kontakt@oma-fizjo.pl';

    public function testShouldNotifyAdminAboutNewAccount(): void
    {
        $sender = new RecordingSender();
        $customer = $this->createMock(CustomerInterface::class);

        $this->notifier($sender)(new GenericEvent($customer));

        self::assertSame([CustomerRegisteredAdminNotifier::EMAIL_CODE], $sender->codes);
        self::assertSame([[self::CHANNEL_CONTACT]], $sender->recipients);
        self::assertSame($customer, $sender->data[0]['customer']);
        self::assertSame('pl_PL', $sender->data[0]['localeCode']);
    }

    public function testShouldIgnoreSubjectsThatAreNotCustomers(): void
    {
        $sender = new RecordingSender();

        $this->notifier($sender)(new GenericEvent(new \stdClass()));

        self::assertSame([], $sender->codes);
    }

    public function testShouldStaySilentWhenThereIsNoChannelToTakeContactEmailFrom(): void
    {
        $sender = new RecordingSender();

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $notifier = new CustomerRegisteredAdminNotifier(
            $sender,
            new AdminRecipient($channelContext, ''),
            $this->localeContext(),
        );

        $notifier(new GenericEvent($this->createMock(CustomerInterface::class)));

        self::assertSame([], $sender->codes);
    }

    private function notifier(RecordingSender $sender): CustomerRegisteredAdminNotifier
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getContactEmail')->willReturn(self::CHANNEL_CONTACT);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        return new CustomerRegisteredAdminNotifier(
            $sender,
            new AdminRecipient($channelContext, ''),
            $this->localeContext(),
        );
    }

    private function localeContext(): LocaleContextInterface
    {
        $localeContext = $this->createMock(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('pl_PL');

        return $localeContext;
    }
}
