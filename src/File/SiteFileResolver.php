<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Files the site gets only when its pages use them: a file uploaded in the panel, shown by a template
 * (site_file(article.cover)) or by the HTML of the editor (<img src="/uploads/ab/cd/x.jpg">). The build writes the
 * files the pages used; the preview asks for one by its path.
 */
#[AutoconfigureTag(self::FILE_RESOLVER_TAG)]
interface SiteFileResolver
{
    public const string FILE_RESOLVER_TAG = 'static_site.file_resolver';

    /**
     * The file of the site a reference ("public://ab/cd/x.jpg") or an address of an uploaded file
     * ("/uploads/ab/cd/x.jpg") is; null when it is none of this resolver's.
     */
    public function fromReference(string $value): ?SiteFile;

    /** The file at a path of the site ("uploads/ab/cd/x.jpg"); null when it is none of this resolver's. */
    public function fromPath(string $path): ?SiteFile;
}
