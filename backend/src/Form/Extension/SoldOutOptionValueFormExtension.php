<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Product\VariantAvailability;
use Sylius\Bundle\ProductBundle\Form\Type\ProductOptionValueChoiceType;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SoldOutOptionValueFormExtension extends AbstractTypeExtension
{
    private const SOLD_OUT_LABEL = 'oma.product.sold_out_option';

    public function __construct(
        private readonly VariantAvailability $availability,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductOptionValueChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_label' => fn (Options $options): callable => $this->labelFor($options['product']),
            'choice_attr' => fn (Options $options): callable => $this->attributesFor($options['product']),
        ]);
    }

    private function labelFor(mixed $product): callable
    {
        return function (ProductOptionValueInterface $optionValue) use ($product): string {
            $value = (string) $optionValue->getValue();

            if ($this->isSoldOut($product, $optionValue)) {
                return $this->translator->trans(self::SOLD_OUT_LABEL, ['%value%' => $value]);
            }

            return $value;
        };
    }

    private function attributesFor(mixed $product): callable
    {
        return fn (ProductOptionValueInterface $optionValue): array => $this->isSoldOut($product, $optionValue)
            ? ['disabled' => 'disabled']
            : [];
    }

    private function isSoldOut(mixed $product, ProductOptionValueInterface $optionValue): bool
    {
        return $product instanceof ProductInterface && !$this->availability->isOptionValueAvailable($product, $optionValue);
    }
}
