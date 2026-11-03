<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

final class UnknownText extends \RuntimeException
{
    public static function key(string $key): self
    {
        return new self(sprintf('The text "%s" is in no TextCatalog.', $key));
    }
}
