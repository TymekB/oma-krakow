<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\Exception;

final class ApplePayNotConfigured extends ApplePayException
{
    public static function missingMerchantId(): self
    {
        return new self('Apple Pay wymaga zmiennej APPLE_PAY_MERCHANT_ID.');
    }

    public static function missingCertificate(string $path): self
    {
        return new self(sprintf('Brak certyfikatu Merchant Identity pod ścieżką "%s".', $path));
    }
}
