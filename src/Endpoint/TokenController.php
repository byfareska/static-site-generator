<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class TokenController
{
    public function __construct(private FormToken $tokens)
    {
    }

    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse(['token' => $this->tokens->issue()]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
