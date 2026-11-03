<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Entity;

use Byfareska\StaticSite\Content\SiteContent;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A text of the site changed in the panel. Its label, type and default are in the TextCatalog.
 */
#[ORM\Entity]
#[ORM\Table(name: 'static_site_text')]
class SiteText implements SiteContent
{
    #[ORM\Id, ORM\Column(name: 'text_key', length: 120)]
    public string $key;

    #[ORM\Column(type: Types::TEXT)]
    public string $value;

    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;

    public function __construct(string $key, string $value)
    {
        $this->key = $key;
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function change(string $value): void
    {
        if ($value !== $this->value) {
            $this->value = $value;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
