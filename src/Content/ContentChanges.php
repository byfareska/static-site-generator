<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

use Byfareska\StaticSite\Entity\SiteState;
use Doctrine\ORM\EntityManagerInterface;

/**
 * When the content last changed. Written with plain SQL (an UPDATE, else an INSERT), so it works on SQLite, MySQL and
 * PostgreSQL alike and never runs another flush from within one.
 */
final readonly class ContentChanges
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function touch(?\DateTimeImmutable $at = null): void
    {
        $connection = $this->em->getConnection();
        $table = $this->em->getClassMetadata(SiteState::class)->getTableName();
        // With its microseconds: a database that keeps them (SQLite, a DATETIME(6)) tells an edit from a build begun
        // in the same second.
        $now = ($at ?? new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
        $updated = $connection->executeStatement("UPDATE $table SET content_changed_at = ? WHERE id = 1", [$now]);
        if ($updated === 0) {
            $connection->executeStatement("INSERT INTO $table (id, content_changed_at) VALUES (1, ?)", [$now]);
        }
    }

    public function lastChange(): ?\DateTimeImmutable
    {
        $connection = $this->em->getConnection();
        $table = $this->em->getClassMetadata(SiteState::class)->getTableName();
        $value = $connection->fetchOne("SELECT content_changed_at FROM $table WHERE id = 1");

        return is_string($value) ? new \DateTimeImmutable($value) : null;
    }
}
