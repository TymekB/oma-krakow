<?php

declare(strict_types=1);

namespace App\Entity\Notification;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'oma_notification_setting')]
class NotificationSetting
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled;

    public function __construct(string $code, bool $enabled)
    {
        $this->code = $code;
        $this->enabled = $enabled;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }
}
