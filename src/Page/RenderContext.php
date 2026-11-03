<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

/**
 * What the pages are drawn for: a build or a preview, with drafts or without, at a moment ("now" decides which
 * scheduled content is out already).
 */
final readonly class RenderContext
{
    public function __construct(
        public RenderMode $mode = RenderMode::Build,
        public bool $includeDrafts = false,
        public \DateTimeImmutable $now = new \DateTimeImmutable(),
    ) {
    }

    public static function build(?\DateTimeImmutable $now = null): self
    {
        return new self(RenderMode::Build, false, $now ?? new \DateTimeImmutable());
    }

    public static function preview(bool $includeDrafts = false): self
    {
        return new self(RenderMode::Preview, $includeDrafts);
    }

    public function isPreview(): bool
    {
        return $this->mode === RenderMode::Preview;
    }
}
