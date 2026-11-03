<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * With symfony/scheduler, a worker consuming "scheduler_static_site" publishes scheduled content when it is due
 * (static_site.publish.schedule_every; the console's static-site:publish-scheduled does the same from cron).
 */
#[AsSchedule(self::NAME)]
final class PublicationSchedule implements ScheduleProviderInterface
{
    public const string NAME = 'static_site';

    private ?Schedule $schedule = null;

    public function __construct(
        #[Autowire('%static_site.publish.schedule_every%')]
        private readonly string $every,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())->add(RecurringMessage::every($this->every, new PublishScheduled()));
    }
}
