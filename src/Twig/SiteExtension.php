<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Twig;

use Byfareska\StaticSite\Build\RenderState;
use Byfareska\StaticSite\Content\SiteLinks;
use Byfareska\StaticSite\Endpoint\SiteEndpoints;
use Byfareska\StaticSite\File\UploadedFiles;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePaths;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Links between the files of the site, in the templates of its pages.
 */
final readonly class SiteExtension
{
    public function __construct(
        private SitePaths $paths,
        private RenderState $state,
        private SiteEndpoints $endpoints,
        private SiteLinks $links,
        private UploadedFiles $files,
        #[Autowire('%static_site.endpoints.script%')]
        private string $endpointScript,
    ) {
    }

    /** A link from the page being drawn to a path of the site: site_path('artykuly/'), site_path('index.html#faq'). */
    #[AsTwigFunction('site_path')]
    public function path(string $target = ''): string
    {
        return $this->paths->relative($target);
    }

    /** site_path() with the version of the file: "css/style.css?v=1a2b3c4d", changed when the file changes. */
    #[AsTwigFunction('site_asset')]
    public function asset(string $target): string
    {
        $path = ltrim($target, '/');
        $hash = $this->state->fileHash($path);

        return $this->paths->relative($path) . ($hash === null ? '' : '?v=' . substr($hash, 0, 8));
    }

    /** The absolute address of a path: for canonical links, Open Graph, feeds. */
    #[AsTwigFunction('site_url')]
    public function url(string $target = ''): string
    {
        return $this->paths->absolute($target);
    }

    /**
     * A link to a file: an uploaded one ("public://ab/cd/x.jpg", the value of a field of files), published with the
     * site because the page uses it, or a path of the site ("img/hero.jpg"); "" for none.
     */
    #[AsTwigFunction('site_file')]
    public function file(string|\Stringable|null $file): string
    {
        $path = $this->filePath($file);

        return $path === null ? '' : $this->paths->relative($path);
    }

    /** site_file() as an absolute address: for Open Graph, feeds. */
    #[AsTwigFunction('site_file_url')]
    public function fileUrl(string|\Stringable|null $file): string
    {
        $path = $this->filePath($file);

        return $path === null ? '' : $this->paths->absolute($path);
    }

    /** The path of the site of a reference or of a path; null for none. */
    public function filePath(string|\Stringable|null $file): ?string
    {
        $value = trim((string) $file);
        if ($value === '') {
            return null;
        }

        $uploaded = $this->files->use($value);

        return $uploaded !== null ? $uploaded->path : ltrim($value, '/');
    }

    #[AsTwigFunction('site_current_path')]
    public function currentPath(): string
    {
        return $this->paths->current();
    }

    #[AsTwigFunction('site_render_context')]
    public function context(): RenderContext
    {
        return $this->state->context();
    }

    /**
     * The links of HTML written in the panel ("/propozycje.png", "/#faq") made relative to the page, so they work in
     * the preview and on a site in a subdirectory too; its uploaded files published with the site.
     */
    #[AsTwigFilter('site_links', isSafe: ['html'])]
    public function links(?string $html): string
    {
        return $this->links->rewrite($html);
    }

    /** The address of an endpoint of the application (#[AsSiteEndpoint]); the build notes that the site uses it. */
    #[AsTwigFunction('site_endpoint')]
    public function endpoint(string $name): string
    {
        $this->state->useEndpoint($name);

        return $this->paths->basePath() . $this->endpoints->path($name);
    }

    /** The <script> that sends the forms of the endpoints (<form data-site-endpoint="...">). */
    #[AsTwigFunction('site_endpoint_script', isSafe: ['html'])]
    public function endpointScript(): string
    {
        $this->state->useEndpoint(SiteEndpoints::TOKEN);

        return sprintf(
            '<script src="%s" data-token-url="%s" defer></script>',
            htmlspecialchars($this->paths->relative($this->endpointScript)),
            htmlspecialchars($this->paths->basePath() . $this->endpoints->path(SiteEndpoints::TOKEN)),
        );
    }
}
