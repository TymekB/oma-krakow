<?php

declare(strict_types=1);

namespace App\Payment\PayU\Infrastructure\Sylius;

use App\Payment\PayU\Domain\Exception\PayUApiException;
use App\Payment\PayU\Domain\ValueObject\PayUCredentials;
use App\Payment\PayU\Domain\ValueObject\PayUEnvironment;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;

final readonly class GatewayConfigCredentialsProvider
{
    public function provide(?PaymentMethodInterface $paymentMethod): PayUCredentials
    {
        $gatewayConfig = $paymentMethod?->getGatewayConfig();

        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            throw new PayUApiException('Payment method has no PayU gateway configuration.');
        }

        $config = $gatewayConfig->getConfig();

        return new PayUCredentials(
            $this->environment($config),
            $this->requiredString($config, 'pos_id'),
            $this->requiredString($config, 'client_id'),
            $this->requiredString($config, 'client_secret'),
            $this->requiredString($config, 'signature_key'),
            $this->optionalString($config, 'pay_method'),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function optionalString(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function environment(array $config): PayUEnvironment
    {
        $environment = $config['environment'] ?? null;

        if (!is_string($environment)) {
            return PayUEnvironment::Sandbox;
        }

        return PayUEnvironment::tryFrom($environment) ?? PayUEnvironment::Sandbox;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || '' === trim($value)) {
            throw new PayUApiException(sprintf('PayU gateway configuration is missing "%s".', $key));
        }

        return trim($value);
    }
}
