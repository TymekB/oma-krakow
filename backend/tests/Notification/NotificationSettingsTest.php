<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\Notification\NotificationSetting;
use App\Notification\NotificationEvent;
use App\Notification\NotificationSettings;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationSettingsTest extends TestCase
{
    public function testShouldTreatEventWithoutSavedSettingAsEnabled(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $settings = new NotificationSettings($entityManager);

        self::assertTrue($settings->isEnabled(NotificationEvent::ADMIN_ORDER_PLACED->value));
    }

    public function testShouldReportEveryKnownEventInSettingsList(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $all = (new NotificationSettings($entityManager))->all();

        self::assertSame(
            array_map(static fn (NotificationEvent $event): string => $event->value, NotificationEvent::cases()),
            array_keys($all),
        );
    }

    public function testShouldCreateSettingsForEventsThatWereNeverSaved(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);
        $entityManager
            ->method('persist')
            ->willReturnCallback(
                static function (object $entity) use (&$persisted): void {
                    self::assertInstanceOf(NotificationSetting::class, $entity);
                    $persisted[$entity->getCode()] = $entity->isEnabled();
                }
            );

        (new NotificationSettings($entityManager))->save([NotificationEvent::ADMIN_ORDER_PLACED->value]);

        self::assertTrue($persisted[NotificationEvent::ADMIN_ORDER_PLACED->value]);
        self::assertFalse($persisted[NotificationEvent::ORDER_CONFIRMATION->value]);
    }

    public function testShouldFlipExistingSettingInsteadOfCreatingSecondOne(): void
    {
        $existing = new NotificationSetting(NotificationEvent::ADMIN_ORDER_PLACED->value, true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('find')
            ->willReturnCallback(
                static fn (string $class, mixed $id): ?NotificationSetting => NotificationEvent::ADMIN_ORDER_PLACED->value === $id ? $existing : null,
            );
        $entityManager->expects(self::exactly(count(NotificationEvent::cases()) - 1))->method('persist');

        (new NotificationSettings($entityManager))->save([]);

        self::assertFalse($existing->isEnabled());
    }
}
