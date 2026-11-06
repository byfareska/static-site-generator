<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Markdown;

/**
 * llms.txt (llmstxt.org): the name of the site, what it is, and its pages in Markdown, for AI agents.
 */
final class LlmsTxt
{
    public const string PATH = 'llms.txt';

    /** @param list<MarkdownPage> $pages */
    public static function text(string $name, string $summary, array $pages): string
    {
        $text = '# ' . self::line($name) . "\n\n";
        if ($summary !== '') {
            $text .= '> ' . self::line($summary) . "\n\n";
        }
        $text .= "## Strony\n\n";
        foreach ($pages as $page) {
            $text .= sprintf('- [%s](%s)', self::label($page->title !== '' ? $page->title : $page->url), $page->url);
            $text .= $page->description === '' ? "\n" : ': ' . self::line($page->description) . "\n";
        }

        return $text;
    }

    private static function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function label(string $text): string
    {
        return addcslashes(self::line($text), '[]\\');
    }
}
