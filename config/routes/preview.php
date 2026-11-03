<?php

declare(strict_types=1);

use Byfareska\StaticSite\Preview\PreviewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The preview of the site, under the prefix of the panel:
 *
 *     static_site_preview:
 *         resource: '@StaticSiteBundle/config/routes/preview.php'
 *         prefix: /admin/podglad
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('static_site_preview', '/{mode}/{path}')
        ->controller(PreviewController::class)
        ->requirements(['mode' => 'live|drafts', 'path' => '.*'])
        ->defaults(['path' => ''])
        ->methods(['GET']);
};
