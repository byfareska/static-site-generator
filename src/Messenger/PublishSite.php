<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Messenger;

/** Route it to a transport with a worker: framework.messenger.routing. */
final readonly class PublishSite
{
    public function __construct(public int $publicationId)
    {
    }
}
