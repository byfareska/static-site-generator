<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Files the site gets whole, used by a page or not: every file of a storage of the panel (static_site.files.publish_all).
 * The build writes them next to those the pages use ({@see SiteFileResolver}); one gone from the library is gone from
 * the target at the next publication.
 */
#[AutoconfigureTag(self::FILE_LIBRARY_TAG)]
interface SiteFileLibrary
{
    public const string FILE_LIBRARY_TAG = 'static_site.file_library';

    /** @return iterable<SiteFile> */
    public function allFiles(): iterable;
}
