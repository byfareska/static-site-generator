<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Who asked for a publication, as the history shows it. */
final readonly class CurrentUser
{
    public function __construct(private ?TokenStorageInterface $tokens = null)
    {
    }

    public function name(): ?string
    {
        return $this->tokens?->getToken()?->getUser()?->getUserIdentifier();
    }
}
