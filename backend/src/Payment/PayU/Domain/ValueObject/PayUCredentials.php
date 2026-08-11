<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\ValueObject;

final readonly class PayUCredentials
{
    public function __construct(
        public PayUEnvironment $environment,
        public string $posId,
        public string $clientId,
        public string $clientSecret,
        public string $signatureKey,
        public ?string $payMethod = null,
    ) {
    }

    public function cacheKey(): string
    {
        return 'oma_payu_token_' . hash(
            'xxh128',
            implode(
                '|',
                [
                $this->environment->value,
                $this->clientId,
                $this->clientSecret,
                ],
            ),
        );
    }
}
