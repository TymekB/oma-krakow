<?php

declare(strict_types=1);

namespace App\Payment\PayU\Domain\ValueObject;

enum PayUPayMethod: string
{
    case ApplePay = 'jp';
    case GooglePay = 'ap';
    case Blik = 'blik';
    case Card = 'c';

    public function label(): string
    {
        return match ($this) {
            self::ApplePay => 'oma.payu.form.pay_method_apple_pay',
            self::GooglePay => 'oma.payu.form.pay_method_google_pay',
            self::Blik => 'oma.payu.form.pay_method_blik',
            self::Card => 'oma.payu.form.pay_method_card',
        };
    }
}
