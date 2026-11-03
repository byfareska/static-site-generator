<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Twig;

use Byfareska\StaticSite\Build\RenderState;
use Byfareska\StaticSite\Endpoint\SiteEndpoints;
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
     * the preview and on a site in a subdirectory too.
     */
    #[AsTwigFilter('site_links', isSafe: ['html'])]
    public function links(?string $html): string
    {
        return self::relativeLinks((string) $html, $this->paths);
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

    public static function relativeLinks(string $html, SitePaths $paths): string
    {
        return (string) preg_replace_callback(
            '/\b(href|src)=(["\'])\/(?!\/)([^"\']*)\2/i',
            static fn (array $m): string => sprintf('%s=%s%s%s', $m[1], $m[2], $paths->relative('/' . $m[3]), $m[2]),
            $html,
        );
    }
}
