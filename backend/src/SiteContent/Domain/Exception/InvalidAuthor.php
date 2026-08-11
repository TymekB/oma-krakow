<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Exception;

final class InvalidAuthor extends SiteContentException
{
    public static function blank(): self
    {
        return new self('Autor rewizji nie może być pusty.');
    }

    public static function tooLong(int $length, int $limit): self
    {
        return new self(sprintf('Autor ma %d znaków, limit to %d.', $length, $limit));
    }
}
