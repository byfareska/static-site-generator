<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Dashboard\DashboardWidgetProps;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\SiteStatus;
use Byfareska\StaticSite\Publication\TargetStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/** The dashboard: what each target holds, and the last publications. */
#[AsLiveComponent('StaticSite:StatusWidget', template: '@StaticSite/crud/status_widget.html.twig')]
final class StatusWidget implements ScopedComponent
{
    use DashboardWidgetProps;
    use DefaultActionTrait;

    public function __construct(
        private readonly SiteStatus $status,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array<string, TargetStatus> */
    public function getTargets(): array
    {
        return $this->status->targets();
    }

    /** @return list<Publication> */
    public function getLast(): array
    {
        return $this->em->getRepository(Publication::class)->findBy([], ['id' => 'DESC'], 5);
    }
}
