<?php

declare(strict_types=1);

namespace App\Entity\Landing;

use App\Repository\LandingRevisionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LandingRevisionRepository::class)]
#[ORM\Table(name: 'oma_landing_revision')]
class LandingRevision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType

    /** @var array<string, string> */
    #[ORM\Column(type: 'json')]
    private array $snapshot;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $changedKeys;

    #[ORM\Column(type: 'string', length: 128)]
    private string $author;

    #[ORM\Column(type: 'string', length: 32)]
    private string $reason;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $snapshot
     * @param list<string>          $changedKeys
     */
    public function __construct(array $snapshot, array $changedKeys, string $author, string $reason)
    {
        $this->snapshot = $snapshot;
        $this->changedKeys = $changedKeys;
        $this->author = $author;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array<string, string>
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    /**
     * @return list<string>
     */
    public function getChangedKeys(): array
    {
        return $this->changedKeys;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
