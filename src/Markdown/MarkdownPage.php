<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Markdown;

/**
 * The Markdown version of a page: the file and what llms.txt says of it.
 */
final readonly class MarkdownPage
{
    public function __construct(
        public string $path,
        public string $url,
        public string $title,
        public string $description,
        public string $content,
    ) {
    }
}
