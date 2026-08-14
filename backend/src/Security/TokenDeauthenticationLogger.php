<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;

#[AsEventListener(event: TokenDeauthenticatedEvent::class)]
final readonly class TokenDeauthenticationLogger
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TokenDeauthenticatedEvent $event): void
    {
        $token = $event->getOriginalToken();
        $user = $token->getUser();

        $this->logger->error('Sesja zostala uniewazniona przy odswiezaniu uzytkownika.', [
            'user_identifier' => $token->getUserIdentifier(),
            'user_class' => null === $user ? null : $user::class,
            'token_class' => $token::class,
            'roles' => $token->getRoleNames(),
            'password_hash_prefix' => $user instanceof PasswordAuthenticatedUserInterface
                ? substr((string) $user->getPassword(), 0, 12)
                : null,
            'path' => $event->getRequest()->getPathInfo(),
        ]);
    }
}
