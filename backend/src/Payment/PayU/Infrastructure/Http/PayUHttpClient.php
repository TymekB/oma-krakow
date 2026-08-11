<?php

declare(strict_types=1);

namespace App\Payment\PayU\Api;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(PayUClientInterface::class)]
final readonly class PayUClient implements PayUClientInterface
{
    private const OAUTH_PATH = '/pl/standard/user/oauth/authorize';

    private const ORDERS_PATH = '/api/v2_1/orders';

    private const TOKEN_EXPIRATION_MARGIN = 60;

    private const MINIMUM_TOKEN_LIFETIME = 60;

    private const ACCEPTED_STATUS_CODES = [
        'SUCCESS',
        'SUCCESS_MULTI_CHANNEL',
        'WARNING_CONTINUE_REDIRECT',
        'WARNING_CONTINUE_3DS',
        'WARNING_CONTINUE_CVV',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
    ) {
    }

    public function createOrder(PayUCredentials $credentials, array $payload): PayUCreatedOrder
    {
        $data = $this->request(
            $credentials,
            'POST',
            self::ORDERS_PATH,
            [
            'json' => $payload,
            'max_redirects' => 0,
            ],
        );

        $extOrderId = $data['extOrderId'] ?? $payload['extOrderId'] ?? null;

        return new PayUCreatedOrder(
            $this->requiredString($data, 'orderId'),
            is_string($extOrderId) ? $extOrderId : '',
            $this->requiredString($data, 'redirectUri'),
        );
    }

    public function retrieveOrder(PayUCredentials $credentials, string $orderId): array
    {
        $data = $this->request($credentials, 'GET', self::ORDERS_PATH . '/' . rawurlencode($orderId), []);

        $orders = $data['orders'] ?? null;

        if (!is_array($orders) || [] === $orders) {
            throw new PayUApiException(sprintf('PayU returned no order data for order "%s".', $orderId));
        }

        $order = reset($orders);

        if (!is_array($order)) {
            throw new PayUApiException(sprintf('PayU returned malformed order data for order "%s".', $orderId));
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<array-key, mixed>
     */
    private function request(
        PayUCredentials $credentials,
        string $method,
        string $path,
        array $options,
        bool $retryOnUnauthorized = true,
    ): array {
        $options['auth_bearer'] = $this->accessToken($credentials);

        try {
            $response = $this->httpClient->request($method, $credentials->environment->baseUri() . $path, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $exception) {
            throw new PayUApiException(
                sprintf('PayU request to "%s" failed: %s', $path, $exception->getMessage()),
                previous: $exception,
            );
        }

        if (Response::HTTP_UNAUTHORIZED === $statusCode && $retryOnUnauthorized) {
            $this->cache->delete($credentials->cacheKey());

            return $this->request($credentials, $method, $path, $options, false);
        }

        $data = $this->decode($content, $path);
        $status = $data['status'] ?? [];
        $statusCodeLiteral = is_array($status) ? ($status['statusCode'] ?? null) : null;

        if (!is_string($statusCodeLiteral) || !in_array($statusCodeLiteral, self::ACCEPTED_STATUS_CODES, true)) {
            throw new PayUApiException(
                sprintf(
                    'PayU rejected the request to "%s" (HTTP %d, status "%s", description "%s").',
                    $path,
                    $statusCode,
                    is_string($statusCodeLiteral) ? $statusCodeLiteral : 'unknown',
                    is_array($status) && is_string($status['codeLiteral'] ?? null) ? $status['codeLiteral'] : '',
                ),
            );
        }

        return $data;
    }

    private function accessToken(PayUCredentials $credentials): string
    {
        return $this->cache->get(
            $credentials->cacheKey(),
            function (ItemInterface $item) use ($credentials): string {
                $data = $this->requestAccessToken($credentials);

                $expiresIn = $data['expires_in'] ?? null;
                $lifetime = is_int($expiresIn) ? $expiresIn - self::TOKEN_EXPIRATION_MARGIN : 0;
                $item->expiresAfter(max(self::MINIMUM_TOKEN_LIFETIME, $lifetime));

                return $this->requiredString($data, 'access_token');
            },
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function requestAccessToken(PayUCredentials $credentials): array
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                $credentials->environment->baseUri() . self::OAUTH_PATH,
                [
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $credentials->clientId,
                        'client_secret' => $credentials->clientSecret,
                    ],
                ],
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $exception) {
            throw new PayUApiException(
                sprintf('PayU authorization failed: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        if (Response::HTTP_OK !== $statusCode) {
            throw new PayUApiException(sprintf('PayU authorization failed with HTTP %d.', $statusCode));
        }

        return $this->decode($content, self::OAUTH_PATH);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $content, string $path): array
    {
        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new PayUApiException(
                sprintf('PayU response from "%s" is not a valid JSON.', $path),
                previous: $exception,
            );
        }

        if (!is_array($data)) {
            throw new PayUApiException(sprintf('PayU response from "%s" is not an object.', $path));
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || '' === $value) {
            throw new PayUApiException(sprintf('PayU response is missing "%s".', $key));
        }

        return $value;
    }
}
