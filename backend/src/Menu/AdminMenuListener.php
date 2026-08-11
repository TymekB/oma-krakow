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
        'channels',
        'countries',
        'zones',
        'currencies',
        'exchange_rates',
        'locales',
    ];

    private const MOVED_TO_SALES = [
        'payment_methods',
        'shipping_methods',
        'shipping_categories',
    ];

    private const SALES = 'sales';

    private const SECTION_ORDER = [
        'catalog',
        'sales',
        'marketing',
        'configuration',
        'customers',
    ];

    public function __invoke(MenuBuilderEvent $event): void
    {
        $this->moveToSales($event->getMenu());
        $this->removeFrom($event->getMenu());
        $this->orderSections($event->getMenu());
    }

    private function orderSections(ItemInterface $menu): void
    {
        $wanted = array_values(
            array_filter(
                self::SECTION_ORDER,
                static fn (string $name): bool => null !== $menu->getChild($name),
            ),
        );

        if ([] === $wanted) {
            return;
        }

        $order = [];
        $taken = 0;

        foreach (array_keys($menu->getChildren()) as $name) {
            $order[] = in_array($name, $wanted, true) ? $wanted[$taken++] : $name;
        }

        $menu->reorderChildren($order);
    }

    private function moveToSales(ItemInterface $menu): void
    {
        $sales = $menu->getChild(self::SALES);
        $configuration = $menu->getChild('configuration');

        if (null === $sales || null === $configuration) {
            return;
        }

        foreach (self::MOVED_TO_SALES as $name) {
            $item = $configuration->getChild($name);

            if (null === $item) {
                continue;
            }

            $configuration->removeChild($name);
            $sales->addChild($item);
        }
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
