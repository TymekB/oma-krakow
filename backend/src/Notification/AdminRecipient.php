<?php

declare(strict_types=1);

namespace App\Notification;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AdminRecipient
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        #[Autowire('%env(ADMIN_NOTIFICATION_EMAIL)%')]
        private string $configuredRecipient,
    ) {
    }

    public function resolve(?ChannelInterface $channel = null): ?string
    {
        $configured = trim($this->configuredRecipient);

        if ('' !== $configured) {
            return $configured;
        }

        $contactEmail = trim((string) ($channel ?? $this->currentChannel())?->getContactEmail());

        return '' === $contactEmail ? null : $contactEmail;
    }

    public function currentChannel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }
}
