<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\GoogleLoginAvailabilityChecker;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class GoogleConnectController
{
    private const SCOPES = ['email', 'profile'];

    public function __construct(
        private ClientRegistry $clientRegistry,
        private GoogleLoginAvailabilityChecker $availabilityChecker,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sklep/connect/google', name: 'oma_shop_google_connect', methods: ['GET'])]
    public function connect(): RedirectResponse
    {
        if (!$this->availabilityChecker->isAvailable()) {
            throw new NotFoundHttpException('Logowanie przez Google nie jest skonfigurowane.');
        }

        return $this->clientRegistry->getClient('google')->redirect(self::SCOPES, []);
    }

    #[Route('/sklep/connect/google/check', name: 'oma_shop_google_check', methods: ['GET'])]
    public function check(): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }
}
