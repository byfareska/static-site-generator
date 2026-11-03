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
use Symfony\Contracts\Translation\TranslatorInterface;

/** Sends the files of a publication again, to the target chosen: the site as it was then, without building it. */
final readonly class RollbackPublication implements RecordActionRunner
{
    public function __construct(
        private EntityManagerInterface $em,
        private Publisher $publisher,
        private CurrentUser $user,
        private PublicationResult $result,
        private TranslatorInterface $translator,
    ) {
    }

    public function run(RunAction $action, RecordView $record, ?string $input): BulkResult
    {
        $publication = $this->em->find(Publication::class, (int) $record->id) ?? throw CrudFailure::notFound();
        if ($input === null || $input === '') {
            throw new CrudFailure(422, $this->translator->trans('crud.site_publication.action.rollback_target'));
        }
        $this->result->assertNoneRunning();
        try {
            $rollback = $this->publisher->request([$input], $this->user->name(), $publication);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw new CrudFailure(409, $e->getMessage(), previous: $e);
        }

        return $this->result->requested($rollback);
    }
}
