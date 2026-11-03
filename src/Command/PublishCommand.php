<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Command;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\PublicationStatus;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Target\Targets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('static-site:publish', 'Builds the site and sends it to targets (here, without a worker)')]
final readonly class PublishCommand
{
    public function __construct(
        private Publisher $publisher,
        private Targets $targets,
        private EntityManagerInterface $em,
    ) {
    }

    /** @param list<string> $target */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The targets (all when none is named)')] array $target = [],
        #[Option('Send the build of this publication again instead of building')] ?int $rollback = null,
        #[Option('Only build, into var/static_site/builds')] bool $buildOnly = false,
    ): int {
        if ($buildOnly) {
            $build = $this->publisher->buildOnly('manual-' . date('Ymd-His'), static fn (string $line) => $io->writeln($line));
            $io->success(sprintf('Zbudowano %d plików w %s', count($build->files), $build->directory));

            return Command::SUCCESS;
        }
        $names = $target === [] ? $this->targets->names() : $target;
        $source = $rollback === null ? null : $this->em->find(Publication::class, $rollback);
        if ($rollback !== null && $source === null) {
            $io->error(sprintf('Nie ma publikacji #%d.', $rollback));

            return Command::FAILURE;
        }
        if ($this->publisher->active() !== null) {
            $io->error('Inna publikacja jest w toku. Poczekaj, aż się skończy.');

            return Command::FAILURE;
        }
        $publication = new Publication($names, 'console', $source);
        $this->em->persist($publication);
        $this->em->flush();
        $this->publisher->run($publication);

        $io->writeln((string) $publication->log);
        if ($publication->status !== PublicationStatus::Done) {
            $io->error('Publikacja się nie udała.');

            return Command::FAILURE;
        }
        $io->success(sprintf('Opublikowano na: %s', implode(', ', $names)));

        return Command::SUCCESS;
    }
}
