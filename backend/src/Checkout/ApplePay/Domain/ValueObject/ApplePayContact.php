<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Domain\ValueObject;

final readonly class ApplePayContact
{
    public function __construct(
        public string $countryCode,
        public string $postcode,
        public string $city,
        public ?string $email = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $street = null,
        public ?string $phoneNumber = null,
    ) {
    }

    public function isComplete(): bool
    {
        return null !== $this->email &&
            null !== $this->firstName &&
            null !== $this->lastName &&
            null !== $this->street;
    }
}
