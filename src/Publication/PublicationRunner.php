<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Messenger\PublishSite;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Starts a requested publication the way the deployment allows (static_site.publish.mode):
 *
 * - "messenger": a message for a worker (Docker, a VPS);
 * - "terminate": in the same PHP process, after the response is sent (a shared host without a worker);
 * - "cron": not at all, static-site:run-pending runs it (a cron job).
 */
final class PublicationRunner
{
    public const string MESSENGER = 'messenger';
    public const string TERMINATE = 'terminate';
    public const string CRON = 'cron';

    /** @var list<int> run on kernel.terminate / console.terminate */
    private array $deferred = [];

    public function __construct(
        #[Autowire('%static_site.publish.mode%')]
        private readonly string $mode,
        private readonly ?MessageBusInterface $bus = null,
    ) {
    }

    public function dispatch(Publication $publication): void
    {
        match ($this->mode) {
            self::MESSENGER => $this->bus === null
                ? throw new \LogicException('static_site.publish.mode "messenger" needs symfony/messenger.')
                : $this->bus->dispatch(new PublishSite((int) $publication->id)),
            self::TERMINATE => $this->deferred[] = (int) $publication->id,
            default => null,
        };
    }

    /** @return list<int> the publications to run now, emptied */
    public function takeDeferred(): array
    {
        $ids = $this->deferred;
        $this->deferred = [];

        return $ids;
    }

    public function mode(): string
    {
        return $this->mode;
    }
}
