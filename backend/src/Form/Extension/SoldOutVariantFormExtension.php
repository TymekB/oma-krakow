<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Product\VariantAvailability;
use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantChoiceType;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SoldOutVariantFormExtension extends AbstractTypeExtension
{
    private const SOLD_OUT_LABEL = 'oma.product.sold_out_option';

    public function __construct(
        private readonly VariantAvailability $availability,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choice_label' => $this->label(...),
            'choice_attr' => $this->attributes(...),
        ]);
    }

    private function label(mixed $variant): string
    {
        if (!$variant instanceof ProductVariantInterface) {
            return '';
        }

        $name = $variant->getName() ?? $variant->getDescriptor();

        if ($this->availability->isAvailable($variant)) {
            return $name;
        }

        return $this->translator->trans(self::SOLD_OUT_LABEL, ['%value%' => $name]);
    }

    /**
     * @return array<string, string>
     */
    private function attributes(mixed $variant): array
    {
        if (!$variant instanceof ProductVariantInterface || $this->availability->isAvailable($variant)) {
            return [];
        }

        return ['disabled' => 'disabled'];
    }
}
