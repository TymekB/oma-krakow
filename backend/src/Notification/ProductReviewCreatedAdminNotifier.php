<?php

declare(strict_types=1);

namespace App\Notification;

use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\Review\Model\ReviewInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'sylius.product_review.post_create')]
final readonly class ProductReviewCreatedAdminNotifier
{
    public const EMAIL_CODE = 'admin_product_review_created';

    public function __construct(
        private SenderInterface $emailSender,
        private AdminRecipient $adminRecipient,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $review = $event->getSubject();

        if (!$review instanceof ReviewInterface) {
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
                'review' => $review,
                'channel' => $channel,
                'localeCode' => $this->localeContext->getLocaleCode(),
            ],
        );
    }
}
