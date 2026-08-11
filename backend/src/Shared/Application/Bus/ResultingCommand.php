<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

/**
 * Komenda, której wynik jest potrzebny wołającemu (np. liczba zmienionych rekordów).
 *
 * @template-covariant TResult
 */
interface ResultingCommand extends Command
{
}
