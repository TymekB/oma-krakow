<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\ResultingCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'oma.command_bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $this->commandBus->dispatch($command);
    }

    public function dispatchAndReturn(ResultingCommand $command): mixed
    {
        $handled = $this->commandBus->dispatch($command)->last(HandledStamp::class);

        if (!$handled instanceof HandledStamp) {
            throw new \LogicException(sprintf('Komenda "%s" nie została obsłużona.', $command::class));
        }

        return $handled->getResult();
    }
}
