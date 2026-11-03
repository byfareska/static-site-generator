<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Byfareska\StaticSite\Page\RenderContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Files of the site that are not drawn by Twig: uploads, the static directory, generated images.
 */
#[AutoconfigureTag(self::FILE_PROVIDER_TAG)]
interface FileProvider
{
    public const string FILE_PROVIDER_TAG = 'static_site.file_provider';

    /** @return iterable<SiteFile> */
    public function files(RenderContext $context): iterable;
}
