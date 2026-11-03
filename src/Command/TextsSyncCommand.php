<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Command;

use Byfareska\StaticSite\Entity\SiteText;
use Byfareska\StaticSite\Text\Texts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('static-site:texts', 'Lists the texts kept in the database that no catalog knows any more')]
final readonly class TextsSyncCommand
{
    public function __construct(
        private Texts $texts,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Delete them')] bool $prune = false): int
    {
        $io->writeln(sprintf('Teksty w katalogach: %d, w grupach: %d.', count($this->texts->items()), count($this->texts->groups())));
        $orphans = $this->texts->orphans();
        if ($orphans === []) {
            $io->success('Każdy zapisany tekst jest w katalogu.');

            return Command::SUCCESS;
        }
        $io->listing($orphans);
        if ($prune) {
            foreach ($orphans as $key) {
                $text = $this->em->find(SiteText::class, $key);
                if ($text !== null) {
                    $this->em->remove($text);
                }
            }
            $this->em->flush();
            $io->success(sprintf('Usunięto %d.', count($orphans)));
        } else {
            $io->note('Uruchom z --prune, żeby je usunąć.');
        }

        return Command::SUCCESS;
    }
}
