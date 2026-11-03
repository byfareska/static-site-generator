<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\DependencyInjection;

use Byfareska\StaticSite\Endpoint\AsSiteEndpoint;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The endpoints of #[AsSiteEndpoint], as the parameter static_site.endpoints.list (their routes are made of it).
 */
final class CollectEndpointsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $endpoints = [];
        foreach ($container->findTaggedServiceIds(AsSiteEndpoint::TAG) as $id => $tags) {
            foreach ($tags as $tag) {
                $name = (string) $tag['name'];
                if (isset($endpoints[$name])) {
                    throw new \LogicException(sprintf(
                        'Two endpoints are named "%s": %s and %s.',
                        $name,
                        $endpoints[$name]['controller'],
                        $id,
                    ));
                }
                $method = (string) ($tag['method'] ?? '__invoke');
                $endpoints[$name] = [
                    'controller' => $method === '__invoke' ? $id : $id . '::' . $method,
                    'name' => $name,
                    'path' => (string) $tag['path'],
                    'methods' => array_values((array) $tag['methods']),
                    'protection' => array_values((array) $tag['protection']),
                    'rateLimit' => $tag['rateLimit'] ?? null,
                    'maxBodySize' => (int) $tag['maxBodySize'],
                    'cacheMaxAge' => isset($tag['cacheMaxAge']) ? (int) $tag['cacheMaxAge'] : null,
                    'minSeconds' => (int) $tag['minSeconds'],
                ];
            }
        }
        ksort($endpoints);
        $container->setParameter('static_site.endpoints.list', $endpoints);
    }
}
