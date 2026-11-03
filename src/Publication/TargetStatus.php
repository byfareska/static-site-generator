<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Entity\TargetState;
use Byfareska\StaticSite\Target\Target;

/**
 * What the panel tells of a target: what it holds and whether the content changed since.
 */
final readonly class TargetStatus
{
    public function __construct(
        public Target $target,
        public ?TargetState $state,
        public bool $changed,
        public ?\DateTimeImmutable $scheduledChange,
    ) {
    }

    public function deployedAt(): ?\DateTimeImmutable
    {
        return $this->state?->deployedAt;
    }

    public function isIncomplete(): bool
    {
        return $this->state !== null && !$this->state->complete;
    }

    public function needsPublication(): bool
    {
        return $this->changed || $this->isIncomplete()
            || ($this->scheduledChange !== null && $this->scheduledChange <= new \DateTimeImmutable());
    }
}
