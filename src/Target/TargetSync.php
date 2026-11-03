<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

use Byfareska\StaticSite\Build\Build;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends the differences between a build and what a target holds (see SyncPlan).
 *
 * A file goes under a temporary name and is then moved into place, so a visitor never gets half a file. The state
 * is handed to $checkpoint every few files: a sync cut short (the time limit of a shared host, a dropped connection)
 * starts again where it stopped.
 */
final readonly class TargetSync
{
    public function __construct(
        private AdapterFactory $adapters,
        private SymlinkReleases $symlinks,
        #[Autowire('%static_site.sync.batch%')]
        private int $batch = 20,
    ) {
    }

    /**
     * @param array<string, string>                      $state      what the target holds now
     * @param \Closure(array<string, string>): void      $checkpoint gets the state as it changes
     * @param \Closure(string): void                     $log
     */
    public function sync(Target $target, Build $build, array $state, \Closure $checkpoint, \Closure $log): SyncResult
    {
        if ($target->strategy === Target::SYMLINK) {
            $this->symlinks->activate($target, $build, $log);
            $plan = SyncPlan::between($state, $build->files);
            $checkpoint($build->files);

            return new SyncResult(count($plan->uploads), count($plan->deletions), $plan->unchanged);
        }

        $fs = $this->adapters->create($target);
        $plan = SyncPlan::between($state, $build->files);
        $log(sprintf(
            '%s: do wysłania %d, do usunięcia %d, bez zmian %d',
            $target->name,
            count($plan->uploads),
            count($plan->deletions),
            $plan->unchanged,
        ));

        $done = 0;
        foreach ($plan->uploads as $path) {
            $this->upload($fs, $build->path($path), $path);
            $state[$path] = $build->files[$path];
            if (++$done % $this->batch === 0) {
                $checkpoint($state);
                $log(sprintf('%s: wysłano %d z %d', $target->name, $done, count($plan->uploads)));
            }
        }
        $checkpoint($state);

        foreach ($plan->deletions as $path) {
            try {
                if ($fs->fileExists($path)) {
                    $fs->delete($path);
                }
            } catch (FilesystemException $e) {
                $log(sprintf('%s: nie usunięto %s (%s)', $target->name, $path, $e->getMessage()));
                continue;
            }
            unset($state[$path]);
        }
        $this->removeEmptyDirectories($fs, $plan->deletions, $build->files);
        $checkpoint($state);

        return new SyncResult(count($plan->uploads), count($plan->deletions), $plan->unchanged);
    }

    private function upload(FilesystemOperator $fs, string $source, string $path): void
    {
        $stream = fopen($source, 'r');
        if ($stream === false) {
            throw new \RuntimeException(sprintf('Cannot read the built file "%s".', $source));
        }
        $temporary = (dirname($path) === '.' ? '' : dirname($path) . '/') . '.' . basename($path) . '.ssg-upload';
        try {
            $fs->writeStream($temporary, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        try {
            $fs->move($temporary, $path);
        } catch (FilesystemException) {
            // Some FTP servers do not rename over an existing file: delete it first.
            if ($fs->fileExists($path)) {
                $fs->delete($path);
            }
            $fs->move($temporary, $path);
        }
    }

    /**
     * The directories left empty by the deletions, deepest first; none that the build still has files in.
     *
     * @param list<string>          $deleted
     * @param array<string, string> $build
     */
    private function removeEmptyDirectories(FilesystemOperator $fs, array $deleted, array $build): void
    {
        $directories = [];
        foreach ($deleted as $path) {
            for ($dir = dirname($path); $dir !== '.' && $dir !== ''; $dir = dirname($dir)) {
                $directories[$dir] = substr_count($dir, '/');
            }
        }
        arsort($directories);
        foreach (array_keys($directories) as $dir) {
            $dir = (string) $dir;
            foreach (array_keys($build) as $file) {
                if (str_starts_with((string) $file, $dir . '/')) {
                    continue 2;
                }
            }
            try {
                $empty = true;
                foreach ($fs->listContents($dir, false) as $ignored) {
                    $empty = false;
                    break;
                }
                if ($empty) {
                    $fs->deleteDirectory($dir);
                }
            } catch (FilesystemException) {
                // Left as it is: an empty directory does no harm.
            }
        }
    }
}
