<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\Exception;

final class ExpressCheckoutFailed extends ApplePayException
{
    public static function emptyCart(): self
    {
        return new self('Koszyk jest pusty.');
    }

    public static function incompleteContact(): self
    {
        return new self('Apple Pay nie przekazał kompletu danych do wysyłki.');
    }

    public static function noShippingMethod(): self
    {
        return new self('Dla podanego adresu nie ma dostępnej metody wysyłki.');
    }

    public static function unknownPaymentMethod(string $code): self
    {
        return new self(sprintf('Brak włączonej metody płatności "%s".', $code));
    }

    public static function checkoutRejected(string $transition): self
    {
        return new self(sprintf('Checkout odrzucił przejście "%s".', $transition));
    }
}
