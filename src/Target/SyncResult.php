<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

final readonly class SyncResult
{
    public function __construct(
        public int $uploaded,
        public int $deleted,
        public int $unchanged,
    ) {
    }
}
