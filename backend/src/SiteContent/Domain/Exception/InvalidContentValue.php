<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Exception;

final class InvalidContentValue extends SiteContentException
{
    public static function tooLong(int $length, int $limit): self
    {
        return new self(sprintf('Treść ma %d znaków, limit to %d.', $length, $limit));
    }
}
