<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\File;

use Byfareska\StaticSite\Page\SitePaths;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A file of the site copied as it is (a stylesheet, an image, a PDF), written from a string, or read from a stream (a
 * file of a storage of the panel).
 */
final readonly class SiteFile
{
    /** @param (\Closure(): resource)|null $open */
    private function __construct(
        public string $path,
        public ?string $source,
        public ?string $contents,
        private ?\Closure $open = null,
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

    /** @param \Closure(): resource $open gives a stream of the file each time it is called */
    public static function stream(string $path, \Closure $open): self
    {
        return new self($path, null, null, $open);
    }

    public function read(): string
    {
        if ($this->contents !== null) {
            return $this->contents;
        }
        if ($this->open !== null) {
            $stream = $this->openStream();
            try {
                $data = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            if ($data === false) {
                throw new \RuntimeException(sprintf('Cannot read the stream of "%s".', $this->path));
            }

            return $data;
        }
        $data = @file_get_contents((string) $this->source);
        if ($data === false) {
            throw new \RuntimeException(sprintf('Cannot read "%s" for "%s".', $this->source, $this->path));
        }

        return $data;
    }

    /** Writes the file to a path of the disk, a stream without holding it in memory. */
    public function dumpTo(string $target): void
    {
        $fs = new Filesystem();
        if ($this->open === null) {
            $fs->dumpFile($target, $this->read());

            return;
        }
        $stream = $this->openStream();
        try {
            $fs->dumpFile($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function hash(): string
    {
        if ($this->contents !== null) {
            return hash('sha256', $this->contents);
        }
        if ($this->open !== null) {
            $stream = $this->openStream();
            try {
                $context = hash_init('sha256');
                hash_update_stream($context, $stream);

                return hash_final($context);
            } finally {
                fclose($stream);
            }
        }

        return (string) hash_file('sha256', (string) $this->source);
    }

    /** @return resource */
    private function openStream(): mixed
    {
        $open = $this->open ?? throw new \LogicException(sprintf('"%s" is no stream.', $this->path));
        try {
            $stream = $open();
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Cannot read "%s": %s', $this->path, $e->getMessage()), previous: $e);
        }
        if (!is_resource($stream)) {
            throw new \RuntimeException(sprintf('Cannot read "%s": no stream.', $this->path));
        }

        return $stream;
    }
}
