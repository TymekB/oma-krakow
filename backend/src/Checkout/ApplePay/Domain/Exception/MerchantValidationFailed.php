<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\Exception;

final class MerchantValidationFailed extends ApplePayException
{
    public static function untrustedUrl(string $url): self
    {
        return new self(sprintf('Adres walidacji "%s" nie należy do Apple.', $url));
    }

    public static function appleRejected(int $statusCode): self
    {
        return new self(sprintf('Apple odrzuciło walidację merchanta (HTTP %d).', $statusCode));
    }

    public static function transportError(string $reason): self
    {
        return new self(sprintf('Nie udało się połączyć z Apple: %s', $reason));
    }
}
