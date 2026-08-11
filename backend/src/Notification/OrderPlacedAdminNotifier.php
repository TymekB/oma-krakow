<?php

declare(strict_types=1);

namespace App\Notification;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'sylius.order.post_complete')]
final readonly class OrderPlacedAdminNotifier
{
    public const EMAIL_CODE = 'admin_order_placed';

    public function __construct(
        private SenderInterface $emailSender,
        #[Autowire('%env(ADMIN_NOTIFICATION_EMAIL)%')]
        private string $configuredRecipient,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $order = $event->getSubject();

        if (!$order instanceof OrderInterface) {
            return;
        }

        $recipient = $this->resolveRecipient($order);

        if (null === $recipient) {
            return;
        }

        $this->emailSender->send(
            self::EMAIL_CODE,
            [$recipient],
            [
                'order' => $order,
                'channel' => $order->getChannel(),
                'localeCode' => $order->getLocaleCode(),
            ],
        );
    }

    private function resolveRecipient(OrderInterface $order): ?string
    {
        $configured = trim($this->configuredRecipient);

        if ('' !== $configured) {
            return $configured;
        }

        $channel = $order->getChannel();

        if (!$channel instanceof ChannelInterface) {
            return null;
        }

        $contactEmail = $channel->getContactEmail();

        return '' === trim((string) $contactEmail) ? null : $contactEmail;
    }
}
