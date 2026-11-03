<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

/**
 * The texts of a part of the site, edited on one page of the panel.
 */
final readonly class TextGroup
{
    /** @param list<TextItem> $items */
    public function __construct(
        public string $key,
        public string $label,
        public array $items,
        public ?string $description = null,
        public ?string $icon = null,
    ) {
        if (preg_match('/^[a-z0-9_-]+$/', $key) !== 1) {
            throw new \InvalidArgumentException(sprintf('The key of a group of texts is [a-z0-9_-]: "%s".', $key));
        }
    }
}
