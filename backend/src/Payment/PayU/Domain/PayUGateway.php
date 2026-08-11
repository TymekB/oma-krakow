<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain;

final class PayUGateway
{
    public const FACTORY_NAME = 'payu';

    public const DETAILS_KEY = 'payu';

    private function __construct()
    {
    }
}
