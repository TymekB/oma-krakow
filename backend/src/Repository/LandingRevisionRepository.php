<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Landing\LandingRevision;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LandingRevision>
 */
final class LandingRevisionRepository extends ServiceEntityRepository
{
    private const HISTORY_LIMIT = 30;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LandingRevision::class);
    }

    /**
     * @return list<LandingRevision>
     */
    public function findLatest(): array
    {
        /**
         * @var list<LandingRevision> $revisions
         */
        $revisions = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(self::HISTORY_LIMIT)
            ->getQuery()
            ->getResult();

        return $revisions;
    }

    /**
     * @param array<string, string> $snapshot
     * @param list<string>          $changedKeys
     */
    public function record(array $snapshot, array $changedKeys, string $author, string $reason): LandingRevision
    {
        $revision = new LandingRevision($snapshot, $changedKeys, $author, $reason);

        $this->getEntityManager()->persist($revision);
        $this->getEntityManager()->flush();

        return $revision;
    }
}
