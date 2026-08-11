<?php

declare(strict_types=1);

namespace App\Factory;

use Sylius\Component\Core\Factory\CustomerAfterCheckoutFactoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * @implements CustomerAfterCheckoutFactoryInterface<CustomerInterface>
 */
#[AsDecorator(decorates: 'sylius.factory.customer_after_checkout')]
final readonly class CustomerAfterCheckoutFactory implements CustomerAfterCheckoutFactoryInterface
{
    /**
     * @param CustomerAfterCheckoutFactoryInterface<CustomerInterface> $decorated
     */
    public function __construct(private CustomerAfterCheckoutFactoryInterface $decorated)
    {
    }

    public function createNew(): CustomerInterface
    {
        return $this->decorated->createNew();
    }

    public function createAfterCheckout(OrderInterface $order): CustomerInterface
    {
        $guest = $order->getCustomer();
        $address = $order->getBillingAddress();

        $customer = $this->createNew();
        $customer->setEmail($guest?->getEmail());
        $customer->setFirstName($address?->getFirstName() ?? $guest?->getFirstName());
        $customer->setLastName($address?->getLastName() ?? $guest?->getLastName());
        $customer->setPhoneNumber($address?->getPhoneNumber() ?? $guest?->getPhoneNumber());

        return $customer;
    }
}
