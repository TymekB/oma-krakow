<?php

declare(strict_types=1);

namespace App\Tests\SiteContent\Domain\Model;

use App\SiteContent\Domain\Event\SiteContentChanged;
use App\SiteContent\Domain\Event\SiteContentRemoved;
use App\SiteContent\Domain\Model\SiteContent;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentValue;
use PHPUnit\Framework\TestCase;

final class SiteContentTest extends TestCase
{
    private const NOW = '2026-08-11 12:00:00';

    private const LATER = '2026-08-11 13:00:00';

    public function testShouldRecordEventOnCreation(): void
    {
        // Given
        $now = new \DateTimeImmutable(self::NOW);

        // When
        $content = SiteContent::create(
            ContentKey::fromString('hero.lead'),
            ContentValue::fromString('Pierwsza treść'),
            $now,
        );

        // Then
        $events = $content->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SiteContentChanged::class, $events[0]);
        self::assertTrue($events[0]->isFirstValue());
        self::assertSame('Pierwsza treść', $events[0]->currentValue->value);
        self::assertEquals($now, $events[0]->occurredAt());
    }

    public function testShouldReleaseEventsOnlyOnce(): void
    {
        // Given
        $content = SiteContent::create(
            ContentKey::fromString('hero.lead'),
            ContentValue::fromString('Treść'),
            new \DateTimeImmutable(self::NOW),
        );

        // When
        $first = $content->releaseEvents();
        $second = $content->releaseEvents();

        // Then
        self::assertCount(1, $first);
        self::assertSame([], $second);
    }

    public function testShouldRecordEventWhenValueActuallyChanges(): void
    {
        // Given
        $content = $this->settledContent('Stara treść');

        // When
        $content->changeValue(ContentValue::fromString('Nowa treść'), new \DateTimeImmutable(self::LATER));

        // Then
        $events = $content->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SiteContentChanged::class, $events[0]);
        self::assertSame('Stara treść', $events[0]->previousValue?->value);
        self::assertSame('Nowa treść', $events[0]->currentValue->value);
        self::assertSame('Nowa treść', $content->value()->value);
    }

    public function testShouldStaySilentWhenValueIsIdentical(): void
    {
        // Given
        $content = $this->settledContent('Ta sama treść');

        // When
        $content->changeValue(ContentValue::fromString('Ta sama treść'), new \DateTimeImmutable(self::LATER));

        // Then
        self::assertSame([], $content->releaseEvents());
        self::assertEquals(
            new \DateTimeImmutable(self::NOW),
            $content->updatedAt(),
            'brak zmiany nie odświeża znacznika czasu',
        );
    }

    public function testShouldRecordRemovalEvent(): void
    {
        // Given
        $content = $this->settledContent('Treść');

        // When
        $content->markRemoved(new \DateTimeImmutable(self::LATER));

        // Then
        $events = $content->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SiteContentRemoved::class, $events[0]);
        self::assertSame('hero.lead', $events[0]->key->value);
    }

    /**
     * Treść po utworzeniu, z odebranymi zdarzeniami startowymi.
     */
    private function settledContent(string $value): SiteContent
    {
        $content = SiteContent::create(
            ContentKey::fromString('hero.lead'),
            ContentValue::fromString($value),
            new \DateTimeImmutable(self::NOW),
        );

        $content->releaseEvents();

        return $content;
    }
}
