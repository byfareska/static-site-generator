<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Navigation\NavigationExtension;
use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\StaticSite\Endpoint\SiteSubmissions;

/** The number of new submissions next to "Zgłoszenia" in the menu. */
final readonly class SubmissionsBadge implements NavigationExtension
{
    public function __construct(private SiteSubmissions $submissions)
    {
    }

    public function extend(array $items): array
    {
        return array_map(fn (NavigationItem $item): NavigationItem => $item->resource === Panel::SUBMISSION
            ? $item->with(badge: $this->submissions->countNew(...), badgeVariant: 'info')
            : $item, $items);
    }
}
