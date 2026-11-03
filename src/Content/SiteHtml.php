<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

/**
 * A property of a SiteContent entity holding HTML from the editor: cleaned by the sanitizer when saved, its
 * headings given ids (anchors) when they have none.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class SiteHtml
{
    public const string CONTENT = 'static_site.content';
    public const string INLINE = 'static_site.inline';

    /** @param list<string> $headingIds the headings given an id, e.g. ['h2', 'h3'] */
    public function __construct(
        public string $sanitizer = self::CONTENT,
        public array $headingIds = [],
    ) {
    }
}
