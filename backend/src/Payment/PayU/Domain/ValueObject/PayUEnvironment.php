<?php

declare(strict_types=1);

namespace App\Payment\PayU\Api;

enum PayUEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function baseUri(): string
    {
        return match ($this) {
            self::Sandbox => 'https://secure.snd.payu.com',
            self::Production => 'https://secure.payu.com',
        };
    }
}
