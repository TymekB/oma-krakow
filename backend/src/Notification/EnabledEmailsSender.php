<?php

declare(strict_types=1);

namespace App\Notification;

use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: 'sylius.email_sender')]
final readonly class EnabledEmailsSender implements SenderInterface
{
    public function __construct(
        #[AutowireDecorated]
        private SenderInterface $decorated,
        private NotificationSettings $settings,
    ) {
    }

    /**
     * @param array<int, string|null> $recipients
     * @param array<string, mixed>    $data
     * @param array<int, string>      $attachments
     * @param array<int, string>      $replyTo
     */
    public function send(
        string $code,
        array $recipients,
        array $data = [],
        array $attachments = [],
        array $replyTo = [],
    ): void {
        if (!$this->settings->isEnabled($code)) {
            return;
        }

        $this->decorated->send($code, $recipients, $data, $attachments, $replyTo);
    }
}
