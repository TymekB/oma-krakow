<?php

declare(strict_types=1);

namespace App\Tests\Checkout\ApplePay\UI\Http\Request;

use App\Checkout\ApplePay\UI\Http\Exception\MalformedApplePayPayload;
use App\Checkout\ApplePay\UI\Http\Request\ApplePayPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ApplePayPayloadTest extends TestCase
{
    private const FULL_CONTACT = [
        'emailAddress' => 'kupujaca@oma.local',
        'givenName' => 'Natalia',
        'familyName' => 'Podgórska',
        'phoneNumber' => '+48123456789',
        'addressLines' => ['Karmelicka 7', 'm. 3'],
        'locality' => 'Kraków',
        'postalCode' => '31-000',
        'countryCode' => 'pl',
    ];

    public function testShouldMapFullSheetContactToOrderData(): void
    {
        // Given
        $payload = new ApplePayPayload();
        $request = $this->request(['shippingContact' => self::FULL_CONTACT]);

        // When
        $contact = $payload->contact($request);

        // Then
        self::assertSame('kupujaca@oma.local', $contact->email);
        self::assertSame('Natalia', $contact->firstName);
        self::assertSame('Podgórska', $contact->lastName);
        self::assertSame('Karmelicka 7, m. 3', $contact->street);
        self::assertSame('Kraków', $contact->city);
        self::assertSame('31-000', $contact->postcode);
        self::assertSame('pl', $contact->countryCode);
        self::assertSame('+48123456789', $contact->phoneNumber);
        self::assertTrue($contact->isComplete());
    }

    public function testShouldFallBackToBillingContactForMissingFields(): void
    {
        // Given
        $payload = new ApplePayPayload();
        $request = $this->request(
            [
            'shippingContact' => ['countryCode' => 'PL', 'postalCode' => '31-000', 'locality' => 'Kraków'],
            'billingContact' => self::FULL_CONTACT,
            ]
        );

        // When
        $contact = $payload->contact($request);

        // Then
        self::assertSame('kupujaca@oma.local', $contact->email);
        self::assertSame('Karmelicka 7, m. 3', $contact->street);
    }

    public function testShouldAcceptRedactedContactWhileChoosingShipping(): void
    {
        // Given
        $payload = new ApplePayPayload();
        $request = $this->request(
            [
            'shippingContact' => ['countryCode' => 'PL', 'postalCode' => '31-000', 'locality' => 'Kraków'],
            ]
        );

        // When
        $contact = $payload->shippingContact($request);

        // Then
        self::assertSame('PL', $contact->countryCode);
        self::assertSame('31-000', $contact->postcode);
        self::assertNull($contact->street);
        self::assertFalse($contact->isComplete(), 'do wyboru wysyłki Apple ukrywa dane osobowe');
    }

    public function testShouldRejectContactWithoutCountryCode(): void
    {
        // Given
        $payload = new ApplePayPayload();
        $request = $this->request(['shippingContact' => ['postalCode' => '31-000']]);

        // When & Then
        $this->expectException(MalformedApplePayPayload::class);
        $payload->contact($request);
    }

    public function testShouldEncodePaymentTokenForGateway(): void
    {
        // Given
        $payload = new ApplePayPayload();
        $token = ['version' => 'EC_v1', 'data' => 'zaszyfrowane'];
        $request = $this->request(['paymentToken' => $token]);

        // When
        $encoded = $payload->paymentToken($request);

        // Then
        self::assertIsString($encoded);

        $decoded = base64_decode($encoded, true);
        self::assertIsString($decoded);
        self::assertSame($token, json_decode($decoded, true));
    }

    public function testShouldTreatMissingPaymentTokenAsAbsent(): void
    {
        // Given
        $payload = new ApplePayPayload();

        // When & Then
        self::assertNull($payload->paymentToken($this->request([])));
        self::assertNull($payload->paymentToken($this->request(['paymentToken' => []])));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create('/sklep/apple-pay/order', 'POST', content: json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
