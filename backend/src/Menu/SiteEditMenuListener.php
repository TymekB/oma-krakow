<?php

declare(strict_types=1);

namespace App\Menu;

use Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MainMenuBuilder::EVENT_NAME)]
final class SiteEditMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $configuration = $menu->getChild('configuration');

        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild('oma_site_edit', ['route' => 'oma_admin_site_edit'])
            ->setLabel('oma.ui.site_edit')
            ->setLabelAttribute('icon', 'tabler:edit');
    }
}
