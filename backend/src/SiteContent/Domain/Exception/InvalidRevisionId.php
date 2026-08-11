<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Exception;

final class InvalidRevisionId extends SiteContentException
{
    public static function notPositive(int $value): self
    {
        return new self(sprintf('Identyfikator wersji musi być dodatni, otrzymano %d.', $value));
    }
}
