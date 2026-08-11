<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Command;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\ResultingCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            throw $this->unwrap($exception);
        }
    }

    public function dispatchAndReturn(ResultingCommand $command): mixed
    {
        try {
            $handled = $this->commandBus->dispatch($command)->last(HandledStamp::class);
        } catch (HandlerFailedException $exception) {
            throw $this->unwrap($exception);
        }

        if (!$handled instanceof HandledStamp) {
            throw new \LogicException(sprintf('Komenda "%s" nie została obsłużona.', $command::class));
        }

        return $handled->getResult();
    }

    private function unwrap(HandlerFailedException $exception): \Throwable
    {
        return $exception->getPrevious() ?? $exception;
    }
}
