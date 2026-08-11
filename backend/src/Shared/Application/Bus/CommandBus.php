<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

interface CommandBus
{
    public function dispatch(Command $command): void;

    /**
     * @template TResult
     *
     * @param ResultingCommand<TResult> $command
     *
     * @return TResult
     */
    public function dispatchAndReturn(ResultingCommand $command): mixed;
}
