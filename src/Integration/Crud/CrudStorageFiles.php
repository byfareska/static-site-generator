<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUrls;
use Byfareska\StaticSite\File\SiteFile;
use Byfareska\StaticSite\File\SiteFileResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The files uploaded in the panel (byfareska_crud.files) as files of the site: a file of a storage of
 * static_site.files.storages is published in its directory ("public://ab/cd/x.jpg" - "uploads/ab/cd/x.jpg"), by its
 * reference or by the address the editor wrote ("/uploads/ab/cd/x.jpg"). Nothing without crud's files enabled.
 */
final readonly class CrudStorageFiles implements SiteFileResolver
{
    /**
     * @param array<string, string> $directories the directory of the site of each storage, by its name
     */
    public function __construct(
        #[Autowire('%static_site.files.storages%')]
        private array $directories,
        private ?FileStorages $storages = null,
        private ?FileUrls $urls = null,
    ) {
    }

    public function fromReference(string $value): ?SiteFile
    {
        if ($this->directories === [] || $this->storages === null) {
            return null;
        }
        $reference = FileReference::tryParse($value);
        if ($reference === null || $reference->isExternal()) {
            // "/uploads/ab/cd/x.jpg", or the address of a storage on a CDN.
            $reference = $this->urls?->referenceOf($value);
        }

        return $reference === null ? null : $this->fileOf($reference);
    }

    public function fromPath(string $path): ?SiteFile
    {
        if ($this->storages === null) {
            return null;
        }
        foreach ($this->directories as $storage => $directory) {
            if (!str_starts_with($path, $directory . '/')) {
                continue;
            }
            $reference = FileReference::tryParse($storage . '://' . substr($path, strlen($directory) + 1));
            if ($reference !== null && $this->storages->has($storage) && $this->storages->storage($storage)->exists($reference->path)) {
                return $this->fileOf($reference);
            }
        }

        return null;
    }

    /** The file of the site a file of a storage is; null when the site does not publish its storage. */
    public function fileOf(FileReference $reference): ?SiteFile
    {
        $directory = $this->directories[$reference->storage] ?? null;
        $storages = $this->storages;
        if ($directory === null || $storages === null) {
            return null;
        }
        if (!$storages->has($reference->storage)) {
            throw new \LogicException(sprintf(
                'static_site.files.storages names "%s", a storage byfareska_crud.files.storages does not have.',
                $reference->storage,
            ));
        }

        return SiteFile::stream(
            $directory . '/' . $reference->path,
            static fn (): mixed => $storages->storage($reference->storage)->readStream($reference->path),
        );
    }
}
