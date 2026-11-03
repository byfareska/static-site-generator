<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

use Byfareska\StaticSite\Build\Build;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A local target served from <path>/current: each build is copied to <path>/releases/<id> and the link is switched
 * at once (a rename), so the server sees either the old site or the new one. The web server's document root is
 * <path>/current.
 */
final readonly class SymlinkReleases
{
    private Filesystem $fs;

    public function __construct(
        #[Autowire('%static_site.keep_builds%')]
        private int $keep,
    ) {
        $this->fs = new Filesystem();
    }

    /** @param \Closure(string): void $log */
    public function activate(Target $target, Build $build, \Closure $log): void
    {
        $root = (string) $target->localPath();
        $release = $root . '/releases/' . $build->id;
        if (!is_dir($release)) {
            $this->fs->mirror($build->directory, $release);
        }
        $temporary = $root . '/current.tmp-' . bin2hex(random_bytes(4));
        $this->fs->symlink('releases/' . $build->id, $temporary);
        if (!@rename($temporary, $root . '/current')) {
            $this->fs->remove($temporary);

            throw new \RuntimeException(sprintf('Could not switch %s/current to the release %s.', $root, $build->id));
        }
        $log(sprintf('%s: current -> releases/%s', $target->name, $build->id));
        $this->prune($root, $build->id);
    }

    private function prune(string $root, string $current): void
    {
        $releases = array_map('basename', glob($root . '/releases/*', GLOB_ONLYDIR) ?: []);
        sort($releases);
        foreach (array_slice($releases, 0, max(0, count($releases) - $this->keep)) as $name) {
            if ($name !== $current) {
                $this->fs->remove($root . '/releases/' . $name);
            }
        }
    }
}
