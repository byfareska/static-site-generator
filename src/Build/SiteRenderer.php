<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use Psr\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * Draws a page of the site. The build and the preview of the panel both go through it.
 */
final readonly class SiteRenderer
{
    public function __construct(
        private Environment $twig,
        private SitePaths $paths,
        private EventDispatcherInterface $events,
    ) {
    }

    public function render(SitePage $page, RenderContext $context): string
    {
        $this->events->dispatch(new PageRendering($page, $context));

        return $this->paths->within($page->path, function () use ($page, $context): string {
            if ($page->render !== null) {
                return ($page->render)();
            }

            return $this->twig->render((string) $page->template, [
                ...$page->context,
                'site_page' => $page,
                'site_context' => $context,
            ]);
        });
    }
}
