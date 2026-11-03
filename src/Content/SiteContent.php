<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

/**
 * An entity the site is drawn from. Saving one marks the site as changed ("unpublished changes" in the panel) and
 * cleans its #[SiteHtml] properties.
 */
interface SiteContent
{
}
