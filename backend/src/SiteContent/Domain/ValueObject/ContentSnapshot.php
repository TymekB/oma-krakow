<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\ValueObject;

final readonly class ContentSnapshot implements \Countable
{
    /**
     * @param array<string, string> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * @param array<string, string> $values
     */
    public static function fromArray(array $values): self
    {
        ksort($values);

        return new self($values);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function has(ContentKey $key): bool
    {
        return array_key_exists($key->value, $this->values);
    }

    public function get(ContentKey $key): ?ContentValue
    {
        $value = $this->values[$key->value] ?? null;

        return null === $value ? null : ContentValue::fromString($value);
    }

    /**
     * @return list<ContentKey>
     */
    public function keys(): array
    {
        return array_map(ContentKey::fromString(...), array_keys($this->values));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function equals(self $other): bool
    {
        return $this->values === $other->values;
    }

    public function count(): int
    {
        return count($this->values);
    }
}
