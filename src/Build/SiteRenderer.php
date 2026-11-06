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

            return $this->twig->render((string) $page->template, self::variables($page, $context));
        });
    }

    /** Draws another template of the page (its Markdown version) with the variables and the links of the page. */
    public function renderTemplate(SitePage $page, RenderContext $context, string $template): string
    {
        return $this->paths->within(
            $page->path,
            fn (): string => $this->twig->render($template, self::variables($page, $context)),
        );
    }

    /** @return array<string, mixed> */
    private static function variables(SitePage $page, RenderContext $context): array
    {
        return [...$page->context, 'site_page' => $page, 'site_context' => $context];
    }
}
