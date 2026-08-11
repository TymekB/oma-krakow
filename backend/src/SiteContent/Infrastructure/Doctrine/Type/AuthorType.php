<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Type;

use App\SiteContent\Domain\ValueObject\Author;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class AuthorType extends Type
{
    public const NAME = 'oma_site_content_author';

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

        if ($value instanceof Author) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', Author::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Author
    {
        if (null === $value || $value instanceof Author) {
            return $value;
        }

        if (is_string($value)) {
            return Author::fromString($value);
        }

        throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['null', 'string']);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
