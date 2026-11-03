<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\Publisher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** What the actions asking for a publication share: one at a time, and the way to the one asked for. */
final readonly class PublicationResult
{
    public function __construct(
        private Publisher $publisher,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    /** @throws CrudFailure while another publication runs */
    public function assertNoneRunning(): void
    {
        $active = $this->publisher->active();
        if ($active !== null) {
            throw new CrudFailure(409, $this->translator->trans('static_site.publish.already_running', ['id' => $active->id]));
        }
    }

    public function requested(Publication $publication): BulkResult
    {
        return new BulkResult(
            $this->translator->trans('static_site.publish.requested', ['id' => $publication->id]),
            redirect: $this->urls->generate('crud_' . Panel::PUBLICATION . '_show', ['id' => $publication->id]),
        );
    }
}
