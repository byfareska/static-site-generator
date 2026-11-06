<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Paths between the files of the site. Links are written relative to the page being drawn, so the same HTML works on
 * the target, in the preview of the panel (under its prefix) and opened from a directory. With clean URLs the links
 * of pages lose ".html" ("artykuly/x.html" is "artykuly/x", "artykuly/index.html" is "artykuly/"); the files keep it
 * and the server maps the address to the file.
 */
final class SitePaths implements ResetInterface
{
    private string $current = 'index.html';

    public function __construct(
        private readonly string $baseUrl = '',
        private readonly bool $cleanUrls = false,
    ) {
    }

    public function cleanUrls(): bool
    {
        return $this->cleanUrls;
    }

    public function current(): string
    {
        return $this->current;
    }

    /**
     * Draws $fn with $path as the current page.
     *
     * @template T
     *
     * @param \Closure(): T $fn
     *
     * @return T
     */
    public function within(string $path, \Closure $fn): mixed
    {
        $previous = $this->current;
        $this->current = $path;
        try {
            return $fn();
        } finally {
            $this->current = $previous;
        }
    }

    /**
     * The link from the current page to $target, a path from the root of the site ("css/style.css",
     * "/artykuly/", "index.html#faq", "" for the home page).
     */
    public function relative(string $target): string
    {
        [$path, $suffix] = self::split($target);
        $path = $this->addressOf(ltrim($path, '/'));
        $from = self::directoryOf($this->current);
        $fromParts = $from === '' ? [] : explode('/', $from);
        $toParts = $path === '' ? [] : explode('/', $path);
        $isDirectory = $path === '' || str_ends_with($path, '/');
        if ($isDirectory && $toParts !== []) {
            array_pop($toParts); // the empty part after the trailing slash
        }
        $file = $isDirectory ? '' : (string) array_pop($toParts);

        $common = 0;
        while (isset($fromParts[$common], $toParts[$common]) && $fromParts[$common] === $toParts[$common]) {
            ++$common;
        }
        $up = array_fill(0, count($fromParts) - $common, '..');
        $down = array_slice($toParts, $common);
        $relative = implode('/', [...$up, ...$down]);
        if ($isDirectory) {
            $relative = $relative === '' ? './' : $relative . '/';
        } else {
            $relative = ($relative === '' ? '' : $relative . '/') . $file;
        }

        return $relative . $suffix;
    }

    /** The absolute address of a path of the site; "x/index.html" becomes "x/" (and "x/y.html" "x/y" with clean URLs). */
    public function absolute(string $target): string
    {
        [$path, $suffix] = self::split($target);
        $path = ltrim($path, '/');
        if ($path === 'index.html') {
            $path = '';
        } elseif (str_ends_with($path, '/index.html')) {
            $path = substr($path, 0, -strlen('index.html'));
        }

        return rtrim($this->baseUrl, '/') . '/' . $this->addressOf($path) . $suffix;
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /** The path part of the base address ("" for a site at the root of its domain). */
    public function basePath(): string
    {
        return rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
    }

    public function reset(): void
    {
        $this->current = 'index.html';
    }

    public static function assertValid(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_ends_with($path, '/')
            || preg_match('#(^|/)\.\.?(/|$)|\\\\|\x00#', $path) === 1) {
            throw new \InvalidArgumentException(sprintf(
                'A path of the site is a file relative to its root, without "..": "%s" is not.',
                $path,
            ));
        }
    }

    /** "artykuly/x.html" for "artykuly/" and "" (the home page) for "", so a directory is drawn by its index. */
    public static function fileOf(string $path): string
    {
        $path = ltrim($path, '/');

        return $path === '' || str_ends_with($path, '/') ? $path . 'index.html' : $path;
    }

    /** With clean URLs, the address of a file of the site: no "index.html", no ".html". */
    private function addressOf(string $path): string
    {
        if (!$this->cleanUrls || !str_ends_with($path, '.html')) {
            return $path;
        }
        if ($path === 'index.html' || str_ends_with($path, '/index.html')) {
            return substr($path, 0, -strlen('index.html'));
        }

        return substr($path, 0, -strlen('.html'));
    }

    private static function directoryOf(string $path): string
    {
        $dir = dirname($path);

        return $dir === '.' ? '' : $dir;
    }

    /** @return array{string, string} the path and what follows it (?query, #fragment) */
    private static function split(string $target): array
    {
        $at = strcspn($target, '?#');

        return [substr($target, 0, $at), substr($target, $at)];
    }
}
