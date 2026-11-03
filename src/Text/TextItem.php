<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

final readonly class TextItem
{
    public function __construct(
        public string $key,
        public string $label,
        public TextType $type = TextType::Plain,
        public string $default = '',
        public ?string $help = null,
    ) {
    }
}
