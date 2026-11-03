<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * config/routes/static_site.yaml:
 *
 *     static_site_endpoints:
 *         resource: .
 *         type: static_site_endpoints
 */
#[AutoconfigureTag('routing.loader')]
final class EndpointRouteLoader extends Loader
{
    public function __construct(private readonly SiteEndpoints $endpoints)
    {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        // Built again when an endpoint is added or changed (the router cache of dev would not see it otherwise).
        $routes->addResource(new ContainerParametersResource(['static_site.endpoints.list' => $this->endpoints->all()]));
        if ($this->endpoints->isEmpty()) {
            return $routes;
        }
        $routes->add('static_site_endpoint_token', new Route(
            $this->endpoints->path(SiteEndpoints::TOKEN),
            ['_controller' => TokenController::class, '_site_endpoint' => SiteEndpoints::TOKEN, '_stateless' => true],
            methods: ['GET'],
        ));
        foreach ($this->endpoints->all() as $name => $endpoint) {
            $routes->add('static_site_endpoint_' . str_replace('-', '_', $name), new Route(
                $this->endpoints->path($name),
                ['_controller' => $endpoint['controller'], '_site_endpoint' => $name, '_stateless' => true],
                methods: $endpoint['methods'],
            ));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'static_site_endpoints';
    }
}
