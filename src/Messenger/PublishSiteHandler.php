<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Messenger;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Publication\Publisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PublishSiteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private Publisher $publisher,
    ) {
    }

    public function __invoke(PublishSite $message): void
    {
        $publication = $this->em->find(Publication::class, $message->publicationId);
        if ($publication !== null) {
            // A worker runs for long: the content read now, not what Doctrine remembers from before.
            $this->em->clear();
            $publication = $this->em->find(Publication::class, $message->publicationId);
            if ($publication !== null) {
                $this->publisher->run($publication);
            }
        }
    }
}
