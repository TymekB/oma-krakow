<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Type;

use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ContentSnapshotType extends Type
{
    public const NAME = 'oma_site_content_snapshot';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ContentSnapshot) {
            throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', ContentSnapshot::class]);
        }

        try {
            return json_encode($value->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            throw ConversionException::conversionFailedSerialization($value, 'json', $exception->getMessage(), $exception);
        }
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ContentSnapshot
    {
        if (null === $value || $value instanceof ContentSnapshot) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', 'string']);
        }

        if ('' === $value) {
            return ContentSnapshot::empty();
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ConversionException::conversionFailed($value, self::NAME, $exception);
        }

        if (!is_array($decoded)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        $values = [];

        foreach ($decoded as $key => $item) {
            $values[(string) $key] = is_string($item) ? $item : (string) json_encode($item);
        }

        return ContentSnapshot::fromArray($values);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
