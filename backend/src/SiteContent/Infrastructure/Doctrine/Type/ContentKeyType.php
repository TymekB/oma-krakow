<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Type;

use App\SiteContent\Domain\ValueObject\ContentKey;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ContentKeyType extends Type
{
    public const NAME = 'oma_site_content_key';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ContentKey) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', ContentKey::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ContentKey
    {
        if (null === $value || $value instanceof ContentKey) {
            return $value;
        }

        if (is_string($value)) {
            return ContentKey::fromString($value);
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', 'string']);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
