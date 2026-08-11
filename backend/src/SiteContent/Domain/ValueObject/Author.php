<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\Exception\InvalidAuthor;

final readonly class Author implements \Stringable
{
    public const MAX_LENGTH = 128;

    private const SYSTEM = 'system';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ('' === $trimmed) {
            throw InvalidAuthor::blank();
        }

        $length = mb_strlen($trimmed);

        if ($length > self::MAX_LENGTH) {
            throw InvalidAuthor::tooLong($length, self::MAX_LENGTH);
        }

        return new self($trimmed);
    }

    public static function system(): self
    {
        return new self(self::SYSTEM);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
