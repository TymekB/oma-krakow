<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MainMenuBuilder::EVENT_NAME, priority: -100)]
final class AdminMenuListener
{
    private const REMOVED_ITEMS = [
        'official_support',
        'sylius.ui.administration',
        'credit_memos',
        'mollie_subscriptions',
    ];

    public function __invoke(MenuBuilderEvent $event): void
    {
        $this->removeFrom($event->getMenu());
    }

    private function removeFrom(ItemInterface $item): void
    {
        foreach ($item->getChildren() as $name => $child) {
            if (in_array($name, self::REMOVED_ITEMS, true)) {
                $item->removeChild($name);

                continue;
            }

            $this->removeFrom($child);
        }
    }
}
