<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

/**
 * One file of the site drawn by Twig: an HTML page, a feed, a .htaccess. The path is relative to the root of the
 * site, without a leading slash ("index.html", "artykuly/index.html", "artykuly/feed.xml").
 */
final readonly class SitePage
{
    /**
     * @param array<string, mixed>              $context    the variables of the template
     * @param (\Closure(): string)|null         $render     draws the page instead of the template
     * @param bool|null                         $inSitemap  null: every .html page is in the sitemap
     * @param string|false|null                 $markdown   with static_site.markdown: null = drawn from the HTML,
     *                                                      false = no Markdown version, a template = drawn by it
     */
    public function __construct(
        public string $path,
        public ?string $template = null,
        public array $context = [],
        public ?\Closure $render = null,
        public ?bool $inSitemap = null,
        public ?\DateTimeInterface $lastModified = null,
        public ?float $priority = null,
        public ?string $changeFrequency = null,
        public string|false|null $markdown = null,
    ) {
        if ($template === null && $render === null) {
            throw new \InvalidArgumentException(sprintf('The page "%s" needs a template or a render closure.', $path));
        }
        SitePaths::assertValid($path);
    }

    public function isHtml(): bool
    {
        return str_ends_with($this->path, '.html');
    }

    /** "x.md" for "x.html", "a/index.md" for "a/index.html"; null for a page without HTML or without Markdown. */
    public function markdownPath(): ?string
    {
        return $this->isHtml() && $this->markdown !== false ? substr($this->path, 0, -strlen('.html')) . '.md' : null;
    }

    public function isInSitemap(): bool
    {
        return $this->inSitemap ?? $this->isHtml();
    }
}
