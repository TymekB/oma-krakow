<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Landing\LandingContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LandingContent>
 */
final class LandingContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LandingContent::class);
    }

    /**
     * @return array<string, string>
     */
    public function findAllAsMap(): array
    {
        $map = [];

        foreach ($this->findAll() as $content) {
            $map[$content->getKey()] = $content->getValue();
        }

        return $map;
    }

    /**
     * @param array<string, string> $snapshot
     *
     * @return list<string>
     */
    public function replaceAll(array $snapshot): array
    {
        $manager = $this->getEntityManager();
        $changed = [];

        foreach ($this->findAll() as $content) {
            if (!array_key_exists($content->getKey(), $snapshot)) {
                $changed[] = $content->getKey();
                $manager->remove($content);
            }
        }

        foreach ($snapshot as $key => $value) {
            $content = $this->findOneBy(['key' => $key]);

            if (null === $content) {
                $manager->persist(new LandingContent($key, $value));
                $changed[] = $key;

                continue;
            }

            if ($content->getValue() !== $value) {
                $content->changeValue($value);
                $changed[] = $key;
            }
        }

        $manager->flush();

        return $changed;
    }

    public function upsert(string $key, string $value): LandingContent
    {
        $content = $this->findOneBy(['key' => $key]);

        if (null === $content) {
            $content = new LandingContent($key, $value);
            $this->getEntityManager()->persist($content);
        } else {
            $content->changeValue($value);
        }

        $this->getEntityManager()->flush();

        return $content;
    }
}
