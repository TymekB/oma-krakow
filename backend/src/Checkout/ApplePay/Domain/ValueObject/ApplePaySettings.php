<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\ValueObject;

final readonly class ApplePaySettings
{
    public function __construct(
        public string $merchantId,
        public string $displayName,
        public string $paymentMethodCode,
        public string $certificatePath,
        public string $certificateKeyPath,
        public string $certificatePassphrase,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->merchantId;
    }

    public function hasCertificate(): bool
    {
        return '' !== $this->certificatePath && is_file($this->certificatePath);
    }
}
