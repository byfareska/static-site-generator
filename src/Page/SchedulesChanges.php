<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

/**
 * A provider whose pages change by themselves at a moment: an article with a publication date in the future. The
 * panel tells that a publication is needed after it; static-site:publish-scheduled publishes then.
 */
interface SchedulesChanges
{
    /** The first moment after $after at which the pages change, or null. */
    public function nextChangeAfter(\DateTimeImmutable $after): ?\DateTimeImmutable;
}
