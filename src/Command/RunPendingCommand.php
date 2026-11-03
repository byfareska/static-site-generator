<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Command;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\PublicationStatus;
use Byfareska\StaticSite\Publication\Publisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For static_site.publish.mode "cron": run it every minute (crontab: * * * * * php bin/console static-site:run-pending).
 */
#[AsCommand('static-site:run-pending', 'Runs the publications requested in the panel and waiting')]
final readonly class RunPendingCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private Publisher $publisher,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        /** @var list<Publication> $pending */
        $pending = $this->em->getRepository(Publication::class)->findBy(['status' => PublicationStatus::Pending], ['id' => 'ASC']);
        foreach ($pending as $publication) {
            $io->writeln(sprintf('Publikacja %s', $publication));
            $this->publisher->run($publication);
            $io->writeln(sprintf('  %s', $publication->status->value));
        }

        return Command::SUCCESS;
    }
}
