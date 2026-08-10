<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Sklep ma jeden kanał i tak zostanie, więc pole wyboru kanału tylko myli.
 * Ukrywamy je i przypisujemy jedyny kanał automatycznie.
 */
final class SingleChannelFormExtension extends AbstractTypeExtension
{
    private const CHANNELS_FIELD = 'channels';

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, $this->hideChannelsField(...));
    }

    public function hideChannelsField(FormEvent $event): void
    {
        $form = $event->getForm();

        if (!$form->has(self::CHANNELS_FIELD)) {
            return;
        }

        $channel = $this->onlyChannel();

        if (null === $channel) {
            return;
        }

        $form->remove(self::CHANNELS_FIELD);

        $data = $event->getData();

        if ($data instanceof ChannelsAwareInterface && !$data->hasChannel($channel)) {
            $data->addChannel($channel);
        }
    }

    private function onlyChannel(): ?ChannelInterface
    {
        $channels = $this->channelRepository->findAll();

        return 1 === count($channels) ? $channels[0] : null;
    }
}
