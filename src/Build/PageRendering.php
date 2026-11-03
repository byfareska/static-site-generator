<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;

/**
 * Dispatched before each page is drawn: what remembers something for one request (the entries Reprise or Encore
 * printed already) is reset here, since a build draws every page in one process.
 */
final readonly class PageRendering
{
    public function __construct(
        public SitePage $page,
        public RenderContext $context,
    ) {
    }
}
