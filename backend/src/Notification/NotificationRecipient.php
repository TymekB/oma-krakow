<?php

declare(strict_types=1);

namespace App\Notification;

enum NotificationRecipient: string
{
    case ADMINISTRATOR = 'administrator';
    case USER = 'user';

    public function label(): string
    {
        return sprintf('oma.notification.recipient.%s', $this->value);
    }
}
