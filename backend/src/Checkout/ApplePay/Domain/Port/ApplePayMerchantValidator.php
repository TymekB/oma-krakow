<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\Port;

use App\Checkout\ApplePay\Domain\Exception\ApplePayException;

interface ApplePayMerchantValidator
{
    /**
     * @return array<array-key, mixed>
     *
     * @throws ApplePayException
     */
    public function validate(string $validationUrl, string $domain): array;
}
