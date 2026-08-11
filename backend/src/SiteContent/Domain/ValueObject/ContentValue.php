<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\Exception\InvalidContentValue;

final readonly class ContentValue implements \Stringable
{
    public const MAX_LENGTH = 5000;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $length = mb_strlen($value);

        if ($length > self::MAX_LENGTH) {
            throw InvalidContentValue::tooLong($length, self::MAX_LENGTH);
        }

        return new self($value);
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
