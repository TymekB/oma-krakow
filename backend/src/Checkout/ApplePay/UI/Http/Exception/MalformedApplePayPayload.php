<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\UI\Http\Exception;

final class MalformedApplePayPayload extends \RuntimeException
{
    public static function notAnObject(): self
    {
        return new self('Treść żądania nie jest obiektem JSON.');
    }

    public static function missingContact(): self
    {
        return new self('Brak danych kontaktowych z arkusza Apple Pay.');
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('Arkusz Apple Pay nie przekazał pola "%s".', $field));
    }

    public static function missingValidationUrl(): self
    {
        return new self('Brak adresu walidacji merchanta.');
    }
}
