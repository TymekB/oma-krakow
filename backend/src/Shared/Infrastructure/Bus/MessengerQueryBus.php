<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\Query;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBus
{
    public function __construct(
        #[Autowire(service: 'oma.query_bus')]
        private MessageBusInterface $queryBus,
    ) {
    }

    public function ask(Query $query): mixed
    {
        try {
            $handled = $this->queryBus->dispatch($query)->last(HandledStamp::class);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        if (!$handled instanceof HandledStamp) {
            throw new \LogicException(sprintf('Zapytanie "%s" nie zostało obsłużone.', $query::class));
        }

        return $handled->getResult();
    }
}
