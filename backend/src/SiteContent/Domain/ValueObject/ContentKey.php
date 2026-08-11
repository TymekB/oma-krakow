<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\Exception\InvalidContentKey;

final readonly class ContentKey implements \Stringable
{
    public const PATTERN = '/^[a-z0-9._-]{1,128}$/';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidContentKey::forValue($value);
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
