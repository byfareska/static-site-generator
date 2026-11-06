<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Byfareska\StaticSite\Build\RenderState;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The files of the resolvers ({@see SiteFileResolver}): what a reference or an address is on the site, noted as used by
 * the page being drawn.
 */
final readonly class UploadedFiles
{
    /** @param iterable<SiteFileResolver> $resolvers */
    public function __construct(
        #[AutowireIterator(SiteFileResolver::FILE_RESOLVER_TAG)]
        private iterable $resolvers,
        private RenderState $state,
    ) {
    }

    /** The file a reference or an address is, noted as used; null when no resolver knows it. */
    public function use(string $value): ?SiteFile
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '#') || str_starts_with($value, 'data:')) {
            return null;
        }
        foreach ($this->resolvers as $resolver) {
            $file = $resolver->fromReference($value);
            if ($file !== null) {
                $this->state->useFile($file);

                return $file;
            }
        }

        return null;
    }

    public function atPath(string $path): ?SiteFile
    {
        foreach ($this->resolvers as $resolver) {
            $file = $resolver->fromPath($path);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
