<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Entity\Publication;

/**
 * The targets whose content changes by itself (an article scheduled for a date: SchedulesChanges) and whose date has
 * come: static-site:publish-scheduled from cron, or the schedule of symfony/scheduler in a worker.
 */
final readonly class ScheduledPublications
{
    public const string REQUESTED_BY = 'scheduler';

    public function __construct(
        private SiteStatus $status,
        private Publisher $publisher,
    ) {
    }

    /**
     * @param list<string> $only the targets to check, all when empty
     *
     * @return list<string>
     */
    public function due(array $only = [], ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $due = [];
        foreach ($this->status->targets() as $name => $status) {
            if (($only === [] || in_array($name, $only, true))
                && $status->scheduledChange !== null && $status->scheduledChange <= $now) {
                $due[] = $name;
            }
        }

        return $due;
    }

    /**
     * Asks for the publication of the targets due, if any.
     *
     * @param list<string> $only
     */
    public function request(array $only = []): ?Publication
    {
        $due = $this->due($only);

        return $due === [] ? null : $this->publisher->request($due, self::REQUESTED_BY);
    }
}
