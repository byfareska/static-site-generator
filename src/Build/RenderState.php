<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\File\SiteFile;
use Byfareska\StaticSite\Page\RenderContext;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What the drawing of the pages knows besides the current page: the render context, the files of the site (for the
 * versions of assets) and the endpoints the pages use.
 */
final class RenderState implements ResetInterface
{
    private ?RenderContext $context = null;

    /** @var array<string, SiteFile>|null */
    private ?array $files = null;

    /** @var array<string, string> */
    private array $hashes = [];

    /** @var array<string, true> */
    private array $endpoints = [];

    public function __construct(private readonly SiteSources $sources)
    {
    }

    public function begin(RenderContext $context): void
    {
        $this->reset();
        $this->context = $context;
    }

    public function context(): RenderContext
    {
        return $this->context ??= RenderContext::preview();
    }

    /** @return array<string, SiteFile> */
    public function files(): array
    {
        return $this->files ??= $this->sources->files($this->context());
    }

    public function fileHash(string $path): ?string
    {
        $file = $this->files()[$path] ?? null;

        return $file === null ? null : ($this->hashes[$path] ??= $file->hash());
    }

    public function useEndpoint(string $name): void
    {
        $this->endpoints[$name] = true;
    }

    /** @return list<string> */
    public function usedEndpoints(): array
    {
        return array_keys($this->endpoints);
    }

    public function reset(): void
    {
        $this->context = null;
        $this->files = null;
        $this->hashes = [];
        $this->endpoints = [];
    }
}
