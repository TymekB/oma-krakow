<?php

declare(strict_types=1);

namespace App\Tests\SiteContent\Domain\Service;

use App\SiteContent\Domain\Event\SiteContentChanged;
use App\SiteContent\Domain\Event\SiteContentRemoved;
use App\SiteContent\Domain\Service\SiteContentEditor;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use App\SiteContent\Domain\ValueObject\ContentValue;
use App\Tests\SiteContent\Domain\InMemorySiteContentRepository;
use PHPUnit\Framework\TestCase;

final class SiteContentEditorTest extends TestCase
{
    private const NOW = '2026-08-11 12:00:00';

    public function testShouldCreateMissingContent(): void
    {
        // Given
        $repository = new InMemorySiteContentRepository();
        $editor = new SiteContentEditor($repository);

        // When
        $editor->change(
            ContentKey::fromString('hero.lead'),
            ContentValue::fromString('Nowa treść'),
            $this->now(),
        );

        // Then
        self::assertSame(['hero.lead' => 'Nowa treść'], $repository->snapshot()->toArray());
    }

    public function testShouldRestoreRemovingKeysAbsentFromSnapshot(): void
    {
        // Given
        $repository = InMemorySiteContentRepository::withValues(
            ['hero.lead' => 'A', 'hero.eyebrow' => 'B'],
            $this->now(),
        );
        $editor = new SiteContentEditor($repository);

        // When
        $result = $editor->restoreTo(ContentSnapshot::fromArray(['hero.lead' => 'A']), $this->now());

        // Then
        self::assertSame(['hero.eyebrow'], $result->changedKeysAsStrings());
        self::assertSame(['hero.lead' => 'A'], $repository->snapshot()->toArray());
        self::assertCount(1, $result->events);
        self::assertInstanceOf(SiteContentRemoved::class, $result->events[0]);
    }

    public function testShouldRestoreOnlyKeysThatDiffer(): void
    {
        // Given
        $repository = InMemorySiteContentRepository::withValues(
            ['hero.lead' => 'A', 'hero.eyebrow' => 'B'],
            $this->now(),
        );
        $editor = new SiteContentEditor($repository);

        // When
        $result = $editor->restoreTo(
            ContentSnapshot::fromArray(['hero.lead' => 'A', 'hero.eyebrow' => 'ZMIENIONE']),
            $this->now(),
        );

        // Then
        self::assertSame(['hero.eyebrow'], $result->changedKeysAsStrings());
        self::assertCount(1, $result->events);
        self::assertInstanceOf(SiteContentChanged::class, $result->events[0]);
    }

    public function testShouldReportNothingWhenSnapshotMatchesCurrentState(): void
    {
        // Given
        $values = ['hero.lead' => 'A', 'hero.eyebrow' => 'B'];
        $repository = InMemorySiteContentRepository::withValues($values, $this->now());
        $editor = new SiteContentEditor($repository);

        // When
        $result = $editor->restoreTo(ContentSnapshot::fromArray($values), $this->now());

        // Then
        self::assertTrue($result->isEmpty());
        self::assertSame([], $result->events);
    }

    public function testShouldRecreateKeyMissingInCurrentState(): void
    {
        // Given
        $repository = InMemorySiteContentRepository::withValues(['hero.lead' => 'A'], $this->now());
        $editor = new SiteContentEditor($repository);

        // When
        $result = $editor->restoreTo(
            ContentSnapshot::fromArray(['hero.lead' => 'A', 'hero.eyebrow' => 'B']),
            $this->now(),
        );

        // Then
        self::assertSame(['hero.eyebrow'], $result->changedKeysAsStrings());
        self::assertSame(['hero.eyebrow' => 'B', 'hero.lead' => 'A'], $repository->snapshot()->toArray());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }
}
