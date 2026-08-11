<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Exception;

final class InvalidContentKey extends SiteContentException
{
    public static function forValue(string $value): self
    {
        return new self(sprintf('Nieprawidłowy klucz treści: %s', $value));
    }
}
