<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Preview;

use Byfareska\StaticSite\Build\RenderState;
use Byfareska\StaticSite\Build\SiteRenderer;
use Byfareska\StaticSite\Build\SiteSources;
use Byfareska\StaticSite\Build\Sitemap;
use Byfareska\StaticSite\File\UploadedFiles;
use Byfareska\StaticSite\Markdown\LlmsTxt;
use Byfareska\StaticSite\Markdown\MarkdownRenderer;
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
 * with the drafts and scheduled content. Links are relative, so the whole site can be browsed under the prefix. With
 * clean URLs "x" is the page x.html; its Markdown version is at "x.md" or at "x" with "Accept: text/markdown".
 * Import config/routes/preview.php under the prefix of the panel (its firewall guards it).
 */
final readonly class PreviewController
{
    public function __construct(
        private SiteSources $sources,
        private SiteRenderer $renderer,
        private RenderState $state,
        private SitePaths $paths,
        private MarkdownRenderer $markdown,
        private UploadedFiles $uploads,
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

        $files = $this->state->files();
        $page = $this->sources->page($context, $file);
        $wantsMarkdown = $this->markdown->isEnabled() && MarkdownRenderer::wanted($request->headers->get('Accept'));
        if ($page === null && !isset($files[$file])) {
            if ($this->paths->cleanUrls() && pathinfo($file, PATHINFO_EXTENSION) === '') {
                $page = $this->sources->page($context, $file . '.html');
            } elseif ($this->markdown->isEnabled() && str_ends_with($file, '.md')) {
                $page = $this->sources->page($context, substr($file, 0, -strlen('.md')) . '.html');
                $page = $page !== null && $page->markdownPath() === $file ? $page : null;
                $wantsMarkdown = true;
            }
        }
        if ($page !== null) {
            $content = $this->renderer->render($page, $context);
            $markdownPath = $this->markdown->pathOf($page);
            if ($markdownPath !== null && $wantsMarkdown) {
                $response = $this->response($this->markdown->render($page, $context, $content)->content, $markdownPath);
            } else {
                if ($page->isHtml()) {
                    $content = self::withBadge($this->markdown->withAlternate($page, $content), $context->includeDrafts);
                }
                $response = $this->response($content, $page->path);
            }
            if ($markdownPath !== null) {
                $response->setVary('Accept');
            }

            return $response;
        }
        if (isset($files[$file])) {
            $siteFile = $files[$file];
            if ($siteFile->source !== null && is_file($siteFile->source)) {
                $response = new BinaryFileResponse($siteFile->source);
                $response->headers->set('Content-Type', self::type($file));

                return $response;
            }

            return $this->response($siteFile->read(), $file);
        }
        $upload = $this->uploads->atPath($file);
        if ($upload !== null) {
            $response = $this->response($upload->read(), $file);
            // A path of an upload is random and never written again; private: the preview is the panel's.
            $response->setMaxAge(86400);

            return $response;
        }
        if ($file === LlmsTxt::PATH && $this->markdown->hasLlmsTxt()) {
            return $this->response($this->llmsTxt($context), $file);
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

    private function llmsTxt(RenderContext $context): string
    {
        $versions = [];
        $home = null;
        foreach ($this->sources->pages($context) as $page) {
            if ($page->path === 'index.html' || ($this->markdown->pathOf($page) !== null && $page->isInSitemap())) {
                $html = $this->renderer->render($page, $context);
                $home = $page->path === 'index.html' ? $html : $home;
                if ($this->markdown->pathOf($page) !== null && $page->isInSitemap()) {
                    $versions[] = $this->markdown->render($page, $context, $html);
                }
            }
        }

        return $this->markdown->llmsTxt($home, $versions);
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
            'md' => 'text/markdown',
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
