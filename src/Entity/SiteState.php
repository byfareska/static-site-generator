<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row: when the content of the site last changed (any SiteContent saved), compared with the deployments of the
 * targets to tell that there is something to publish; and the settings of the site made in the panel.
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

    /** Every file of the libraries published, used or not; null = static_site.files.publish_all. */
    #[ORM\Column(name: 'publish_all_files', nullable: true)]
    public ?bool $publishAllFiles = null;

    public function __construct()
    {
        $this->contentChangedAt = new \DateTimeImmutable();
    }
}
