<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\File\FileProvider;
use Byfareska\StaticSite\File\SiteFile;
use Byfareska\StaticSite\Page\PageProvider;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SchedulesChanges;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use Byfareska\StaticSite\Redirect\Redirect;
use Byfareska\StaticSite\Redirect\RedirectProvider;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Everything the site is made of, asked of the providers of the application.
 */
final readonly class SiteSources
{
    /**
     * @param iterable<PageProvider>     $pageProviders
     * @param iterable<FileProvider>     $fileProviders
     * @param iterable<RedirectProvider> $redirectProviders
     */
    public function __construct(
        #[AutowireIterator(PageProvider::PAGE_PROVIDER_TAG)]
        private iterable $pageProviders,
        #[AutowireIterator(FileProvider::FILE_PROVIDER_TAG)]
        private iterable $fileProviders,
        #[AutowireIterator(RedirectProvider::REDIRECT_PROVIDER_TAG)]
        private iterable $redirectProviders,
    ) {
    }

    /** @return array<string, SitePage> by path; a later provider's page of the same path is a mistake */
    public function pages(RenderContext $context): array
    {
        $pages = [];
        foreach ($this->pageProviders as $provider) {
            foreach ($provider->pages($context) as $page) {
                if (isset($pages[$page->path])) {
                    throw new \LogicException(sprintf('Two pages of the site have the path "%s".', $page->path));
                }
                $pages[$page->path] = $page;
            }
        }

        return $pages;
    }

    /** @return array<string, SiteFile> by path; a later file of the same path replaces the earlier one */
    public function files(RenderContext $context): array
    {
        $files = [];
        foreach ($this->fileProviders as $provider) {
            foreach ($provider->files($context) as $file) {
                $files[$file->path] = $file;
            }
        }

        return $files;
    }

    /** @return list<Redirect> */
    public function redirects(): array
    {
        $redirects = [];
        foreach ($this->redirectProviders as $provider) {
            foreach ($provider->redirects() as $redirect) {
                $redirects[$redirect->fromPath()] = $redirect;
            }
        }

        return array_values($redirects);
    }

    public function page(RenderContext $context, string $path): ?SitePage
    {
        return $this->pages($context)[SitePaths::fileOf($path)] ?? null;
    }

    public function nextChangeAfter(\DateTimeImmutable $after): ?\DateTimeImmutable
    {
        $next = null;
        foreach ([...$this->pageProviders, ...$this->fileProviders] as $provider) {
            if ($provider instanceof SchedulesChanges) {
                $at = $provider->nextChangeAfter($after);
                if ($at !== null && ($next === null || $at < $next)) {
                    $next = $at;
                }
            }
        }

        return $next;
    }
}
