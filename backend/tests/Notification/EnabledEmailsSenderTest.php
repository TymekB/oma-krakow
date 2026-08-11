<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\Notification\NotificationSetting;
use App\Notification\EnabledEmailsSender;
use App\Notification\NotificationEvent;
use App\Notification\NotificationSettings;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class EnabledEmailsSenderTest extends TestCase
{
    public function testShouldPassEmailThroughWhenEventIsEnabled(): void
    {
        $decorated = new RecordingSender();
        $sender = new EnabledEmailsSender($decorated, $this->settings(enabled: true));

        $sender->send(NotificationEvent::ORDER_CONFIRMATION->value, ['klient@example.com']);

        self::assertSame([NotificationEvent::ORDER_CONFIRMATION->value], $decorated->codes);
    }

    public function testShouldHoldBackEmailWhenEventIsDisabled(): void
    {
        $decorated = new RecordingSender();
        $sender = new EnabledEmailsSender($decorated, $this->settings(enabled: false));

        $sender->send(NotificationEvent::ORDER_CONFIRMATION->value, ['klient@example.com']);

        self::assertSame([], $decorated->codes);
    }

    public function testShouldNeverBlockEmailsThatAreNotToggleable(): void
    {
        $decorated = new RecordingSender();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $sender = new EnabledEmailsSender($decorated, new NotificationSettings($entityManager));

        $sender->send('password_reset', ['klient@example.com']);

        self::assertSame(['password_reset'], $decorated->codes);
    }

    public function testShouldForwardAllSendArguments(): void
    {
        $decorated = new RecordingSender();
        $sender = new EnabledEmailsSender($decorated, $this->settings(enabled: true));

        $sender->send(NotificationEvent::ORDER_CONFIRMATION->value, ['klient@example.com'], ['order' => 'x']);

        self::assertSame([['klient@example.com']], $decorated->recipients);
        self::assertSame([['order' => 'x']], $decorated->data);
    }

    private function settings(bool $enabled): NotificationSettings
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('find')
            ->willReturn(new NotificationSetting(NotificationEvent::ORDER_CONFIRMATION->value, $enabled));

        return new NotificationSettings($entityManager);
    }
}
