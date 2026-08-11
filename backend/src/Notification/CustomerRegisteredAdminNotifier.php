<?php

declare(strict_types=1);

namespace App\Notification;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'sylius.customer.post_register')]
final readonly class CustomerRegisteredAdminNotifier
{
    public const EMAIL_CODE = 'admin_customer_registered';

    public function __construct(
        private SenderInterface $emailSender,
        private AdminRecipient $adminRecipient,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $customer = $event->getSubject();

        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $channel = $this->adminRecipient->currentChannel();
        $recipient = $this->adminRecipient->resolve($channel);

        if (null === $recipient) {
            return;
        }

        $this->emailSender->send(
            self::EMAIL_CODE,
            [$recipient],
            [
                'customer' => $customer,
                'channel' => $channel,
                'localeCode' => $this->localeContext->getLocaleCode(),
            ],
        );
    }
}
