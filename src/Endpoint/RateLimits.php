<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * The rate limits of #[AsSiteEndpoint(rateLimit: "5/10min")], by the address of the sender. Needs
 * symfony/rate-limiter.
 */
final class RateLimits
{
    /** @var array<string, RateLimiterFactory> */
    private array $factories = [];

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @return bool whether the request may go on */
    public function consume(string $endpoint, string $limit, string $key): bool
    {
        if (!class_exists(RateLimiterFactory::class)) {
            throw new \LogicException(sprintf(
                'The endpoint "%s" has a rate limit: composer require symfony/rate-limiter.',
                $endpoint,
            ));
        }
        [$count, $interval] = self::parse($limit);
        $this->factories[$endpoint] ??= new RateLimiterFactory([
            'id' => 'static_site_' . $endpoint,
            'policy' => 'sliding_window',
            'limit' => $count,
            'interval' => $interval,
        ], new CacheStorage($this->cache));

        return $this->factories[$endpoint]->create($key)->consume()->isAccepted();
    }

    /** @return array{int, string} "5/10min" => [5, "10 minutes"] */
    public static function parse(string $limit): array
    {
        if (preg_match('/^(\d+)\s*\/\s*(\d*)\s*(s|sec|second|seconds|min|minute|minutes|h|hour|hours|d|day|days)$/i', trim($limit), $m) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is no rate limit: "5/10min", "100/1h", "1000/1d".', $limit));
        }
        $unit = match (strtolower($m[3])[0]) {
            's' => 'seconds',
            'm' => 'minutes',
            'h' => 'hours',
            default => 'days',
        };

        return [(int) $m[1], sprintf('%d %s', $m[2] === '' ? 1 : (int) $m[2], $unit)];
    }
}
