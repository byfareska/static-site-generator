<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Publication\SiteStatus;
use Byfareska\StaticSite\Publication\TargetStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The top bar of the panel: "unpublished changes", the button that publishes (to one target or all that need it),
 * the progress of a publication running - drawn again every two seconds while it runs - and the way to the preview.
 */
#[AsLiveComponent('StaticSite:PublishButton', template: '@StaticSite/crud/publish_button.html.twig')]
final class PublishButton
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $error = null;

    #[LiveProp]
    public ?int $justAsked = null;

    public function __construct(
        private readonly SiteStatus $status,
        private readonly Publisher $publisher,
        private readonly EntityManagerInterface $em,
        private readonly CurrentUser $user,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return array<string, TargetStatus> */
    public function getTargets(): array
    {
        return $this->status->targets();
    }

    /** @return list<string> */
    public function getOutdated(): array
    {
        return array_keys(array_filter($this->getTargets(), static fn (TargetStatus $status): bool => $status->needsPublication()));
    }

    public function getActive(): ?Publication
    {
        return $this->publisher->active();
    }

    /** The publication asked for here that has just finished: its result is shown until the next render. */
    public function getFinished(): ?Publication
    {
        if ($this->justAsked === null) {
            return null;
        }
        $publication = $this->em->find(Publication::class, $this->justAsked);

        return $publication !== null && $publication->status->isFinished() ? $publication : null;
    }

    /** @param array<string, scalar> $parameters */
    public function url(string $route, array $parameters = []): ?string
    {
        try {
            return $this->urls->generate($route, $parameters);
        } catch (RouteNotFoundException) {
            return null;
        }
    }

    /** @param string $target a target, or "*" for every one that needs it */
    #[LiveAction]
    public function publish(#[LiveArg] string $target): void
    {
        $this->error = null;
        $targets = $target === '*' ? $this->getOutdated() : [$target];
        if ($targets === []) {
            $this->error = $this->translator->trans('static_site.publish.nothing');

            return;
        }
        try {
            $publication = $this->publisher->request($targets, $this->user->name());
            $this->justAsked = $publication->id;
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }
}
