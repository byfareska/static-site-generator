<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\StaticSite\Preview\PreviewController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The pages of the site in the panel - the texts and the preview - imported under the prefix of the panel:
 *
 *     static_site_panel:
 *         resource: .
 *         type: static_site_panel
 *         prefix: /admin
 */
#[AutoconfigureTag('routing.loader')]
final class PanelRouteLoader extends Loader
{
    public function __construct(private readonly Panel $panel)
    {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $defaults = ['_scope' => $this->panel->scope];
        $routes = new RouteCollection();
        $routes->add('static_site_texts', new Route('/teksty', $defaults + ['_controller' => TextsController::class . '::index'], methods: ['GET']));
        $routes->add('static_site_texts_group', new Route(
            '/teksty/{group}',
            $defaults + ['_controller' => TextsController::class . '::edit'],
            ['group' => '[a-z0-9_-]+'],
            methods: ['GET', 'POST'],
        ));
        $routes->add('static_site_preview', new Route(
            '/podglad/{mode}/{path}',
            $defaults + ['_controller' => PreviewController::class, 'path' => ''],
            ['mode' => 'live|drafts', 'path' => '.*'],
            methods: ['GET'],
        ));

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'static_site_panel';
    }
}
