<?php

declare(strict_types=1);

namespace App\Tests\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\Exception\InvalidContentKey;
use App\SiteContent\Domain\ValueObject\ContentKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentKeyTest extends TestCase
{
    #[DataProvider('validKeys')]
    public function testShouldAcceptValidKey(string $value): void
    {
        // When
        $key = ContentKey::fromString($value);

        // Then
        self::assertSame($value, $key->value);
    }

    #[DataProvider('invalidKeys')]
    public function testShouldRejectInvalidKey(string $value): void
    {
        // When & Then
        $this->expectException(InvalidContentKey::class);

        ContentKey::fromString($value);
    }

    public function testShouldCompareByValue(): void
    {
        // Given
        $key = ContentKey::fromString('hero.eyebrow');

        // When & Then
        self::assertTrue($key->equals(ContentKey::fromString('hero.eyebrow')));
        self::assertFalse($key->equals(ContentKey::fromString('hero.lead')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validKeys(): iterable
    {
        yield 'z kropką' => ['hero.eyebrow'];
        yield 'z podkreśleniem' => ['nav_link'];
        yield 'z myślnikiem' => ['nav-link'];
        yield 'z cyfrą' => ['nav.link.0'];
        yield 'maksymalna długość' => [str_repeat('a', 128)];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'pusty' => [''];
        yield 'wielkie litery' => ['Hero.Eyebrow'];
        yield 'spacja' => ['hero eyebrow'];
        yield 'ukośnik' => ['hero/eyebrow'];
        yield 'przekroczona długość' => [str_repeat('a', 129)];
    }
}
