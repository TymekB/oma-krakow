<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\ShopBundle\Form\Type\Checkout\AddressType;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CheckoutAddressTypeExtension extends AbstractTypeExtension
{
    private const ADDRESS_FIELDS = ['billingAddress', 'shippingAddress'];

    private const CUSTOMER_FIELD = 'customer';

    private const NAME_MIN_LENGTH = 2;

    private const NAME_MAX_LENGTH = 255;

    /**
     * @param list<string> $customerValidationGroups
     */
    public function __construct(
        #[Autowire('%sylius.form.type.customer_guest.validation_groups%')]
        private readonly array $customerValidationGroups,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddressType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, $this->replaceAddressWithContactDetails(...));
    }

    public function replaceAddressWithContactDetails(FormEvent $event): void
    {
        $order = $event->getData();

        if (!$order instanceof OrderInterface || $order->isShippingRequired()) {
            return;
        }

        $form = $event->getForm();

        foreach (self::ADDRESS_FIELDS as $field) {
            if ($form->has($field)) {
                $form->remove($field);
            }
        }

        if ($form->has(self::CUSTOMER_FIELD)) {
            $this->addContactDetails($form->get(self::CUSTOMER_FIELD));
        }
    }

    private function addContactDetails(FormInterface $customerForm): void
    {
        $customerForm
            ->add('firstName', TextType::class, [
                'label' => 'sylius.form.customer.first_name',
                'constraints' => [
                    new NotBlank(
                        message: 'sylius.customer.first_name.not_blank',
                        groups: $this->customerValidationGroups,
                    ),
                    new Length(
                        min: self::NAME_MIN_LENGTH,
                        max: self::NAME_MAX_LENGTH,
                        minMessage: 'sylius.customer.first_name.min',
                        maxMessage: 'sylius.customer.first_name.max',
                        groups: $this->customerValidationGroups,
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'sylius.form.customer.last_name',
                'constraints' => [
                    new NotBlank(
                        message: 'sylius.customer.last_name.not_blank',
                        groups: $this->customerValidationGroups,
                    ),
                    new Length(
                        min: self::NAME_MIN_LENGTH,
                        max: self::NAME_MAX_LENGTH,
                        minMessage: 'sylius.customer.last_name.min',
                        maxMessage: 'sylius.customer.last_name.max',
                        groups: $this->customerValidationGroups,
                    ),
                ],
            ])
            ->add('phoneNumber', TextType::class, [
                'required' => false,
                'label' => 'sylius.form.customer.phone_number',
                'constraints' => [
                    new Length(
                        max: self::NAME_MAX_LENGTH,
                        maxMessage: 'sylius.customer.phone_number.max',
                        groups: $this->customerValidationGroups,
                    ),
                ],
            ])
        ;
    }
}
