<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use Byfareska\StaticSite\Redirect\Htaccess;
use Byfareska\StaticSite\Redirect\RedirectStub;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds the whole site into a directory of its own: files of the providers, pages, redirects, then sitemap.xml and
 * robots.txt. A build is the same for every target; publishing sends it.
 */
final readonly class SiteBuilder
{
    private Filesystem $fs;

    public function __construct(
        private SiteSources $sources,
        private SiteRenderer $renderer,
        private RenderState $state,
        private SitePaths $paths,
        private BuildStore $store,
        #[Autowire('%static_site.sitemap%')]
        private bool $sitemap,
        #[Autowire('%static_site.htaccess%')]
        private bool $htaccess,
        #[Autowire('%static_site.endpoints.prefix%')]
        private string $endpointPrefix,
        #[Autowire('%static_site.endpoints.script%')]
        private string $endpointScript,
        #[Autowire('%static_site.endpoints.script_source%')]
        private string $endpointScriptSource,
    ) {
        $this->fs = new Filesystem();
    }

    /** @param \Closure(string): void|null $log */
    public function build(string $id, ?RenderContext $context = null, ?\Closure $log = null): Build
    {
        $context ??= RenderContext::build();
        $log ??= static function (string $line): void {
        };
        $dir = $this->store->directory($id);
        if (is_dir($dir)) {
            throw new \RuntimeException(sprintf('The build "%s" exists already.', $id));
        }

        try {
            $this->state->begin($context);
            $this->fs->mkdir($dir);

            $files = $this->state->files();
            foreach ($files as $file) {
                $this->fs->dumpFile($dir . '/' . $file->path, $file->read());
            }
            $log(sprintf('Pliki: %d', count($files)));

            $pages = $this->sources->pages($context);
            foreach ($pages as $page) {
                if (isset($files[$page->path])) {
                    $log(sprintf('Strona %s zastępuje plik o tej samej ścieżce.', $page->path));
                }
                $this->fs->dumpFile($dir . '/' . $page->path, $this->renderer->render($page, $context));
            }
            $log(sprintf('Strony: %d', count($pages)));

            $endpoints = $this->state->usedEndpoints();
            if ($endpoints !== []) {
                $this->fs->copy($this->endpointScriptSource, $dir . '/' . $this->endpointScript, true);
                $log('Endpointy używane przez strony: ' . implode(', ', $endpoints));
            }

            $this->writeRedirects($dir, $pages, $endpoints !== [], $log);

            if ($this->sitemap) {
                $inSitemap = array_values(array_filter($pages, static fn (SitePage $p): bool => $p->isInSitemap()));
                if (!isset($pages['sitemap.xml'])) {
                    $this->fs->dumpFile($dir . '/sitemap.xml', Sitemap::xml($inSitemap, $this->paths, $context->now));
                }
                if (!isset($pages['robots.txt']) && !isset($files['robots.txt'])) {
                    $this->fs->dumpFile($dir . '/robots.txt', Sitemap::robots($this->paths));
                }
                $log(sprintf('sitemap.xml: %d adresów', count($inSitemap)));
            }

            $build = new Build($id, $dir, $this->store->scan($id), $endpoints, $context->now);
            $this->store->save($build);

            return $build;
        } catch (\Throwable $e) {
            $this->store->remove($id);

            throw $e;
        } finally {
            $this->state->reset();
        }
    }

    /**
     * @param array<string, SitePage> $pages
     * @param \Closure(string): void  $log
     */
    private function writeRedirects(string $dir, array $pages, bool $hasEndpoints, \Closure $log): void
    {
        $redirects = $this->sources->redirects();
        foreach ($redirects as $redirect) {
            $from = $redirect->fromPath();
            if (isset($pages[$from]) || is_file($dir . '/' . $from)) {
                throw new \LogicException(sprintf(
                    'The address "%s" is redirected to "%s" but is also a page or a file of the site.',
                    $from,
                    $redirect->to,
                ));
            }
            $to = $redirect->isExternal() ? $redirect->to : null;
            $html = $this->paths->within($from, fn (): string => RedirectStub::html(
                $to ?? $this->paths->relative($redirect->to),
                $to ?? $this->paths->absolute($redirect->to),
            ));
            $this->fs->dumpFile($dir . '/' . $from, $html);
        }
        if ($redirects !== []) {
            $log(sprintf('Przekierowania: %d', count($redirects)));
        }

        $file = $dir . '/.htaccess';
        if (!$this->htaccess || ($redirects === [] && !$hasEndpoints && !is_file($file))) {
            return;
        }
        $block = Htaccess::block($redirects, $this->paths, $hasEndpoints ? $this->endpointPrefix : null);
        $this->fs->dumpFile($file, Htaccess::merge(is_file($file) ? (string) file_get_contents($file) : '', $block));
    }
}
