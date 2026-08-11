<?php

declare(strict_types=1);

namespace App\Tests\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use PHPUnit\Framework\TestCase;

final class ContentSnapshotTest extends TestCase
{
    public function testShouldBeEqualRegardlessOfKeyOrder(): void
    {
        // Given
        $first = ContentSnapshot::fromArray(['b' => '2', 'a' => '1']);
        $second = ContentSnapshot::fromArray(['a' => '1', 'b' => '2']);

        // When & Then
        self::assertTrue($first->equals($second));
    }

    public function testShouldDetectDifferentValues(): void
    {
        // Given
        $first = ContentSnapshot::fromArray(['a' => '1']);
        $second = ContentSnapshot::fromArray(['a' => '2']);

        // When & Then
        self::assertFalse($first->equals($second));
    }

    public function testShouldDetectMissingKey(): void
    {
        // Given
        $first = ContentSnapshot::fromArray(['a' => '1', 'b' => '2']);
        $second = ContentSnapshot::fromArray(['a' => '1']);

        // When & Then
        self::assertFalse($first->equals($second));
    }

    public function testShouldExposeKeysAsValueObjects(): void
    {
        // Given
        $snapshot = ContentSnapshot::fromArray(['hero.lead' => 'x', 'hero.eyebrow' => 'y']);

        // When
        $keys = $snapshot->keys();

        // Then
        self::assertCount(2, $keys);
        self::assertContainsOnlyInstancesOf(ContentKey::class, $keys);
        self::assertSame('hero.eyebrow', $keys[0]->value);
    }

    public function testShouldAnswerWhetherKeyIsPresent(): void
    {
        // Given
        $snapshot = ContentSnapshot::fromArray(['hero.lead' => 'x']);

        // When & Then
        self::assertTrue($snapshot->has(ContentKey::fromString('hero.lead')));
        self::assertFalse($snapshot->has(ContentKey::fromString('hero.eyebrow')));
        self::assertCount(1, $snapshot);
    }

    public function testEmptySnapshotHasNoKeys(): void
    {
        // When
        $snapshot = ContentSnapshot::empty();

        // Then
        self::assertCount(0, $snapshot);
        self::assertSame([], $snapshot->toArray());
    }
}
