<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The endpoints of the application, collected from #[AsSiteEndpoint].
 */
final readonly class SiteEndpoints
{
    /** The built-in endpoint giving the tokens of the forms. */
    public const string TOKEN = '_token';

    /**
     * @param array<string, array{controller: string, name: string, path: string, methods: list<string>, protection: list<string>, rateLimit: string|null, maxBodySize: int, cacheMaxAge: int|null, minSeconds: int}> $endpoints
     */
    public function __construct(
        #[Autowire('%static_site.endpoints.list%')]
        private array $endpoints,
        #[Autowire('%static_site.endpoints.prefix%')]
        private string $prefix,
    ) {
    }

    /** @return array<string, array{controller: string, name: string, path: string, methods: list<string>, protection: list<string>, rateLimit: string|null, maxBodySize: int, cacheMaxAge: int|null, minSeconds: int}> */
    public function all(): array
    {
        return $this->endpoints;
    }

    public function has(string $name): bool
    {
        return isset($this->endpoints[$name]);
    }

    public function isEmpty(): bool
    {
        return $this->endpoints === [];
    }

    /** @return array{controller: string, name: string, path: string, methods: list<string>, protection: list<string>, rateLimit: string|null, maxBodySize: int, cacheMaxAge: int|null, minSeconds: int} */
    public function get(string $name): array
    {
        return $this->endpoints[$name] ?? throw new \InvalidArgumentException(sprintf(
            'There is no endpoint "%s" (#[AsSiteEndpoint]). The endpoints: %s.',
            $name,
            implode(', ', array_keys($this->endpoints)) ?: 'none',
        ));
    }

    /** The path from the root of the domain: "/api/kontakt". */
    public function path(string $name): string
    {
        $prefix = rtrim($this->prefix, '/');

        return $name === self::TOKEN ? $prefix . '/_token' : $prefix . $this->get($name)['path'];
    }

    public function prefix(): string
    {
        return rtrim($this->prefix, '/');
    }
}
