<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\UI\Http\Request;

use App\Checkout\ApplePay\Domain\ValueObject\ApplePayContact;
use App\Checkout\ApplePay\UI\Http\Exception\MalformedApplePayPayload;
use Symfony\Component\HttpFoundation\Request;

final readonly class ApplePayPayload
{
    public function validationUrl(Request $request): string
    {
        $url = $this->decode($request)['validationUrl'] ?? null;

        if (!is_string($url) || '' === $url) {
            throw MalformedApplePayPayload::missingValidationUrl();
        }

        return $url;
    }

    public function shippingContact(Request $request): ApplePayContact
    {
        $payload = $this->decode($request);
        $contact = $payload['shippingContact'] ?? null;

        if (!is_array($contact)) {
            throw MalformedApplePayPayload::missingContact();
        }

        return $this->contactFrom($contact, []);
    }

    public function contact(Request $request): ApplePayContact
    {
        $payload = $this->decode($request);
        $shipping = $payload['shippingContact'] ?? null;
        $billing = $payload['billingContact'] ?? null;

        if (!is_array($shipping)) {
            throw MalformedApplePayPayload::missingContact();
        }

        return $this->contactFrom($shipping, is_array($billing) ? $billing : []);
    }

    public function shippingMethodCode(Request $request): ?string
    {
        $code = $this->decode($request)['shippingMethod'] ?? null;

        return is_string($code) && '' !== $code ? $code : null;
    }

    public function paymentToken(Request $request): ?string
    {
        $token = $this->decode($request)['paymentToken'] ?? null;

        if (is_string($token) && '' !== $token) {
            return $token;
        }

        if (!is_array($token) || [] === $token) {
            return null;
        }

        $encoded = json_encode($token);

        return false !== $encoded ? base64_encode($encoded) : null;
    }

    /**
     * @param array<array-key, mixed> $shipping
     * @param array<array-key, mixed> $billing
     */
    private function contactFrom(array $shipping, array $billing): ApplePayContact
    {
        $countryCode = $this->text($shipping, 'countryCode') ?? $this->text($billing, 'countryCode');

        if (null === $countryCode) {
            throw MalformedApplePayPayload::missingField('countryCode');
        }

        $postcode = $this->text($shipping, 'postalCode') ?? $this->text($billing, 'postalCode');

        if (null === $postcode) {
            throw MalformedApplePayPayload::missingField('postalCode');
        }

        return new ApplePayContact(
            $countryCode,
            $postcode,
            $this->text($shipping, 'locality') ?? $this->text($billing, 'locality') ?? '',
            $this->text($shipping, 'emailAddress') ?? $this->text($billing, 'emailAddress'),
            $this->text($shipping, 'givenName') ?? $this->text($billing, 'givenName'),
            $this->text($shipping, 'familyName') ?? $this->text($billing, 'familyName'),
            $this->street($shipping) ?? $this->street($billing),
            $this->text($shipping, 'phoneNumber') ?? $this->text($billing, 'phoneNumber'),
        );
    }

    /**
     * @param array<array-key, mixed> $contact
     */
    private function street(array $contact): ?string
    {
        $lines = $contact['addressLines'] ?? null;

        if (!is_array($lines)) {
            return null;
        }

        $parts = [];

        foreach ($lines as $line) {
            if (is_string($line) && '' !== trim($line)) {
                $parts[] = trim($line);
            }
        }

        return [] === $parts ? null : implode(', ', $parts);
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private function text(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Request $request): array
    {
        $content = $request->getContent();

        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw MalformedApplePayPayload::notAnObject();
        }

        if (!is_array($decoded)) {
            throw MalformedApplePayPayload::notAnObject();
        }

        return $decoded;
    }
}
