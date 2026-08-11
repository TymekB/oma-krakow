<?php

declare(strict_types=1);

namespace App\SiteContent\Domain\Exception;

use App\SiteContent\Domain\ValueObject\RevisionId;

final class RevisionNotFound extends SiteContentException
{
    public static function withId(RevisionId $id): self
    {
        return new self(sprintf('Nie znaleziono wersji o identyfikatorze %d.', $id->value));
    }
}
