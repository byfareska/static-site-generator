<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\StaticSite\Content\SiteSettings;
use Byfareska\StaticSite\File\SiteFileLibrary;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Every file of the storages published whole, used by a page or not - as the panel says
 * ({@see SiteSettings::publishAllFiles()}), or the storage's own static_site.files.storages.<name>.publish_all: those
 * of the library of the storage (the table "crud_file" of the library "doctrine"), or the storage listed when its
 * library cannot list them ("none"). A file uploaded less than byfareska_crud.files.prune_after ago that no record uses is left out: the upload
 * of a form not saved (yet).
 */
final readonly class CrudLibraryFiles implements SiteFileLibrary
{
    private const int PAGE = 200;

    /**
     * @param array<string, bool|null> $publishAll by storage: its files published whole (true), only those the pages
     *                                             use (false), or as the panel says (null)
     */
    public function __construct(
        private CrudStorageFiles $files,
        private SiteSettings $settings,
        #[Autowire('%static_site.files.storages_publish_all%')]
        private array $publishAll,
        private ContainerBagInterface $parameters,
        private ?FileStorages $storages = null,
        private ?FileLibraries $libraries = null,
        private ?FileUsages $usages = null,
    ) {
    }

    public function allFiles(): iterable
    {
        if ($this->storages === null || $this->libraries === null) {
            return;
        }
        $panel = null;
        $young = $this->youngerThan();
        foreach ($this->publishAll as $storage => $whole) {
            $panel ??= $whole === null ? $this->settings->publishAllFiles() : null;
            if (!($whole ?? $panel)) {
                continue;
            }
            /** @var array<string, \DateTimeImmutable|null> $created by reference */
            $created = [];
            foreach ($this->references($storage) as $reference => $createdAt) {
                $created[$reference] = $createdAt;
            }
            foreach ($this->abandoned($created, $young) as $reference) {
                unset($created[$reference]);
            }
            foreach (array_keys($created) as $reference) {
                $file = $this->files->fileOf(FileReference::parse((string) $reference));
                if ($file !== null) {
                    yield $file;
                }
            }
        }
    }

    /**
     * The files of a storage: by its library, else the storage listed.
     *
     * @return iterable<string, \DateTimeImmutable|null> when each was uploaded, by its reference
     */
    private function references(string $storage): iterable
    {
        \assert($this->storages !== null && $this->libraries !== null);
        if (!$this->storages->has($storage)) {
            throw new \LogicException(sprintf(
                'static_site.files.storages names "%s", a storage byfareska_crud.files.storages does not have.',
                $storage,
            ));
        }
        if ($this->libraries->can($storage, LibraryCapability::Browse)) {
            $library = $this->libraries->of($storage);
            $page = 1;
            do {
                $found = $library->browse(new FileQuery($storage, page: $page++, perPage: self::PAGE));
                foreach ($found->files as $file) {
                    yield (string) $file->reference => $file->createdAt;
                }
            } while ($found->more);

            return;
        }
        foreach ($this->storages->storage($storage)->list() as $entry) {
            yield (string) new FileReference($storage, $entry->path) => $entry->lastModified;
        }
    }

    /**
     * Those of the files uploaded after the moment that no record uses.
     *
     * @param array<string, \DateTimeImmutable|null> $created
     *
     * @return list<string>
     */
    private function abandoned(array $created, ?\DateTimeImmutable $after): array
    {
        if ($after === null || $this->usages === null || !$this->usages->tracks()) {
            return [];
        }
        $young = array_keys(array_filter(
            $created,
            static fn (?\DateTimeImmutable $at): bool => $at !== null && $at > $after,
        ));
        if ($young === []) {
            return [];
        }
        $used = array_map(strval(...), $this->usages->used(array_map(
            static fn (int|string $reference): FileReference => FileReference::parse((string) $reference),
            $young,
        )));

        return array_values(array_diff(array_map(strval(...), $young), $used));
    }

    /** Now less byfareska_crud.files.prune_after: a file uploaded after it may be of a form not saved. */
    private function youngerThan(): ?\DateTimeImmutable
    {
        $after = $this->parameters->has('byfareska_crud.files.prune_after')
            ? $this->parameters->get('byfareska_crud.files.prune_after')
            : null;

        return is_string($after) && $after !== ''
            ? (new \DateTimeImmutable())->sub(new \DateInterval($after))
            : null;
    }
}
