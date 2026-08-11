<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Type;

use App\SiteContent\Domain\ValueObject\ContentValue;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ContentValueType extends Type
{
    public const NAME = 'oma_site_content_value';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ContentValue) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', ContentValue::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ContentValue
    {
        if (null === $value || $value instanceof ContentValue) {
            return $value;
        }

        if (is_string($value)) {
            return ContentValue::fromString($value);
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', 'string']);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
