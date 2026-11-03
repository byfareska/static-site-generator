<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

/**
 * A word in braces that a text may contain, "{email}": replaced by its value when the text is drawn. In an HTML
 * text it is $html when given (a link), else the value escaped.
 */
final readonly class Placeholder
{
    public function __construct(
        public string $name,
        public string $value,
        public string $description = '',
        public ?string $html = null,
    ) {
    }
}
