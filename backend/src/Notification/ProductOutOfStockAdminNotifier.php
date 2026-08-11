<?php

declare(strict_types=1);

namespace App\Notification;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ProductOutOfStockAdminNotifier
{
    public const EMAIL_CODE = 'admin_product_out_of_stock';

    private const TRACKED = 'tracked';

    private const ON_HAND = 'onHand';

    private const ON_HOLD = 'onHold';

    /** @var list<ProductVariantInterface> */
    private array $soldOut = [];

    public function __construct(
        private readonly SenderInterface $emailSender,
        private readonly AdminRecipient $adminRecipient,
        private readonly LocaleContextInterface $localeContext,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $unitOfWork = $event->getObjectManager()->getUnitOfWork();
        $this->soldOut = [];

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof ProductVariantInterface) {
                continue;
            }

            $this->rememberIfSoldOut($entity, $unitOfWork->getEntityChangeSet($entity));
        }
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    public function rememberIfSoldOut(ProductVariantInterface $variant, array $changeSet): void
    {
        if ($this->availabilityChecker->isStockAvailable($variant)) {
            return;
        }

        if (!$this->wasAvailable($variant, $changeSet)) {
            return;
        }

        $this->soldOut[] = $variant;
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $variants = $this->soldOut;
        $this->soldOut = [];

        if ([] === $variants) {
            return;
        }

        $currentChannel = $this->adminRecipient->currentChannel();

        foreach ($variants as $variant) {
            $channel = $currentChannel ?? $this->channelSelling($variant);
            $recipient = $this->adminRecipient->resolve($channel);

            if (null === $recipient) {
                continue;
            }

            $this->emailSender->send(
                self::EMAIL_CODE,
                [$recipient],
                [
                    'variant' => $variant,
                    'product' => $variant->getProduct(),
                    'channel' => $channel,
                    'localeCode' => $this->localeContext->getLocaleCode(),
                ],
            );
        }
    }

    private function channelSelling(ProductVariantInterface $variant): ?ChannelInterface
    {
        $product = $variant->getProduct();

        if (!$product instanceof ProductInterface) {
            return null;
        }

        $channel = $product->getChannels()->first();

        return $channel instanceof ChannelInterface ? $channel : null;
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    private function wasAvailable(ProductVariantInterface $variant, array $changeSet): bool
    {
        if (!$this->trackedBeforeFlush($variant, $changeSet)) {
            return true;
        }

        return $this->quantityBeforeFlush($changeSet, self::ON_HAND, $variant->getOnHand())
            > $this->quantityBeforeFlush($changeSet, self::ON_HOLD, $variant->getOnHold());
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    private function trackedBeforeFlush(ProductVariantInterface $variant, array $changeSet): bool
    {
        if (!array_key_exists(self::TRACKED, $changeSet)) {
            return $variant->isTracked();
        }

        return true === $this->previousValue($changeSet, self::TRACKED);
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    private function quantityBeforeFlush(array $changeSet, string $field, ?int $currentQuantity): int
    {
        if (!array_key_exists($field, $changeSet)) {
            return $currentQuantity ?? 0;
        }

        $previousQuantity = $this->previousValue($changeSet, $field);

        return is_int($previousQuantity) ? $previousQuantity : 0;
    }

    /**
     * @param array<string, mixed> $changeSet
     */
    private function previousValue(array $changeSet, string $field): mixed
    {
        $change = $changeSet[$field] ?? null;

        return is_array($change) ? ($change[0] ?? null) : null;
    }
}
