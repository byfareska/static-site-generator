<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Byfareska\StaticSite\Page\RenderContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * The directories of static_site.static_dirs, copied to the root of the site as they are (dot files included, so a
 * .htaccess may live there).
 */
final readonly class StaticDirectoryFiles implements FileProvider
{
    /** @param list<string> $directories */
    public function __construct(
        #[Autowire('%static_site.static_dirs%')]
        private array $directories,
    ) {
    }

    public function files(RenderContext $context): iterable
    {
        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            $finder = (new Finder())->files()->in($directory)->ignoreDotFiles(false)->ignoreVCS(true)
                ->notName(['.DS_Store', 'Thumbs.db'])->sortByName();
            foreach ($finder as $file) {
                yield SiteFile::copy(str_replace('\\', '/', $file->getRelativePathname()), $file->getPathname());
            }
        }
    }
}
