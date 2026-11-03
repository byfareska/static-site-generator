<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\RecordActionRunner;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\StaticSite\Endpoint\SubmissionStatus;
use Byfareska\StaticSite\Entity\SiteSubmission;
use Doctrine\ORM\EntityManagerInterface;

/** "Handled" (with a note) and "spam" of a submission. */
final readonly class MarkSubmission implements RecordActionRunner
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function run(RunAction $action, RecordView $record, ?string $input): ?BulkResult
    {
        $submission = $this->em->find(SiteSubmission::class, (int) $record->id) ?? throw CrudFailure::notFound();
        $submission->status = $action->name === 'spam' ? SubmissionStatus::Spam : SubmissionStatus::Handled;
        $submission->handledAt = new \DateTimeImmutable();
        if ($input !== null && trim($input) !== '') {
            $submission->note = trim($input);
        }
        $this->em->flush();

        return null;
    }
}
