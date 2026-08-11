<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Infrastructure\Config;

use App\Checkout\ApplePay\Domain\ValueObject\ApplePaySettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ApplePayConfiguration
{
    public function __construct(
        #[Autowire('%env(APPLE_PAY_MERCHANT_ID)%')]
        private string $merchantId,
        #[Autowire('%env(APPLE_PAY_DISPLAY_NAME)%')]
        private string $displayName,
        #[Autowire('%env(APPLE_PAY_PAYMENT_METHOD_CODE)%')]
        private string $paymentMethodCode,
        #[Autowire('%env(APPLE_PAY_CERT_PATH)%')]
        private string $certificatePath,
        #[Autowire('%env(APPLE_PAY_CERT_KEY_PATH)%')]
        private string $certificateKeyPath,
        #[Autowire('%env(APPLE_PAY_CERT_PASSPHRASE)%')]
        private string $certificatePassphrase,
    ) {
    }

    public function settings(): ApplePaySettings
    {
        return new ApplePaySettings(
            trim($this->merchantId),
            '' !== trim($this->displayName) ? trim($this->displayName) : 'OMA',
            '' !== trim($this->paymentMethodCode) ? trim($this->paymentMethodCode) : 'payu_apple_pay',
            trim($this->certificatePath),
            trim($this->certificateKeyPath),
            $this->certificatePassphrase,
        );
    }
}
