<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

/**
 * What a sync has to do to make a target hold a build, against what the target holds now (its state):
 * files to send, in an order that keeps the site whole while it runs, and files to delete afterwards.
 *
 * The order: assets first (an HTML page sent before its new stylesheet would look broken), then the pages, then the
 * files that point at the pages (.htaccess, sitemap, feeds). Only files the generator sent itself are ever deleted,
 * so a file put on the server by hand (.htpasswd) stays.
 */
final readonly class SyncPlan
{
    /**
     * @param list<string> $uploads
     * @param list<string> $deletions
     */
    public function __construct(
        public array $uploads,
        public array $deletions,
        public int $unchanged,
    ) {
    }

    /**
     * @param array<string, string> $state what the target holds: path => sha256 (as sent by the generator)
     * @param array<string, string> $build what it should hold
     */
    public static function between(array $state, array $build): self
    {
        $uploads = [];
        $unchanged = 0;
        foreach ($build as $path => $hash) {
            if (($state[$path] ?? null) === $hash) {
                ++$unchanged;
            } else {
                $uploads[] = (string) $path;
            }
        }
        usort($uploads, static fn (string $a, string $b): int => [self::stage($a), $a] <=> [self::stage($b), $b]);
        $deletions = array_values(array_map('strval', array_keys(array_diff_key($state, $build))));
        sort($deletions);

        return new self($uploads, $deletions, $unchanged);
    }

    public function isEmpty(): bool
    {
        return $this->uploads === [] && $this->deletions === [];
    }

    private static function stage(string $path): int
    {
        $name = basename($path);

        return match (true) {
            $name === '.htaccess', $name === 'robots.txt', str_ends_with($name, '.xml') => 2,
            str_ends_with($name, '.html') => 1,
            default => 0,
        };
    }
}
