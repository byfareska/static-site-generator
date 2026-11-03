<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The builds kept on the disk of the application (var/static_site/builds/<id>/ and <id>.json): a publication sends
 * one of them, a rollback sends an older one again.
 */
final readonly class BuildStore
{
    private Filesystem $fs;

    public function __construct(
        #[Autowire('%static_site.builds_dir%')]
        private string $root,
    ) {
        $this->fs = new Filesystem();
    }

    public function directory(string $id): string
    {
        self::assertId($id);

        return $this->root . '/' . $id;
    }

    public function exists(string $id): bool
    {
        return is_dir($this->directory($id)) && is_file($this->manifestFile($id));
    }

    public function save(Build $build): void
    {
        $this->fs->dumpFile(
            $this->manifestFile($build->id),
            json_encode($build->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    public function get(string $id): Build
    {
        if (!$this->exists($id)) {
            throw new \RuntimeException(sprintf('The build "%s" is no longer kept.', $id));
        }
        /** @var array{id: string, createdAt: string, endpoints: list<string>, files: array<string, string>} $data */
        $data = json_decode((string) file_get_contents($this->manifestFile($id)), true, 512, JSON_THROW_ON_ERROR);

        return new Build(
            $data['id'],
            $this->directory($id),
            $data['files'],
            $data['endpoints'],
            new \DateTimeImmutable($data['createdAt']),
        );
    }

    /** @return array<string, string> path => sha256 of every file under the directory */
    public function scan(string $id): array
    {
        $files = [];
        $finder = (new Finder())->files()->in($this->directory($id))->ignoreDotFiles(false)->ignoreVCS(false)
            ->sortByName();
        foreach ($finder as $file) {
            $files[str_replace('\\', '/', $file->getRelativePathname())] = (string) hash_file('sha256', $file->getPathname());
        }

        return $files;
    }

    public function remove(string $id): void
    {
        $this->fs->remove([$this->directory($id), $this->manifestFile($id)]);
    }

    /** @return list<string> the ids kept, oldest first */
    public function ids(): array
    {
        if (!is_dir($this->root)) {
            return [];
        }
        $ids = [];
        foreach (glob($this->root . '/*.json') ?: [] as $file) {
            $ids[] = basename($file, '.json');
        }
        sort($ids);

        return $ids;
    }

    /**
     * Removes the oldest builds, keeping the $keep newest and those in $protected.
     *
     * @param list<string> $protected
     *
     * @return list<string> the ids removed
     */
    public function prune(int $keep, array $protected): array
    {
        $ids = $this->ids();
        $removed = [];
        foreach (array_slice($ids, 0, max(0, count($ids) - $keep)) as $id) {
            if (!in_array($id, $protected, true)) {
                $this->remove($id);
                $removed[] = $id;
            }
        }

        return $removed;
    }

    private function manifestFile(string $id): string
    {
        self::assertId($id);

        return $this->root . '/' . $id . '.json';
    }

    private static function assertId(string $id): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $id) !== 1 || str_contains($id, '..')) {
            throw new \InvalidArgumentException(sprintf('"%s" is not the id of a build.', $id));
        }
    }
}
