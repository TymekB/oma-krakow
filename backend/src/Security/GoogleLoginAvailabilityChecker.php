<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GoogleLoginAvailabilityChecker
{
    public function __construct(
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private string $clientSecret,
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }
}
