<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\CustomerBundle\Form\Type\CustomerProfileType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Gabinet nie prowadzi niczego w podziale na płeć, a pole jest wymagane
 * i domyślnie ustawione na "Nieznany" - pyta o dane, których nie używamy.
 */
final class CustomerGenderFormExtension extends AbstractTypeExtension
{
    private const GENDER_FIELD = 'gender';

    public static function getExtendedTypes(): iterable
    {
        return [CustomerProfileType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->remove(self::GENDER_FIELD);
    }
}
