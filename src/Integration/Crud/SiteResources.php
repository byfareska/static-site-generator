<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Action\BulkDeleteAction;
use Byfareska\Crud\Action\ConfirmInput;
use Byfareska\Crud\Action\NoAction;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\StaticSite\Endpoint\SiteEndpoints;
use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Entity\SiteSubmission;
use Byfareska\StaticSite\Target\Targets;

/**
 * The resources of the site in the panel: the publications (the history, a rollback, a retry) and, when the site has
 * endpoints, what visitors sent.
 */
final readonly class SiteResources implements CrudResourceProvider
{
    public function __construct(
        private Panel $panel,
        private Targets $targets,
        private SiteEndpoints $endpoints,
    ) {
    }

    public function resources(): iterable
    {
        $targets = [];
        foreach ($this->targets->all() as $name => $target) {
            $targets[$target->label] = $name;
        }

        yield new AsCrud(
            name: Panel::PUBLICATION,
            path: '/publikacje',
            icon: 'cloud-upload',
            source: new DoctrineSource(
                Publication::class,
                filters: ['status', 'createdAt'],
                sortable: ['id', 'createdAt', 'status'],
                sort: ['-id'],
                operations: [CrudOperation::List, CrudOperation::Read],
            ),
            columns: ['id', 'status', 'targets', 'requestedBy', 'createdAt', 'finishedAt', 'fileCount', 'uploaded', 'deleted', 'rollbackOf'],
            details: ['status', 'targets', 'buildId', 'rollbackOf', 'requestedBy', 'createdAt', 'startedAt', 'finishedAt', 'fileCount', 'uploaded', 'deleted', 'log'],
            theme: '@StaticSite/crud/values.html.twig',
            palette: [CrudOperation::List],
            section: $this->panel->section,
            role: $this->panel->role,
            priority: 900,
            actions: [
                new RunAction(
                    'rollback',
                    'arrow-counterclockwise',
                    RollbackPublication::class,
                    hint: 'crud.site_publication.action.rollback_hint',
                    input: new ConfirmInput('crud.site_publication.action.rollback_target', choices: $targets, translateChoices: false),
                    update: false,
                ),
                new RunAction('retry', 'arrow-repeat', RetryPublication::class, message: 'crud.site_publication.action.retry_question', update: false),
            ],
            scope: $this->panel->scope,
            preview: false,
        );

        if (!$this->endpoints->isEmpty()) {
            $endpoints = array_keys($this->endpoints->all());

            yield new AsCrud(
                name: Panel::SUBMISSION,
                path: '/zgloszenia',
                icon: 'inbox',
                source: new DoctrineSource(
                    SiteSubmission::class,
                    filters: ['endpoint', 'status', 'createdAt'],
                    sortable: ['createdAt', 'endpoint', 'status'],
                    sort: ['-createdAt'],
                    operations: [CrudOperation::List, CrudOperation::Read, CrudOperation::Delete],
                ),
                columns: ['createdAt', 'endpoint', 'status', 'data'],
                details: ['endpoint', 'status', 'createdAt', 'handledAt', 'page', 'data', 'note', 'userAgent'],
                theme: '@StaticSite/crud/values.html.twig',
                palette: [CrudOperation::List],
                section: $this->panel->section,
                role: $this->panel->role,
                priority: 800,
                actions: [
                    new RunAction('handled', 'check2-circle', MarkSubmission::class, input: new ConfirmInput('crud.site_submission.action.note', required: false), inline: true, update: false),
                    new RunAction('spam', 'slash-circle', MarkSubmission::class, message: 'crud.site_submission.action.spam_question', update: false),
                    new NoAction('edit'),
                ],
                scope: $this->panel->scope,
                bulkActions: [new BulkDeleteAction()],
                filtersInView: ['status'],
                options: ['endpoints' => $endpoints],
            );
        }
    }
}
