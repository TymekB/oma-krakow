<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

enum RevisionReason: string
{
    case Edit = 'edit';
    case Restore = 'restore';
}
