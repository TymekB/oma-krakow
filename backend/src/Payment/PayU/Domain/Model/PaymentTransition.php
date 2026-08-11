<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\Model;

/**
 * Przejścia płatności wyrażone w języku domeny. Mapowanie na maszynę stanów
 * Syliusa żyje w Infrastructure, dzięki czemu domena nie zna frameworka.
 */
enum PaymentTransition: string
{
    case Process = 'process';
    case Authorize = 'authorize';
    case Complete = 'complete';
    case Cancel = 'cancel';
    case Fail = 'fail';
}
