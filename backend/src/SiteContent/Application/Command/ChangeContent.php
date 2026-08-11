<?php

declare(strict_types=1);

namespace App\SiteContent\Application\Command;

use App\Shared\Application\Bus\Command;

final readonly class ChangeContent implements Command
{
    public function __construct(
        public string $key,
        public string $value,
    ) {
    }
}
