<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Command;

use App\Shared\Application\Bus\ResultingCommand;

/**
 * @implements ResultingCommand<int> liczba przywróconych kluczy
 */
final readonly class RestoreRevision implements ResultingCommand
{
    public function __construct(
        public int $revisionId,
        public string $author,
    ) {
    }
}
