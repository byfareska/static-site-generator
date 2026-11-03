<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Byfareska\StaticSite\Page\SitePaths;

/**
 * A file of the site copied as it is (a stylesheet, an image, a PDF) or written from a string.
 */
final readonly class SiteFile
{
    private function __construct(
        public string $path,
        public ?string $source,
        public ?string $contents,
    ) {
        SitePaths::assertValid($path);
    }

    public static function copy(string $path, string $source): self
    {
        return new self($path, $source, null);
    }

    public static function contents(string $path, string $contents): self
    {
        return new self($path, null, $contents);
    }

    public function read(): string
    {
        if ($this->contents !== null) {
            return $this->contents;
        }
        $data = @file_get_contents((string) $this->source);
        if ($data === false) {
            throw new \RuntimeException(sprintf('Cannot read "%s" for "%s".', $this->source, $this->path));
        }

        return $data;
    }

    public function hash(): string
    {
        return $this->contents !== null
            ? hash('sha256', $this->contents)
            : (string) hash_file('sha256', (string) $this->source);
    }
}
