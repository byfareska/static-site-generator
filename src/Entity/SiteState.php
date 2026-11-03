<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row: when the content of the site last changed (any SiteContent saved). Compared with the deployments of the
 * targets to tell that there is something to publish.
 */
#[ORM\Entity]
#[ORM\Table(name: 'static_site_state')]
class SiteState
{
    #[ORM\Id, ORM\Column(name: 'id')]
    public int $id = 1;

    // Named here: ContentChanges writes the row with plain SQL, whatever the naming strategy of the application.
    #[ORM\Column(name: 'content_changed_at')]
    public \DateTimeImmutable $contentChangedAt;

    public function __construct()
    {
        $this->contentChangedAt = new \DateTimeImmutable();
    }
}
