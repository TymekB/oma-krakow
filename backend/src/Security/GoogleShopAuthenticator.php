<?php

declare(strict_types=1);

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Canonicalizer\CanonicalizerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleShopAuthenticator extends OAuth2Authenticator
{
    public const CHECK_ROUTE = 'oma_shop_google_check';

    private const RANDOM_PASSWORD_BYTES = 32;

    /**
     * @param RepositoryInterface<ShopUserInterface> $shopUserRepository
     * @param RepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<ShopUserInterface>    $shopUserFactory
     * @param FactoryInterface<CustomerInterface>    $customerFactory
     */
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RepositoryInterface $shopUserRepository,
        private readonly RepositoryInterface $customerRepository,
        private readonly FactoryInterface $shopUserFactory,
        private readonly FactoryInterface $customerFactory,
        private readonly CanonicalizerInterface $canonicalizer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(Request $request): bool
    {
        return self::CHECK_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = $this->fetchAccessToken($this->googleClient());

        return new SelfValidatingPassport(
            new UserBadge(
                $accessToken->getToken(),
                function () use ($accessToken): ShopUserInterface {
                    /**
                     * @var GoogleUser $googleUser
                     */
                    $googleUser = $this->googleClient()->fetchUserFromToken($accessToken);

                    return $this->resolveShopUser($googleUser);
                },
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->logger->error(
            'Logowanie przez Google nie powiodlo sie.',
            [
            'exception' => $exception,
            'google_error' => $request->query->get('error'),
            ],
        );

        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'oma.google_login.failed');
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_shop_login'));
    }

    private function resolveShopUser(GoogleUser $googleUser): ShopUserInterface
    {
        $email = $googleUser->getEmail();

        if (null === $email) {
            throw new AuthenticationException('Konto Google nie udostępniło adresu e-mail.');
        }

        $canonicalEmail = $this->canonicalizer->canonicalize($email) ?? $email;

        $existingUser = $this->shopUserRepository->findOneBy(['username' => $canonicalEmail]);

        if ($existingUser instanceof ShopUserInterface) {
            $this->activateUnverifiedUser($existingUser, $googleUser);

            return $existingUser;
        }

        return $this->createShopUser($googleUser, $email, $canonicalEmail);
    }

    private function activateUnverifiedUser(ShopUserInterface $shopUser, GoogleUser $googleUser): void
    {
        if ($shopUser->isEnabled() && $shopUser->isVerified()) {
            return;
        }

        if (!$googleUser->isEmailTrustworthy()) {
            throw new AuthenticationException('Google nie potwierdzilo wlasnosci adresu e-mail.');
        }

        $shopUser->setEnabled(true);
        $shopUser->setVerifiedAt(new \DateTime());
        $shopUser->setPlainPassword(bin2hex(random_bytes(self::RANDOM_PASSWORD_BYTES)));

        $this->shopUserRepository->add($shopUser);
    }

    private function createShopUser(GoogleUser $googleUser, string $email, string $canonicalEmail): ShopUserInterface
    {
        $customer = $this->customerRepository->findOneBy(['emailCanonical' => $canonicalEmail]);

        if (!$customer instanceof CustomerInterface) {
            /**
             * @var CustomerInterface $customer
             */
            $customer = $this->customerFactory->createNew();
            $customer->setEmail($email);
            $customer->setFirstName($googleUser->getFirstName());
            $customer->setLastName($googleUser->getLastName());

            $this->customerRepository->add($customer);
        }

        if ($customer->getUser() instanceof ShopUserInterface) {
            return $customer->getUser();
        }

        /**
         * @var ShopUserInterface $shopUser
         */
        $shopUser = $this->shopUserFactory->createNew();
        $shopUser->setCustomer($customer);
        $shopUser->setUsername($email);
        $shopUser->setUsernameCanonical($canonicalEmail);
        $shopUser->setPlainPassword(bin2hex(random_bytes(self::RANDOM_PASSWORD_BYTES)));
        $shopUser->setEnabled(true);
        $shopUser->setVerifiedAt(new \DateTime());

        $this->shopUserRepository->add($shopUser);

        return $shopUser;
    }

    private function googleClient(): GoogleClient
    {
        /**
         * @var GoogleClient $client
         */
        $client = $this->clientRegistry->getClient('google');

        return $client;
    }
}
