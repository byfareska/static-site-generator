<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\RecordActionRunner;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\Publisher;
use Doctrine\ORM\EntityManagerInterface;

/** A failed publication asked for again: the same targets, the content as it is now (or the same build, for a rollback). */
final readonly class RetryPublication implements RecordActionRunner
{
    public function __construct(
        private EntityManagerInterface $em,
        private Publisher $publisher,
        private CurrentUser $user,
        private PublicationResult $result,
    ) {
    }

    public function run(RunAction $action, RecordView $record, ?string $input): BulkResult
    {
        $failed = $this->em->find(Publication::class, (int) $record->id) ?? throw CrudFailure::notFound();
        $this->result->assertNoneRunning();
        try {
            $publication = $this->publisher->request($failed->targets, $this->user->name(), $failed->rollbackOf);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw new CrudFailure(409, $e->getMessage(), previous: $e);
        }

        return $this->result->requested($publication);
    }
}
