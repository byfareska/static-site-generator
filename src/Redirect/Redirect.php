<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Redirect;

use Byfareska\StaticSite\Page\SitePaths;

/**
 * A moved address: "from" is the old path of the site, "to" a path of the site or an absolute address.
 */
final readonly class Redirect
{
    public function __construct(
        public string $from,
        public string $to,
        public bool $permanent = true,
    ) {
        SitePaths::assertValid(ltrim($from, '/'));
    }

    public function fromPath(): string
    {
        return ltrim($this->from, '/');
    }

    public function isExternal(): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $this->to) === 1;
    }
}
