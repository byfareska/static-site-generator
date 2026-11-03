<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Scheduler;

use Byfareska\StaticSite\Publication\ScheduledPublications;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PublishScheduledHandler
{
    public function __construct(
        private ScheduledPublications $scheduled,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(PublishScheduled $message): void
    {
        // A worker runs for long: the content read now, not what Doctrine remembers from before.
        $this->em->clear();
        $this->scheduled->request();
    }
}
