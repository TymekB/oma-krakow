<?php

declare(strict_types=1);

namespace App\Grid;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'sylius.grid.chain_provider')]
final class SingleChannelGridProvider implements GridProviderInterface
{
    private const CHANNEL_ELEMENT = 'channel';

    private ?bool $hasSingleChannel = null;

    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly GridProviderInterface $decorated,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function get(string $code): Grid
    {
        $grid = $this->decorated->get($code);

        if (!$this->hasSingleChannel()) {
            return $grid;
        }

        if ($grid->hasField(self::CHANNEL_ELEMENT)) {
            $grid->removeField(self::CHANNEL_ELEMENT);
        }

        if ($grid->hasFilter(self::CHANNEL_ELEMENT)) {
            $grid->removeFilter(self::CHANNEL_ELEMENT);
        }

        return $grid;
    }

    private function hasSingleChannel(): bool
    {
        return $this->hasSingleChannel ??= 1 === count($this->channelRepository->findAll());
    }
}
