<?php

declare(strict_types=1);

namespace App\SiteContent\UI\Http\Exception;

final class MalformedPayload extends \RuntimeException
{
    public static function notAnObject(): self
    {
        return new self('Nieprawidłowa treść.');
    }

    public static function valueIsNotAString(): self
    {
        return new self('Nieprawidłowa treść.');
    }

    public static function keyIsNotAString(): self
    {
        return new self('Nieprawidłowy klucz.');
    }

    public static function valueIsNotAStringFor(string $key): self
    {
        return new self(sprintf('Nieprawidłowa treść dla klucza: %s', $key));
    }
}
