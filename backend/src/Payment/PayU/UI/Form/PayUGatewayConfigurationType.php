<?php

declare(strict_types=1);

namespace App\Payment\PayU\UI\Form;

use App\Payment\PayU\Domain\ValueObject\PayUEnvironment;
use App\Payment\PayU\Domain\ValueObject\PayUPayMethod;
use App\Payment\PayU\Domain\PayUGateway;
use Sylius\Bundle\PaymentBundle\Attribute\AsGatewayConfigurationType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AsGatewayConfigurationType(type: PayUGateway::FACTORY_NAME, label: 'oma.payu.gateway')]
final class PayUGatewayConfigurationType extends AbstractType
{
    private const VALIDATION_GROUPS = ['sylius', PayUGateway::FACTORY_NAME];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'environment',
                ChoiceType::class,
                [
                'label' => 'oma.payu.form.environment',
                'choices' => [
                    'oma.payu.form.environment_sandbox' => PayUEnvironment::Sandbox->value,
                    'oma.payu.form.environment_production' => PayUEnvironment::Production->value,
                ],
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
                ],
            )
            ->add(
                'pos_id',
                TextType::class,
                [
                'label' => 'oma.payu.form.pos_id',
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
                ],
            )
            ->add(
                'client_id',
                TextType::class,
                [
                'label' => 'oma.payu.form.client_id',
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
                ],
            )
            ->add(
                'client_secret',
                TextType::class,
                [
                'label' => 'oma.payu.form.client_secret',
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
                ],
            )
            ->add(
                'signature_key',
                TextType::class,
                [
                'label' => 'oma.payu.form.signature_key',
                'constraints' => [
                    new NotBlank(['groups' => self::VALIDATION_GROUPS]),
                ],
                ],
            )
            ->add(
                'pay_method',
                ChoiceType::class,
                [
                'label' => 'oma.payu.form.pay_method',
                'required' => false,
                'placeholder' => 'oma.payu.form.pay_method_any',
                'choices' => $this->payMethodChoices(),
                ],
            );
    }

    /**
     * @return array<string, string>
     */
    private function payMethodChoices(): array
    {
        $choices = [];

        foreach (PayUPayMethod::cases() as $payMethod) {
            $choices[$payMethod->label()] = $payMethod->value;
        }

        return $choices;
    }
}
