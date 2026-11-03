<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

final readonly class ContactReceived
{
    public function __construct(public int $submissionId)
    {
    }
}
