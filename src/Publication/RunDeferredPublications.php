<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Publication;

use Byfareska\StaticSite\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The "terminate" mode: the publication runs once the response is sent (PHP-FPM closes the connection first), with
 * no limit of time and whether the browser waits or not.
 */
final readonly class RunDeferredPublications
{
    public function __construct(
        private PublicationRunner $runner,
        private Publisher $publisher,
        private EntityManagerInterface $em,
    ) {
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    public function __invoke(): void
    {
        $ids = $this->runner->takeDeferred();
        if ($ids === []) {
            return;
        }
        set_time_limit(0);
        ignore_user_abort(true);
        foreach ($ids as $id) {
            $publication = $this->em->find(Publication::class, $id);
            if ($publication !== null) {
                $this->publisher->run($publication);
            }
        }
    }
}
