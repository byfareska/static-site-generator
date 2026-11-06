<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Byfareska\Crud\CrudBundle;
use Byfareska\StaticSite\StaticSiteBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\Chartjs\ChartjsBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application with a contact endpoint, a page that uses it and two local targets: "plain" (no backend) and
 * "app" (served by this application). The environment "markdown" has clean URLs and the Markdown versions; "crud"
 * the panel of byfareska/crud with its files (the storage "public", published as uploads/).
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        if ($this->environment === 'crud') {
            yield new TwigComponentBundle();
            yield new LiveComponentBundle();
            yield new StimulusBundle();
            yield new ChartjsBundle();
            yield new CrudBundle();
        }
        yield new StaticSiteBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::varDir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return self::varDir() . '/log';
    }

    public static function varDir(): string
    {
        return sys_get_temp_dir() . '/static-site-bundle-tests';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $var = self::varDir();
        $container->extension('framework', [
            'secret' => 'test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'validation' => true,
            'serializer' => true,
            'property_access' => true,
            'messenger' => [
                'transports' => ['async' => 'in-memory://'],
                'routing' => [
                    'Byfareska\StaticSite\Messenger\PublishSite' => 'async',
                    ContactReceived::class => 'async',
                ],
            ],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/templates']);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///' . $var . '/test.db'],
            'orm' => [],
        ]);
        $files = [];
        if ($this->environment === 'crud') {
            $container->extension('framework', ['form' => ['csrf_protection' => false]]);
            $container->extension('twig_component', ['defaults' => ['App\\Twig\\Components\\' => 'components/'], 'anonymous_template_directory' => 'components/']);
            $container->extension('byfareska_crud', [
                'assets' => 'none',
                'scopes' => ['admin' => []],
                'files' => ['storages' => ['public' => ['service' => 'test.public_storage', 'public_url' => '/uploads']]],
            ]);
            $files = ['files' => ['storages' => ['public' => 'uploads']], 'panel' => ['scope' => 'admin']];
        }
        $markdown = $this->environment === 'markdown' ? [
            'clean_urls' => true,
            'markdown' => ['enabled' => true],
        ] : [];
        $container->extension('static_site', $markdown + $files + [
            'base_url' => 'https://example.com',
            'static_dirs' => [__DIR__ . '/static'],
            'builds_dir' => $var . '/builds',
            'keep_builds' => 3,
            'publish' => ['mode' => 'cron'],
            'targets' => [
                'plain' => ['dsn' => 'local://' . $var . '/plain', 'label' => 'Bez backendu'],
                'app' => ['dsn' => 'local://' . $var . '/app', 'backend' => true],
            ],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->set('logger', \Psr\Log\NullLogger::class);
        $services->set('test.public_storage', Filesystem::class)
            ->args([inline_service(LocalFilesystemAdapter::class)->args([$var . '/uploads'])])
            ->public();
        $services->load(__NAMESPACE__ . '\\', __DIR__ . '/')->exclude([__DIR__ . '/{TestKernel,ContactRequest,ContactReceived}.php', __DIR__ . '/{config,templates,static}/']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'static_site_endpoints');
        $routes->import(dirname(__DIR__, 3) . '/config/routes/preview.php')->prefix('/admin/podglad');
        if ($this->environment === 'crud') {
            $routes->import('admin', 'byfareska_crud')->prefix('/admin');
            $routes->import('.', 'static_site_panel')->prefix('/admin');
        }
    }
}
