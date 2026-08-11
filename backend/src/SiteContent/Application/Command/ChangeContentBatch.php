<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Command;

use App\Shared\Application\Bus\Command;

final readonly class ChangeContentBatch implements Command
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        public array $values,
        public string $author,
    ) {
    }
}
