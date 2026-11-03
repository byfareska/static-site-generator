<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Build\SiteSources;
use Byfareska\StaticSite\Content\ContentChanges;
use Byfareska\StaticSite\Entity\TargetState;
use Byfareska\StaticSite\Target\Targets;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Whether each target is up to date: the content saved after its last deployment, or a scheduled change (an
 * article dated in the future) whose time has come since.
 */
final readonly class SiteStatus
{
    public function __construct(
        private Targets $targets,
        private EntityManagerInterface $em,
        private ContentChanges $changes,
        private SiteSources $sources,
    ) {
    }

    /** @return array<string, TargetStatus> */
    public function targets(): array
    {
        $lastChange = $this->changes->lastChange();
        $statuses = [];
        foreach ($this->targets->all() as $name => $target) {
            $state = $this->em->find(TargetState::class, $name);
            // What the target holds is the content as it was when its build was made - not when it arrived: an
            // edit made while the site was building is not in it.
            $contentAt = $state?->deployedAt === null ? null : ($state->publication?->contentAt() ?? $state->deployedAt);
            $changed = $contentAt === null || ($lastChange !== null && self::isAfter($lastChange, $contentAt));
            $scheduled = $contentAt === null ? null : $this->sources->nextChangeAfter($contentAt);
            $statuses[$name] = new TargetStatus($target, $state, $changed, $scheduled);
        }

        return $statuses;
    }

    /** @param list<string>|null $only the names of the targets to check, null for all */
    public function anyNeedsPublication(?array $only = null): bool
    {
        foreach ($this->targets() as $name => $status) {
            if (($only === null || in_array($name, $only, true)) && $status->needsPublication()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A change after the reading of the content. A database that keeps whole seconds only (a DATETIME of MySQL)
     * loses the fraction of the change: one in the second the building began counts as after it then.
     */
    private static function isAfter(\DateTimeImmutable $change, \DateTimeImmutable $contentAt): bool
    {
        if ($change->format('u') !== '000000') {
            return $change > $contentAt;
        }

        return $change >= $contentAt->setTime((int) $contentAt->format('H'), (int) $contentAt->format('i'), (int) $contentAt->format('s'));
    }
}
