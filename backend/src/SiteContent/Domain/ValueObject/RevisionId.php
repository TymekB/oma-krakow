<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

use App\SiteContent\Domain\Exception\InvalidRevisionId;

final readonly class RevisionId
{
    private function __construct(public int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw InvalidRevisionId::notPositive($value);
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
