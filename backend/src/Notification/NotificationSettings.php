<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification\NotificationSetting;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationSettings
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function isEnabled(string $code): bool
    {
        if (null === NotificationEvent::tryFrom($code)) {
            return true;
        }

        $setting = $this->entityManager->find(NotificationSetting::class, $code);

        return null === $setting || $setting->isEnabled();
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        $enabled = [];

        foreach (NotificationEvent::cases() as $event) {
            $enabled[$event->value] = $this->isEnabled($event->value);
        }

        return $enabled;
    }

    /**
     * @param list<string> $enabledCodes
     */
    public function save(array $enabledCodes): void
    {
        foreach (NotificationEvent::cases() as $event) {
            $shouldBeEnabled = in_array($event->value, $enabledCodes, true);
            $setting = $this->entityManager->find(NotificationSetting::class, $event->value);

            if (null === $setting) {
                $this->entityManager->persist(new NotificationSetting($event->value, $shouldBeEnabled));

                continue;
            }

            $shouldBeEnabled ? $setting->enable() : $setting->disable();
        }

        $this->entityManager->flush();
    }
}
