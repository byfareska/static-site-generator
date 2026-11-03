<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a target holds: the files the generator sent there (path => sha256) and which build was sent last in whole.
 */
#[ORM\Entity]
#[ORM\Table(name: 'static_site_target_state')]
class TargetState
{
    #[ORM\Id, ORM\Column(length: 64)]
    public string $target;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    public array $files = [];

    /** The build last sent in whole; null until then. */
    #[ORM\Column(length: 40, nullable: true)]
    public ?string $buildId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Publication $publication = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $deployedAt = null;

    /** False while a sync has sent only a part of a build (it was cut short). */
    #[ORM\Column]
    public bool $complete = true;

    public function __construct(string $target)
    {
        $this->target = $target;
    }
}
