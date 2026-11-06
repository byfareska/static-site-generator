<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

use Byfareska\StaticSite\Entity\SiteState;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The settings of the site made in the panel (the row of static_site_state), each with its default in the
 * configuration. A change is a change of the site: the targets need a publication.
 */
final readonly class SiteSettings
{
    public function __construct(
        private EntityManagerInterface $em,
        private ContentChanges $changes,
        #[Autowire('%static_site.files.publish_all%')]
        private bool $publishAllFiles = true,
    ) {
    }

    /** Whether every file of the libraries is published, used by a page or not. */
    public function publishAllFiles(): bool
    {
        $connection = $this->em->getConnection();
        $value = $connection->fetchOne(sprintf('SELECT publish_all_files FROM %s WHERE id = 1', $this->table()));
        if ($value === false || $value === null) {
            return $this->publishAllFiles;
        }

        return (bool) Type::getType(Types::BOOLEAN)->convertToPHPValue($value, $connection->getDatabasePlatform());
    }

    public function setPublishAllFiles(bool $publishAll): void
    {
        if ($publishAll === $this->publishAllFiles()) {
            return;
        }
        $this->changes->touch();
        $this->em->getConnection()->executeStatement(
            sprintf('UPDATE %s SET publish_all_files = ? WHERE id = 1', $this->table()),
            [$publishAll],
            [ParameterType::BOOLEAN],
        );
    }

    private function table(): string
    {
        return $this->em->getClassMetadata(SiteState::class)->getTableName();
    }
}
