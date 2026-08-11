<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\Port;

use App\Checkout\ApplePay\Domain\Exception\ApplePayException;
use App\Checkout\ApplePay\Domain\ValueObject\ApplePayContact;
use App\Checkout\ApplePay\Domain\ValueObject\ExpressOrder;
use App\Checkout\ApplePay\Domain\ValueObject\ShippingOption;

interface ExpressCheckout
{
    /**
     * @return list<ShippingOption>
     */
    public function shippingOptionsFor(ApplePayContact $contact): array;

    /**
     * @throws ApplePayException
     */
    public function place(ApplePayContact $contact, ?string $shippingMethodCode, ?string $paymentToken): ExpressOrder;
}
