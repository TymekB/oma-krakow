<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

/**
 * Zdarzenie opisujące skutek przypadku użycia, a nie zmianę w agregacie.
 */
interface ApplicationEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
