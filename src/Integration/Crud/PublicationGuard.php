<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\StaticSite\Build\BuildStore;
use Byfareska\StaticSite\Publication\PublicationStatus;

/** A rollback is offered for a publication whose files are still kept; a retry for one that failed. */
final readonly class PublicationGuard implements RecordActionGuard
{
    public function __construct(private BuildStore $builds)
    {
    }

    public function resource(): string
    {
        return Panel::PUBLICATION;
    }

    public function fields(): array
    {
        return ['status', 'buildId'];
    }

    public function allowsAction(string $action, RecordView $record): bool
    {
        $status = $record->item['status'] ?? null;
        $status = $status instanceof PublicationStatus ? $status->value : (is_string($status) ? $status : null);

        return match ($action) {
            'rollback' => $status === PublicationStatus::Done->value
                && is_string($record->item['buildId'] ?? null)
                && $this->builds->exists($record->item['buildId']),
            'retry' => $status === PublicationStatus::Failed->value,
            default => true,
        };
    }
}
