<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Command;

use Byfareska\StaticSite\Endpoint\SiteSubmissions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('static-site:submissions:purge', 'Deletes the submissions of the endpoints older than static_site.submissions.keep_days')]
final readonly class PurgeSubmissionsCommand
{
    public function __construct(
        private SiteSubmissions $submissions,
        #[Autowire('%static_site.submissions.keep_days%')]
        private int $keepDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Days to keep')] ?int $days = null): int
    {
        $removed = $this->submissions->purgeOlderThan($days ?? $this->keepDays);
        $io->success(sprintf('Usunięto zgłoszeń: %d.', $removed));

        return Command::SUCCESS;
    }
}
