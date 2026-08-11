<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Service;

final readonly class SignatureVerifier
{
    private const ALGORITHMS = [
        'MD5' => 'md5',
        'SHA1' => 'sha1',
        'SHA-1' => 'sha1',
        'SHA256' => 'sha256',
        'SHA-256' => 'sha256',
    ];

    public function verify(?string $header, string $body, string $signatureKey): bool
    {
        if (null === $header || '' === $header) {
            return false;
        }

        $parameters = $this->parse($header);
        $signature = $parameters['signature'] ?? null;
        $algorithm = self::ALGORITHMS[strtoupper($parameters['algorithm'] ?? 'MD5')] ?? null;

        if (null === $signature || null === $algorithm) {
            return false;
        }

        return hash_equals(hash($algorithm, $body . $signatureKey), strtolower($signature));
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        $parameters = [];

        foreach (explode(';', $header) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$name, $value] = explode('=', trim($part), 2);
            $parameters[strtolower(trim($name))] = trim($value);
        }

        return $parameters;
    }
}
