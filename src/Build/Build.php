<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

/**
 * A built site: a directory of files and their manifest.
 */
final readonly class Build
{
    /**
     * @param array<string, string> $files     path => sha256
     * @param list<string>          $endpoints the endpoints its pages call
     */
    public function __construct(
        public string $id,
        public string $directory,
        public array $files,
        public array $endpoints,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public function path(string $file): string
    {
        return $this->directory . '/' . $file;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'endpoints' => $this->endpoints,
            'files' => $this->files,
        ];
    }
}
