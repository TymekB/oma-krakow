<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MainMenuBuilder::EVENT_NAME, priority: -200)]
final class ManualMenuListener
{
    private const NAME = 'oma_manual';

    private const PRECEDING_ITEM = 'dashboard';

    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild(self::NAME, ['route' => 'oma_admin_manual'])
            ->setLabel('oma.ui.manual')
            ->setLabelAttribute('icon', 'tabler:book');

        $menu->reorderChildren($this->orderAfterDashboard($menu));
    }

    /**
     * @return list<string>
     */
    private function orderAfterDashboard(ItemInterface $menu): array
    {
        $order = [];

        foreach (array_keys($menu->getChildren()) as $name) {
            if (self::NAME === $name) {
                continue;
            }

            $order[] = $name;

            if (self::PRECEDING_ITEM === $name) {
                $order[] = self::NAME;
            }
        }

        return in_array(self::NAME, $order, true) ? $order : [...$order, self::NAME];
    }
}
