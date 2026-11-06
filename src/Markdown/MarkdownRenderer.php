<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Markdown;

use Byfareska\StaticSite\Build\SiteRenderer;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use Dom\Element;
use Dom\HTMLDocument;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Markdown version of a page, for AI agents: the content of its drawn HTML (static_site.markdown.selector,
 * without what "exclude" names) with absolute links, or its own template (SitePage::$markdown), under a front matter
 * of the title, the address and the date.
 */
final readonly class MarkdownRenderer
{
    private HtmlConverter $converter;

    /** @param list<string> $exclude */
    public function __construct(
        private SiteRenderer $renderer,
        private SitePaths $paths,
        #[Autowire('%static_site.markdown.enabled%')]
        private bool $enabled = false,
        #[Autowire('%static_site.markdown.selector%')]
        private string $selector = 'main',
        #[Autowire('%static_site.markdown.exclude%')]
        private array $exclude = [],
        #[Autowire('%static_site.markdown.llms_txt%')]
        private bool $llmsTxt = true,
    ) {
        $this->converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'strip_placeholder_links' => true,
            'hard_break' => true,
        ]);
        $this->converter->getEnvironment()->addConverter(new TableConverter());
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function hasLlmsTxt(): bool
    {
        return $this->enabled && $this->llmsTxt;
    }

    /** The path of the Markdown version of the page, null for none. */
    public function pathOf(SitePage $page): ?string
    {
        return $this->enabled ? $page->markdownPath() : null;
    }

    /** $html of the page with <link rel="alternate" type="text/markdown"> to its Markdown version. */
    public function withAlternate(SitePage $page, string $html): string
    {
        $path = $this->pathOf($page);
        $at = stripos($html, '</head>');
        if ($path === null || $at === false) {
            return $html;
        }
        $href = $this->paths->within($page->path, fn (): string => $this->paths->relative($path));

        return substr_replace(
            $html,
            sprintf('<link rel="alternate" type="text/markdown" href="%s">', htmlspecialchars($href)),
            $at,
            0,
        );
    }

    /** The Markdown version of the page drawn as $html. */
    public function render(SitePage $page, RenderContext $context, string $html): MarkdownPage
    {
        $path = $page->markdownPath() ?? throw new \LogicException(sprintf('The page "%s" has no Markdown version.', $page->path));
        $url = $this->paths->absolute($page->path);
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        ['title' => $title, 'description' => $description] = self::summaryOf($document);

        $body = is_string($page->markdown)
            ? trim($this->renderer->renderTemplate($page, $context, $page->markdown))
            : $this->convert($document, $url);

        $front = ['---', 'title: ' . self::yaml($title)];
        if ($description !== '') {
            $front[] = 'description: ' . self::yaml($description);
        }
        $front[] = 'url: ' . $url;
        $front[] = 'updated: ' . ($page->lastModified ?? $context->now)->format('Y-m-d');
        $front[] = '---';
        $navigation = sprintf('[Strona główna](%s)', $this->paths->absolute(''));
        if ($this->hasLlmsTxt()) {
            $navigation .= sprintf(' · [Spis stron](%s)', $this->paths->absolute(LlmsTxt::PATH));
        }
        $content = implode("\n", $front) . "\n" . $navigation . "\n\n" . ($body === '' ? '' : $body . "\n");

        return new MarkdownPage($path, $this->paths->absolute($path), $title, $description, $content);
    }

    /**
     * llms.txt of these Markdown versions, named by the <title> of the home page (or the host of the site).
     *
     * @param list<MarkdownPage> $pages
     */
    public function llmsTxt(?string $homeHtml, array $pages): string
    {
        $home = $homeHtml === null ? ['title' => '', 'description' => ''] : self::summary($homeHtml);
        $name = $home['title'] !== '' ? $home['title'] : (string) parse_url($this->paths->baseUrl(), PHP_URL_HOST);

        return LlmsTxt::text($name, $home['description'], $pages);
    }

    /**
     * The <title> and the meta description of a page.
     *
     * @return array{title: string, description: string}
     */
    public static function summary(string $html): array
    {
        return self::summaryOf(HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8'));
    }

    /**
     * Whether a request of this Accept header gets the Markdown: it names text/markdown (not with q=0) and does not
     * begin with text/html, as browsers do. The same test as the rules of .htaccess and of nginx in README.md.
     */
    public static function wanted(?string $accept): bool
    {
        $accept = (string) $accept;

        return preg_match('~text/markdown~i', $accept) === 1
            && preg_match('~^\s*text/html~i', $accept) !== 1
            && preg_match('~text/markdown\s*;\s*q=0(\.0*)?\s*(,|$)~i', $accept) !== 1;
    }

    private function convert(HTMLDocument $document, string $url): string
    {
        $root = $document->querySelector($this->selector) ?? $document->body;
        if ($root === null) {
            return '';
        }
        if ($this->exclude !== []) {
            foreach ($root->querySelectorAll(implode(', ', $this->exclude)) as $element) {
                $element->remove();
            }
        }
        foreach ($root->querySelectorAll('a[href], img[src]') as $element) {
            $attribute = $element->localName === 'img' ? 'src' : 'href';
            $element->setAttribute($attribute, self::resolve((string) $element->getAttribute($attribute), $url));
        }
        $markdown = $this->converter->convert($this->innerHtml($document, $root));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $markdown));
    }

    private function innerHtml(HTMLDocument $document, Element $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHtml($child);
        }

        return $html;
    }

    /** @return array{title: string, description: string} */
    private static function summaryOf(HTMLDocument $document): array
    {
        $description = $document->querySelector('meta[name="description" i]')?->getAttribute('content');

        return [
            'title' => trim((string) preg_replace('/\s+/u', ' ', $document->title)),
            'description' => trim((string) preg_replace('/\s+/u', ' ', (string) $description)),
        ];
    }

    /** $href of a page at $base as an absolute address (RFC 3986, enough for the links the site writes). */
    public static function resolve(string $href, string $base): string
    {
        $href = trim($href);
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $href) === 1) {
            return $href;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        if (str_starts_with($href, '//')) {
            return $scheme . ':' . $href;
        }
        $origin = $scheme . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $basePath = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        if ($href === '' || $href[0] === '#' || $href[0] === '?') {
            return $origin . $basePath . $href;
        }
        $at = strcspn($href, '?#');
        $path = substr($href, 0, $at);
        $suffix = substr($href, $at);
        if ($path === '' || $path[0] !== '/') {
            $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
        }

        return $origin . self::withoutDots($path) . $suffix;
    }

    private static function withoutDots(string $path): string
    {
        $out = [];
        $segments = explode('/', $path);
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($segment === '..') {
                if (count($out) > 1) {
                    array_pop($out);
                }
                if ($i === $last) {
                    $out[] = '';
                }
            } elseif ($segment === '.') {
                if ($i === $last) {
                    $out[] = '';
                }
            } else {
                $out[] = $segment;
            }
        }
        $result = implode('/', $out);

        return str_starts_with($result, '/') ? $result : '/' . $result;
    }

    private static function yaml(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
