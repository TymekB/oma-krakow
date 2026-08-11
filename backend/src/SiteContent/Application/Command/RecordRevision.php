<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Command;

use App\Shared\Application\Bus\Command;
use App\SiteContent\Domain\ValueObject\RevisionReason;

final readonly class RecordRevision implements Command
{
    /**
     * @param list<string> $changedKeys
     */
    public function __construct(
        public array $changedKeys,
        public string $author,
        public RevisionReason $reason,
    ) {
    }
}
