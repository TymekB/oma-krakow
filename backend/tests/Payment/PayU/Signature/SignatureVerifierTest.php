<?php

declare(strict_types=1);

namespace App\Tests\Payment\PayU\Signature;

use App\Payment\PayU\Signature\SignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureVerifierTest extends TestCase
{
    private const SIGNATURE_KEY = 'b6ca15b0d1020e8094d9b5f8d163db54';

    private const BODY = '{"order":{"orderId":"WZHF5FFDRJ140731GUEST000P01","status":"COMPLETED"}}';

    public function testShouldAcceptSignatureMatchingTheBodyAndSecondKey(): void
    {
        $verifier = new SignatureVerifier();
        $signature = md5(self::BODY . self::SIGNATURE_KEY);
        $header = sprintf('sender=checkout;signature=%s;algorithm=MD5;content=DOCUMENT', $signature);

        self::assertTrue($verifier->verify($header, self::BODY, self::SIGNATURE_KEY));
    }

    public function testShouldAcceptSha256Signature(): void
    {
        $verifier = new SignatureVerifier();
        $signature = hash('sha256', self::BODY . self::SIGNATURE_KEY);
        $header = sprintf('signature=%s;algorithm=SHA-256', $signature);

        self::assertTrue($verifier->verify($header, self::BODY, self::SIGNATURE_KEY));
    }

    #[DataProvider('invalidHeaders')]
    public function testShouldRejectInvalidSignature(?string $header): void
    {
        $verifier = new SignatureVerifier();

        self::assertFalse($verifier->verify($header, self::BODY, self::SIGNATURE_KEY));
    }

    /**
     * @return iterable<string, array{string|null}> 
     */
    public static function invalidHeaders(): iterable
    {
        yield 'no header' => [null];
        yield 'empty header' => [''];
        yield 'tampered signature' => ['sender=checkout;signature=deadbeef;algorithm=MD5;content=DOCUMENT'];
        yield 'missing signature' => ['sender=checkout;algorithm=MD5;content=DOCUMENT'];
        yield 'unsupported algorithm' => [
            'sender=checkout;signature=' . md5(self::BODY . self::SIGNATURE_KEY) . ';algorithm=CRC32',
        ];
        yield 'signature of another key' => [
            'sender=checkout;signature=' . md5(self::BODY . 'other-key') . ';algorithm=MD5',
        ];
    }
}
