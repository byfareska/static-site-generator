<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The pages of a part of the site. Every service implementing it is asked, in the order of the tag's priority.
 */
#[AutoconfigureTag(self::PAGE_PROVIDER_TAG)]
interface PageProvider
{
    public const string PAGE_PROVIDER_TAG = 'static_site.page_provider';

    /** @return iterable<SitePage> */
    public function pages(RenderContext $context): iterable;
}
