<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class AdminLoginThroughShopListener
{
    private const CSRF_PARAMETER = '_csrf_shop_security_token';
    private const CSRF_TOKEN_ID = 'shop_authenticate';

    public function __construct(
        #[Autowire(service: 'sylius.admin_user_provider.email_or_name_based')]
        private UserProviderInterface $adminUserProvider,
        private UserPasswordHasherInterface $passwordHasher,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethod('POST')) {
            return;
        }

        if ($request->getPathInfo() !== $this->urlGenerator->generate('sylius_shop_login_check')) {
            return;
        }

        $identifier = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');

        if ('' === $identifier || '' === $password) {
            return;
        }

        $csrfToken = (string) $request->request->get(self::CSRF_PARAMETER, '');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))) {
            return;
        }

        try {
            $adminUser = $this->adminUserProvider->loadUserByIdentifier($identifier);
        } catch (AuthenticationException) {
            return;
        }

        if (!$adminUser instanceof PasswordAuthenticatedUserInterface) {
            return;
        }

        if (!$this->passwordHasher->isPasswordValid($adminUser, $password)) {
            return;
        }

        $this->security->login($adminUser, 'form_login', 'admin');

        $event->setResponse(
            new RedirectResponse(
                $this->urlGenerator->generate('sylius_admin_dashboard'),
            )
        );
    }
}
