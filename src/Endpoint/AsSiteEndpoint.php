<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

/**
 * A controller (an invokable class or an action) the static site calls: a contact form, a sign-up, a counter.
 *
 * Its route is under static_site.endpoints.prefix ("/api" + $path), in the same application as the panel and the
 * generator. A site whose pages call one (site_endpoint()) is published only to targets with "backend: true".
 *
 * Protections (see EndpointGuard): "origin" (the Origin or Referer is the site), "honeypot" (the field _hp must stay
 * empty; a bot that fills it is answered "sent" and its submission kept as spam), "token" (a token of the site's
 * script, at least $minSeconds old: a form sent at once is a bot).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class AsSiteEndpoint
{
    public const string TAG = 'static_site.endpoint';

    public const string ORIGIN = 'origin';
    public const string HONEYPOT = 'honeypot';
    public const string TOKEN = 'token';

    /**
     * @param list<string> $methods
     * @param list<string> $protection
     * @param string|null  $rateLimit  "5/10min", "100/1h", "1000/1d": requests from one address
     * @param int|null     $cacheMaxAge seconds a GET answer may be kept by browsers and proxies
     */
    public function __construct(
        public string $name,
        public string $path,
        public array $methods = ['POST'],
        public array $protection = [self::ORIGIN, self::HONEYPOT, self::TOKEN],
        public ?string $rateLimit = null,
        public int $maxBodySize = 65536,
        public ?int $cacheMaxAge = null,
        public int $minSeconds = 3,
    ) {
        if (preg_match('/^[a-z0-9_-]+$/', $name) !== 1 || $name === SiteEndpoints::TOKEN) {
            throw new \InvalidArgumentException(sprintf('"%s" is no name for an endpoint: [a-z0-9_-], not "_token".', $name));
        }
        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(sprintf('The path of the endpoint "%s" starts with "/".', $name));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
