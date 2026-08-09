<?php

declare(strict_types=1);

namespace App\Review;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Review\Model\ReviewInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

#[AsEventListener(event: 'sylius.product_review.post_create')]
final readonly class AutoAcceptProductReviewListener
{
    private const GRAPH = 'sylius_product_review';
    private const TRANSITION = 'accept';

    public function __construct(
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $review = $event->getSubject();

        if (!$review instanceof ReviewInterface) {
            return;
        }

        if (!$this->stateMachine->can($review, self::GRAPH, self::TRANSITION)) {
            return;
        }

        $this->stateMachine->apply($review, self::GRAPH, self::TRANSITION);

        $this->entityManager->flush();
    }
}
