<?php

declare(strict_types=1);

namespace App\Notification;

enum NotificationEvent: string
{
    case ADMIN_ORDER_PLACED = 'admin_order_placed';
    case ORDER_CONFIRMATION = 'order_confirmation';
    case SHIPMENT_CONFIRMATION = 'shipment_confirmation';
    case CONTACT_REQUEST = 'contact_request';

    public function label(): string
    {
        return sprintf('oma.notification.%s.label', $this->value);
    }

    public function description(): string
    {
        return sprintf('oma.notification.%s.description', $this->value);
    }
}
