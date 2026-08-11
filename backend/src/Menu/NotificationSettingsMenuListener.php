<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MainMenuBuilder::EVENT_NAME, priority: -300)]
final class NotificationSettingsMenuListener
{
    private const NAME = 'oma_notification_settings';

    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');

        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild(self::NAME, ['route' => 'oma_admin_notification_settings'])
            ->setLabel('oma.ui.notification_settings')
            ->setLabelAttribute('icon', 'tabler:bell');

        $configuration->reorderChildren($this->orderWithNotificationsFirst($configuration));
    }

    /**
     * @return list<string>
     */
    private function orderWithNotificationsFirst(ItemInterface $configuration): array
    {
        $remaining = array_values(
            array_filter(
                array_keys($configuration->getChildren()),
                static fn (string $name): bool => self::NAME !== $name,
            )
        );

        return [self::NAME, ...$remaining];
    }
}
