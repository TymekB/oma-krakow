<?php

declare(strict_types=1);

namespace App\Menu;

use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MainMenuBuilder::EVENT_NAME)]
final class NotificationSettingsMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');

        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild('oma_notification_settings', ['route' => 'oma_admin_notification_settings'])
            ->setLabel('oma.ui.notification_settings')
            ->setLabelAttribute('icon', 'tabler:bell');
    }
}
