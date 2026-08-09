<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\GoogleLoginAvailabilityChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GoogleLoginExtension extends AbstractExtension
{
    public function __construct(private readonly GoogleLoginAvailabilityChecker $availabilityChecker)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('oma_google_login_available', $this->availabilityChecker->isAvailable(...)),
        ];
    }
}
