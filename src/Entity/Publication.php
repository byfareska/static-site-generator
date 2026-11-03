<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Byfareska\StaticSite\Publication\PublicationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One "publish": a build of the site sent to one or more targets, or an older build sent again (a rollback).
 */
#[ORM\Entity]
#[ORM\Table(name: 'static_site_publication')]
#[ORM\Index(fields: ['status'])]
class Publication implements \Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 12, enumType: PublicationStatus::class)]
    public PublicationStatus $status = PublicationStatus::Pending;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    public array $targets;

    #[ORM\Column(length: 180, nullable: true)]
    public ?string $requestedBy;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $finishedAt = null;

    /** The build sent: made by this publication, or the one of $rollbackOf. */
    #[ORM\Column(length: 40, nullable: true)]
    public ?string $buildId = null;

    /** For a rollback: the publication whose build is sent again. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Publication $rollbackOf;

    #[ORM\Column]
    public int $fileCount = 0;

    #[ORM\Column]
    public int $uploaded = 0;

    #[ORM\Column]
    public int $deleted = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $log = null;

    /** @param list<string> $targets */
    public function __construct(array $targets, ?string $requestedBy = null, ?self $rollbackOf = null)
    {
        $this->targets = array_values($targets);
        $this->requestedBy = $requestedBy;
        $this->rollbackOf = $rollbackOf;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function isRollback(): bool
    {
        return $this->rollbackOf !== null;
    }

    /**
     * When the content of its build was read: when the building began (a rollback: the one of the build it sends
     * again). A change after it is not on a target holding this build.
     */
    public function contentAt(): ?\DateTimeImmutable
    {
        return $this->rollbackOf !== null ? $this->rollbackOf->contentAt() : $this->startedAt;
    }

    public function addLog(string $line): void
    {
        $this->log = ($this->log === null ? '' : $this->log . "\n") . $line;
    }

    public function __toString(): string
    {
        return sprintf('#%d %s', (int) $this->id, $this->createdAt->format('Y-m-d H:i'));
    }
}
