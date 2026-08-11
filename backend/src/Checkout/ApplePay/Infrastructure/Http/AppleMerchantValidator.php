<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Infrastructure\Http;

use App\Checkout\ApplePay\Domain\Exception\ApplePayNotConfigured;
use App\Checkout\ApplePay\Domain\Exception\MerchantValidationFailed;
use App\Checkout\ApplePay\Domain\Port\ApplePayMerchantValidator;
use App\Checkout\ApplePay\Infrastructure\Config\ApplePayConfiguration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AppleMerchantValidator implements ApplePayMerchantValidator
{
    private const ALLOWED_HOST_SUFFIX = '.apple.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ApplePayConfiguration $configuration,
    ) {
    }

    public function validate(string $validationUrl, string $domain): array
    {
        $settings = $this->configuration->settings();

        if (!$settings->isEnabled()) {
            throw ApplePayNotConfigured::missingMerchantId();
        }

        if (!$settings->hasCertificate()) {
            throw ApplePayNotConfigured::missingCertificate($settings->certificatePath);
        }

        $this->assertAppleUrl($validationUrl);

        $options = [
            'json' => [
                'merchantIdentifier' => $settings->merchantId,
                'displayName' => $settings->displayName,
                'initiative' => 'web',
                'initiativeContext' => $domain,
            ],
            'local_cert' => $settings->certificatePath,
            'local_pk' => '' !== $settings->certificateKeyPath ? $settings->certificateKeyPath : null,
            'passphrase' => '' !== $settings->certificatePassphrase ? $settings->certificatePassphrase : null,
        ];

        try {
            $response = $this->httpClient->request(
                'POST',
                $validationUrl,
                array_filter(
                    $options,
                    static fn (mixed $value): bool => null !== $value,
                ),
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $exception) {
            throw MerchantValidationFailed::transportError($exception->getMessage());
        }

        if (Response::HTTP_OK !== $statusCode) {
            throw MerchantValidationFailed::appleRejected($statusCode);
        }

        try {
            $session = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw MerchantValidationFailed::transportError($exception->getMessage());
        }

        if (!is_array($session)) {
            throw MerchantValidationFailed::appleRejected($statusCode);
        }

        return $session;
    }

    private function assertAppleUrl(string $validationUrl): void
    {
        $host = parse_url($validationUrl, \PHP_URL_HOST);
        $scheme = parse_url($validationUrl, \PHP_URL_SCHEME);

        if (!is_string($host) || 'https' !== $scheme || !str_ends_with($host, self::ALLOWED_HOST_SUFFIX)) {
            throw MerchantValidationFailed::untrustedUrl($validationUrl);
        }
    }
}
