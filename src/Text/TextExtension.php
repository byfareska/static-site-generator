<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

use Byfareska\StaticSite\Page\SitePaths;
use Byfareska\StaticSite\Twig\SiteExtension;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * The texts of the catalog in the templates of the site:
 *
 * - txt('hero.title') - plain text (escaped by Twig);
 * - t_lines('hero.lead') - plain text, new lines as <br>;
 * - t_html('about.body') - HTML of the editor, its links relative to the page;
 * - t_inline('hero.lead') - HTML without the <p> around it, to put inside a heading or a paragraph;
 * - '...'|fill - the placeholders of any string, '...'|rich - of HTML, with links made relative.
 */
final readonly class TextExtension
{
    public function __construct(
        private Texts $texts,
        private SitePaths $paths,
    ) {
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFunction('txt')]
    public function text(string $key, array $vars = []): string
    {
        $item = $this->texts->item($key);

        return $item->type->isHtml()
            ? html_entity_decode(strip_tags($this->texts->get($key, $vars)), ENT_QUOTES | ENT_HTML5)
            : $this->texts->get($key, $vars);
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFunction('t_lines', isSafe: ['html'])]
    public function lines(string $key, array $vars = []): string
    {
        return nl2br(htmlspecialchars($this->text($key, $vars)), false);
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFunction('t_html', isSafe: ['html'])]
    public function html(string $key, array $vars = []): string
    {
        $item = $this->texts->item($key);
        $html = $item->type->isHtml()
            ? $this->texts->get($key, $vars)
            : nl2br(htmlspecialchars($this->texts->get($key, $vars)), false);

        return SiteExtension::relativeLinks($html, $this->paths);
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFunction('t_inline', isSafe: ['html'])]
    public function inline(string $key, array $vars = []): string
    {
        return self::unwrapParagraph($this->html($key, $vars));
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFilter('fill')]
    public function fill(?string $text, array $vars = []): string
    {
        return $this->texts->fill((string) $text, $vars);
    }

    /** @param array<string, scalar|null> $vars */
    #[AsTwigFilter('rich', isSafe: ['html'])]
    public function rich(?string $html, array $vars = []): string
    {
        return SiteExtension::relativeLinks($this->texts->fill((string) $html, $vars, true), $this->paths);
    }

    #[AsTwigFilter('unwrap_p', isSafe: ['html'])]
    public static function unwrapParagraph(?string $html): string
    {
        $html = trim((string) $html);
        if (preg_match('#^<p>(.*)</p>$#s', $html, $m) === 1 && !str_contains($m[1], '<p')) {
            return $m[1];
        }

        return $html;
    }
}
