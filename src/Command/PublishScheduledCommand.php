<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Command;

use Byfareska\StaticSite\Publication\ScheduledPublications;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Publishes the targets whose scheduled content (an article dated in the future) is due. Run it from cron (without
 * a worker), e.g. every 10 minutes; with symfony/scheduler the worker checks by itself (schedule "static_site").
 */
#[AsCommand('static-site:publish-scheduled', 'Publishes the targets whose scheduled content is due')]
final readonly class PublishScheduledCommand
{
    public function __construct(private ScheduledPublications $scheduled)
    {
    }

    /** @param list<string> $target */
    public function __invoke(SymfonyStyle $io, #[Argument('The targets to check (all when none is named)')] array $target = []): int
    {
        $publication = $this->scheduled->request($target);
        if ($publication === null) {
            $io->writeln('Nic do opublikowania.');

            return Command::SUCCESS;
        }
        $io->writeln(sprintf('Zlecono publikację %s na: %s', $publication, implode(', ', $publication->targets)));

        return Command::SUCCESS;
    }
}
