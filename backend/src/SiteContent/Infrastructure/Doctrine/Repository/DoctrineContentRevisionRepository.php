<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Repository;

use App\SiteContent\Domain\Model\ContentRevision;
use App\SiteContent\Domain\Repository\ContentRevisionRepository;
use App\SiteContent\Domain\ValueObject\RevisionId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineContentRevisionRepository implements ContentRevisionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findById(RevisionId $id): ?ContentRevision
    {
        return $this->entityManager->find(ContentRevision::class, $id->value);
    }

    public function findLatest(int $limit): array
    {
        /**
 * @var list<ContentRevision> $revisions 
*/
        $revisions = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(ContentRevision::class, 'r')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $revisions;
    }

    public function add(ContentRevision $revision): void
    {
        $this->entityManager->persist($revision);
    }
}
