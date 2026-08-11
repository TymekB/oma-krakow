<?php

declare(strict_types=1);

namespace App\SiteContent\Infrastructure\Doctrine\Repository;

use App\SiteContent\Domain\Model\SiteContent;
use App\SiteContent\Domain\Repository\SiteContentRepository;
use App\SiteContent\Domain\ValueObject\ContentKey;
use App\SiteContent\Domain\ValueObject\ContentSnapshot;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSiteContentRepository implements SiteContentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findByKey(ContentKey $key): ?SiteContent
    {
        return $this->entityManager->getRepository(SiteContent::class)->findOneBy(['key' => $key]);
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(SiteContent::class)->findAll();
    }

    public function snapshot(): ContentSnapshot
    {
        $values = [];

        foreach ($this->findAll() as $content) {
            $values[$content->key()->value] = $content->value()->value;
        }

        return ContentSnapshot::fromArray($values);
    }

    public function add(SiteContent $content): void
    {
        $this->entityManager->persist($content);
    }

    public function remove(SiteContent $content): void
    {
        $this->entityManager->remove($content);
    }
}
