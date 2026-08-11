<?php

declare(strict_types=1);

namespace App\Notification;

enum NotificationEvent: string
{
    case ADMIN_ORDER_PLACED = 'admin_order_placed';
    case ADMIN_CUSTOMER_REGISTERED = 'admin_customer_registered';
    case ADMIN_PRODUCT_REVIEW_CREATED = 'admin_product_review_created';
    case ADMIN_PRODUCT_OUT_OF_STOCK = 'admin_product_out_of_stock';
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

    public function recipient(): NotificationRecipient
    {
        return match ($this) {
            self::ADMIN_ORDER_PLACED,
            self::ADMIN_CUSTOMER_REGISTERED,
            self::ADMIN_PRODUCT_REVIEW_CREATED,
            self::ADMIN_PRODUCT_OUT_OF_STOCK,
            self::CONTACT_REQUEST => NotificationRecipient::ADMINISTRATOR,
            self::ORDER_CONFIRMATION,
            self::SHIPMENT_CONFIRMATION => NotificationRecipient::USER,
        };
    }
}
