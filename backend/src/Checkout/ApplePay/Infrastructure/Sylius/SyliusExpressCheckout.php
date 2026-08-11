<?php

declare(strict_types=1);

namespace App\Checkout\ApplePay\Infrastructure\Sylius;

use App\Checkout\ApplePay\Domain\Exception\ExpressCheckoutFailed;
use App\Checkout\ApplePay\Domain\Port\ExpressCheckout;
use App\Checkout\ApplePay\Domain\ValueObject\ApplePayContact;
use App\Checkout\ApplePay\Domain\ValueObject\ExpressOrder;
use App\Checkout\ApplePay\Domain\ValueObject\ShippingOption;
use App\Checkout\ApplePay\Infrastructure\Config\ApplePayConfiguration;
use App\Payment\PayU\Infrastructure\Sylius\PaymentDetails;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SyliusExpressCheckout implements ExpressCheckout
{
    /**
     * @param RepositoryInterface<CustomerInterface>      $customerRepository
     * @param RepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private CartContextInterface $cartContext,
        private OrderProcessorInterface $orderProcessor,
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
        private DelegatingCalculatorInterface $shippingCalculator,
        private StateMachineInterface $stateMachine,
        private PaymentDetails $paymentDetails,
        private ApplePayConfiguration $configuration,
        #[Autowire(service: 'sylius.factory.address')]
        private FactoryInterface $addressFactory,
        #[Autowire(service: 'sylius.factory.customer')]
        private FactoryInterface $customerFactory,
        #[Autowire(service: 'sylius.repository.customer')]
        private RepositoryInterface $customerRepository,
        #[Autowire(service: 'sylius.repository.payment_method')]
        private RepositoryInterface $paymentMethodRepository,
        #[Autowire(service: 'sylius.manager.order')]
        private EntityManagerInterface $orderManager,
    ) {
    }

    public function shippingOptionsFor(ApplePayContact $contact): array
    {
        $order = $this->cart();

        if (!$order->isShippingRequired()) {
            return [];
        }

        $order->setShippingAddress($this->address($contact));
        $this->orderProcessor->process($order);

        $shipment = $order->getShipments()->first();

        if (!$shipment instanceof ShipmentInterface) {
            return [];
        }

        $options = [];
        $selectedMethod = $shipment->getMethod();

        foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $method) {
            if (!$method instanceof ShippingMethodInterface) {
                continue;
            }

            $shipment->setMethod($method);

            $options[] = new ShippingOption(
                (string) $method->getCode(),
                (string) $method->getName(),
                $this->shippingCalculator->calculate($shipment),
                $method->getDescription(),
            );
        }

        $shipment->setMethod($selectedMethod);

        return $options;
    }

    public function place(ApplePayContact $contact, ?string $shippingMethodCode, ?string $paymentToken): ExpressOrder
    {
        if (!$contact->isComplete()) {
            throw ExpressCheckoutFailed::incompleteContact();
        }

        $order = $this->cart();

        $this->assignCustomer($order, $contact);
        $order->setBillingAddress($this->address($contact));
        $order->setShippingAddress($this->address($contact));

        $this->apply($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);

        $this->selectShipping($order, $shippingMethodCode);
        $this->selectPayment($order, $paymentToken);

        $this->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderManager->flush();

        return new ExpressOrder((string) $order->getTokenValue(), $order->getTotal());
    }

    private function cart(): OrderInterface
    {
        try {
            $order = $this->cartContext->getCart();
        } catch (CartNotFoundException) {
            throw ExpressCheckoutFailed::emptyCart();
        }

        if (!$order instanceof OrderInterface || $order->isEmpty()) {
            throw ExpressCheckoutFailed::emptyCart();
        }

        return $order;
    }

    private function selectShipping(OrderInterface $order, ?string $shippingMethodCode): void
    {
        if (!$order->isShippingRequired()) {
            $this->apply($order, OrderCheckoutTransitions::TRANSITION_SKIP_SHIPPING);

            return;
        }

        foreach ($order->getShipments() as $shipment) {
            $shipment->setMethod($this->shippingMethod($shipment, $shippingMethodCode));
        }

        $this->orderProcessor->process($order);
        $this->apply($order, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
    }

    private function shippingMethod(ShipmentInterface $shipment, ?string $shippingMethodCode): ShippingMethodInterface
    {
        $supported = [];

        foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $method) {
            if (!$method instanceof ShippingMethodInterface) {
                continue;
            }

            if ($method->getCode() === $shippingMethodCode) {
                return $method;
            }

            $supported[] = $method;
        }

        if ([] === $supported) {
            throw ExpressCheckoutFailed::noShippingMethod();
        }

        return $supported[0];
    }

    private function selectPayment(OrderInterface $order, ?string $paymentToken): void
    {
        $code = $this->configuration->settings()->paymentMethodCode;
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);

        if (!$paymentMethod instanceof PaymentMethodInterface || !$paymentMethod->isEnabled()) {
            throw ExpressCheckoutFailed::unknownPaymentMethod($code);
        }

        $payment = $order->getLastPayment(BasePaymentInterface::STATE_CART);

        if (!$payment instanceof PaymentInterface) {
            throw ExpressCheckoutFailed::checkoutRejected(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        $payment->setMethod($paymentMethod);

        if (null !== $paymentToken && '' !== $paymentToken) {
            $this->paymentDetails->update($payment, ['authorizationCode' => $paymentToken]);
        }

        $this->apply($order, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
    }

    private function assignCustomer(OrderInterface $order, ApplePayContact $contact): void
    {
        if (null !== $order->getCustomer()) {
            return;
        }

        $email = (string) $contact->email;
        $customer = $this->customerRepository->findOneBy(['email' => $email]);

        if (!$customer instanceof CustomerInterface) {
            $customer = $this->customerFactory->createNew();

            if (!$customer instanceof CustomerInterface) {
                throw ExpressCheckoutFailed::incompleteContact();
            }

            $customer->setEmail($email);
            $customer->setFirstName($contact->firstName);
            $customer->setLastName($contact->lastName);

            $this->customerRepository->add($customer);
        }

        $order->setCustomer($customer);
    }

    private function address(ApplePayContact $contact): AddressInterface
    {
        $address = $this->addressFactory->createNew();

        if (!$address instanceof AddressInterface) {
            throw ExpressCheckoutFailed::incompleteContact();
        }

        $address->setFirstName($contact->firstName);
        $address->setLastName($contact->lastName);
        $address->setStreet($contact->street);
        $address->setCity($contact->city);
        $address->setPostcode($contact->postcode);
        $address->setCountryCode(strtoupper($contact->countryCode));
        $address->setPhoneNumber($contact->phoneNumber);

        return $address;
    }

    private function apply(OrderInterface $order, string $transition): void
    {
        if (!$this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, $transition)) {
            throw ExpressCheckoutFailed::checkoutRejected($transition);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
    }
}
