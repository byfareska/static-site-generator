<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Preview;

use Byfareska\StaticSite\Build\RenderState;
use Byfareska\StaticSite\Build\SiteRenderer;
use Byfareska\StaticSite\Build\SiteSources;
use Byfareska\StaticSite\Build\Sitemap;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\MimeTypes;

/**
 * The site drawn on request, by the code of the build: /<prefix>/live/... as it would be published, /<prefix>/drafts/...
 * with the drafts and scheduled content. Links are relative, so the whole site can be browsed under the prefix.
 * Import config/routes/preview.php under the prefix of the panel (its firewall guards it).
 */
final readonly class PreviewController
{
    public function __construct(
        private SiteSources $sources,
        private SiteRenderer $renderer,
        private RenderState $state,
        private SitePaths $paths,
        #[Autowire('%static_site.endpoints.script%')]
        private string $endpointScript,
        #[Autowire('%static_site.endpoints.script_source%')]
        private string $endpointScriptSource,
    ) {
    }

    public function __invoke(Request $request, string $mode, string $path = ''): Response
    {
        $context = RenderContext::preview($mode === 'drafts');
        $this->state->begin($context);
        $file = SitePaths::fileOf($path);

        // A directory without its slash: relative links would resolve one level too high.
        $isDirectory = $path === '' || ($this->sources->page($context, $path . '/') !== null && !$this->sources->page($context, $path));
        if ($isDirectory && !str_ends_with($request->getPathInfo(), '/')) {
            return new RedirectResponse($request->getBaseUrl() . $request->getPathInfo() . '/', 301);
        }

        $page = $this->sources->page($context, $file);
        if ($page !== null) {
            $content = $this->renderer->render($page, $context);
            if ($page->isHtml()) {
                $content = self::withBadge($content, $context->includeDrafts);
            }

            return $this->response($content, $file);
        }
        $files = $this->state->files();
        if (isset($files[$file])) {
            $siteFile = $files[$file];
            if ($siteFile->source !== null && is_file($siteFile->source)) {
                $response = new BinaryFileResponse($siteFile->source);
                $response->headers->set('Content-Type', self::type($file));

                return $response;
            }

            return $this->response($siteFile->read(), $file);
        }
        if ($file === 'sitemap.xml' || $file === 'robots.txt') {
            $pages = array_values(array_filter($this->sources->pages($context), static fn (SitePage $p): bool => $p->isInSitemap()));

            return $this->response($file === 'robots.txt' ? Sitemap::robots($this->paths) : Sitemap::xml($pages, $this->paths, $context->now), $file);
        }
        if ($file === $this->endpointScript) {
            return new BinaryFileResponse($this->endpointScriptSource, headers: ['Content-Type' => 'text/javascript']);
        }
        foreach ($this->sources->redirects() as $redirect) {
            if ($redirect->fromPath() === $file) {
                $to = $redirect->isExternal()
                    ? $redirect->to
                    : $this->paths->within($file, fn (): string => $this->paths->relative($redirect->to));

                return new RedirectResponse($to);
            }
        }

        throw new NotFoundHttpException(sprintf('The site has no page or file "%s".', $file));
    }

    private function response(string $content, string $file): Response
    {
        $response = new Response($content, 200, ['Content-Type' => self::type($file)]);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->setPrivate();

        return $response;
    }

    private static function type(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = match ($extension) {
            'html', 'htm' => 'text/html',
            'xml' => 'application/xml',
            'txt', '' => 'text/plain',
            'js' => 'text/javascript',
            'css' => 'text/css',
            default => MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream',
        };

        return str_starts_with($type, 'text/') || str_ends_with($type, 'xml') ? $type . '; charset=UTF-8' : $type;
    }

    private static function withBadge(string $html, bool $drafts): string
    {
        $badge = sprintf(
            '<div style="position:fixed;z-index:2147483647;left:12px;bottom:12px;padding:6px 10px;border-radius:6px;'
            . 'background:#111;color:#fff;font:600 12px/1.2 system-ui,sans-serif;opacity:.85;pointer-events:none">'
            . 'Podgląd%s</div>',
            $drafts ? ' ze szkicami' : '',
        );
        $at = strripos($html, '</body>');

        return $at === false ? $html . $badge : substr_replace($html, $badge, $at, 0);
    }
}
