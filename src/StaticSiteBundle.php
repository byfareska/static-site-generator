<?php

declare(strict_types=1);

namespace Byfareska\StaticSite;

use Byfareska\Crud\CrudBundle;
use Byfareska\StaticSite\DependencyInjection\CollectEndpointsPass;
use Byfareska\StaticSite\Endpoint\AsSiteEndpoint;
use Byfareska\StaticSite\Publication\PublicationRunner;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;

/**
 * Renders a site with Twig into static files and publishes them to targets through Flysystem. See README.md.
 */
final class StaticSiteBundle extends AbstractBundle
{
    protected string $extensionAlias = 'static_site';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('base_url')
                    ->info('The address of the published site, e.g. https://lk5.pl: canonical links, Open Graph, the sitemap.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('static_dirs')
                    ->info('Directories copied to the root of the site as they are (css/, js/, images, .htaccess).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('builds_dir')
                    ->info('Where the builds are kept (each publication has one; a rollback sends an older one).')
                    ->defaultValue('%kernel.project_dir%/var/static_site/builds')
                ->end()
                ->integerNode('keep_builds')
                    ->info('How many builds are kept (and releases of a "symlink" target); those on a target always.')
                    ->defaultValue(10)
                    ->min(1)
                ->end()
                ->booleanNode('sitemap')
                    ->info('Write sitemap.xml and robots.txt (unless a page or a file of the site is one).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('htaccess')
                    ->info('Put the redirects (and the rule of the endpoints) into .htaccess, for Apache.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('targets')
                    ->info('Where the site is published, by name.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->defaultNull()->end()
                            ->scalarNode('dsn')
                                ->info('local:///path, ftp://user:pass@host:21/dir?passive=1, ftps://..., sftp://...')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('service')->info('A FilesystemOperator service instead of a DSN.')->defaultNull()->end()
                            ->enumNode('strategy')
                                ->info('sync: send the differences; symlink (local only): releases/<id> and an atomic current link.')
                                ->values(['sync', 'symlink'])
                                ->defaultValue('sync')
                            ->end()
                            ->booleanNode('backend')
                                ->info('This application serves the endpoints at the address of this target (same server, same domain).')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('publish')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')
                            ->info('messenger: a worker runs it; terminate: after the response (no worker); cron: static-site:run-pending.')
                            ->values([PublicationRunner::MESSENGER, PublicationRunner::TERMINATE, PublicationRunner::CRON])
                            ->defaultNull()
                        ->end()
                        ->scalarNode('schedule_every')
                            ->info('With symfony/scheduler: how often a worker checks for scheduled content due (consume "scheduler_static_site").')
                            ->defaultValue('5 minutes')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('sync')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch')->info('Files sent between two saves of the state of a target.')->defaultValue(20)->min(1)->end()
                    ->end()
                ->end()
                ->arrayNode('endpoints')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prefix')->defaultValue('/api')->end()
                        ->scalarNode('script')->info('The path of the forms script in the site.')->defaultValue('_site/endpoints.js')->end()
                    ->end()
                ->end()
                ->arrayNode('submissions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('keep_days')->defaultValue(365)->min(1)->end()
                    ->end()
                ->end()
                ->arrayNode('panel')
                    ->info('The pages of the site in the panel of byfareska/crud, when it is installed.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('scope')->info('The panel (route scope) they go to; null = the default one of crud.')->defaultNull()->end()
                        ->scalarNode('section')->info('The section of the menu: publications, submissions.')->defaultValue('static_site.nav.section')->end()
                        ->scalarNode('texts_section')->info('The section of "Teksty"; null = "section".')->defaultNull()->end()
                        ->scalarNode('role')->info('Who may publish, edit the texts and see the submissions.')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('blocks')
                            ->info('The menu "Wstaw blok" of the editor: ready pieces of HTML.')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('title')->isRequired()->end()
                                    ->scalarNode('html')->isRequired()->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('content_css')->info('The stylesheet of the text in the editor: an asset of the application (build/site-editor.css) or an address.')->defaultNull()->end()
                        ->scalarNode('language')->info('A language of tinymce-i18n loaded by the application (e.g. "pl").')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CollectEndpointsPass());
        $container->registerAttributeForAutoconfiguration(
            AsSiteEndpoint::class,
            static function (ChildDefinition $definition, AsSiteEndpoint $endpoint, \Reflector $reflector): void {
                $method = $reflector instanceof \ReflectionMethod ? $reflector->getName() : '__invoke';
                $definition->addTag(AsSiteEndpoint::TAG, ['method' => $method] + $endpoint->toArray());
                if (!$definition->hasTag('controller.service_arguments')) {
                    $definition->addTag('controller.service_arguments');
                }
            },
        );
    }

    /**
     * @param array{
     *     base_url: string,
     *     static_dirs: list<string>,
     *     builds_dir: string,
     *     keep_builds: int,
     *     sitemap: bool,
     *     htaccess: bool,
     *     targets: array<string, array{label: string|null, dsn: string|null, service: string|null, strategy: string, backend: bool}>,
     *     publish: array{mode: string|null, schedule_every: string},
     *     sync: array{batch: int},
     *     endpoints: array{prefix: string, script: string},
     *     submissions: array{keep_days: int},
     *     panel: array{scope: string|null, section: string, texts_section: string|null, role: string|null},
     *     editor: array{blocks: list<array{title: string, html: string}>, content_css: string|null, language: string|null},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $hasMessenger = interface_exists(MessageBusInterface::class);
        $parameters = $container->parameters();
        $parameters->set('static_site.base_url', $config['base_url']);
        $parameters->set('static_site.static_dirs', $config['static_dirs']);
        $parameters->set('static_site.builds_dir', $config['builds_dir']);
        $parameters->set('static_site.keep_builds', $config['keep_builds']);
        $parameters->set('static_site.sitemap', $config['sitemap']);
        $parameters->set('static_site.htaccess', $config['htaccess']);
        $parameters->set('static_site.targets', $config['targets']);
        $parameters->set(
            'static_site.publish.mode',
            $config['publish']['mode'] ?? ($hasMessenger ? PublicationRunner::MESSENGER : PublicationRunner::TERMINATE),
        );
        $parameters->set('static_site.publish.schedule_every', $config['publish']['schedule_every']);
        $parameters->set('static_site.sync.batch', $config['sync']['batch']);
        $parameters->set('static_site.endpoints.prefix', '/' . trim($config['endpoints']['prefix'], '/'));
        $parameters->set('static_site.endpoints.script', ltrim($config['endpoints']['script'], '/'));
        $parameters->set('static_site.endpoints.script_source', $this->getPath() . '/resources/site/site-endpoints.js');
        $parameters->set('static_site.submissions.keep_days', $config['submissions']['keep_days']);
        $parameters->set('static_site.panel.scope', $config['panel']['scope']);
        $parameters->set('static_site.panel.section', $config['panel']['section']);
        $parameters->set('static_site.panel.texts_section', $config['panel']['texts_section'] ?? $config['panel']['section']);
        $parameters->set('static_site.panel.role', $config['panel']['role']);
        $parameters->set('static_site.editor.blocks', $config['editor']['blocks']);
        $parameters->set('static_site.editor.content_css', $config['editor']['content_css']);
        $parameters->set('static_site.editor.language', $config['editor']['language']);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $src = $this->getPath() . '/src';
        $services->load('Byfareska\\StaticSite\\', $src . '/')
            ->exclude([
                $src . '/StaticSiteBundle.php',
                $src . '/DependencyInjection/',
                $src . '/Entity/',
                $src . '/Messenger/',
                $src . '/Integration/',
                $src . '/Page/{RenderContext,RenderMode,SitePage}.php',
                $src . '/File/SiteFile.php',
                $src . '/Redirect/{Redirect,Htaccess,RedirectStub}.php',
                $src . '/Build/{Build,PageRendering,Sitemap}.php',
                $src . '/Target/{Target,SyncPlan,SyncResult}.php',
                $src . '/Publication/{PublicationStatus,TargetStatus}.php',
                $src . '/Content/{SiteContent,SiteHtml}.php',
                $src . '/Text/{TextType,TextItem,TextGroup,Placeholder,UnknownText}.php',
                $src . '/Endpoint/{AsSiteEndpoint,EndpointResult,SubmissionStatus}.php',
            ]);
        $services->get(Page\SitePaths::class)->arg('$baseUrl', '%static_site.base_url%');
        $services->get(Preview\PreviewController::class)->tag('controller.service_arguments');
        $services->get(Endpoint\TokenController::class)->tag('controller.service_arguments');

        if ($hasMessenger) {
            $services->load('Byfareska\\StaticSite\\Messenger\\', $src . '/Messenger/');
            if (class_exists(AsSchedule::class)) {
                $services->load('Byfareska\\StaticSite\\Integration\\Scheduler\\', $src . '/Integration/Scheduler/')
                    ->exclude([$src . '/Integration/Scheduler/PublishScheduled.php']);
            }
        }

        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];
        if (is_array($bundles) && isset($bundles['CrudBundle']) && class_exists(CrudBundle::class)) {
            $services->load('Byfareska\\StaticSite\\Integration\\Crud\\', $src . '/Integration/Crud/');
            $services->get(Integration\Crud\TextsController::class)->tag('controller.service_arguments');
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['StaticSite' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => $this->getPath() . '/src/Entity',
                'prefix' => 'Byfareska\\StaticSite\\Entity',
                'alias' => 'StaticSite',
            ]]]]);
        }

        $sanitizers = ['sanitizers' => [
            'static_site.content' => [
                'allow_safe_elements' => true,
                'allow_elements' => [
                    'a' => ['href', 'title', 'target', 'rel', 'class'],
                    'h2' => ['id'],
                    'h3' => ['id'],
                    'h4' => ['id'],
                    'p' => ['class'],
                    'strong' => ['class'],
                    'em' => ['class'],
                    // itemprop: the microdata of schema.org written in the text (<span itemprop="author">)
                    'span' => ['class', 'itemprop'],
                    'figure' => ['class'],
                    'figcaption' => [],
                    'blockquote' => ['cite'],
                    'cite' => ['itemprop'],
                    'abbr' => ['title'],
                    'time' => ['datetime'],
                    'data' => ['value'],
                    'mark' => [],
                    'img' => ['src', 'alt', 'width', 'height', 'loading'],
                    'table' => [],
                    'thead' => [],
                    'tbody' => [],
                    'tr' => [],
                    'th' => ['colspan', 'rowspan', 'scope'],
                    'td' => ['colspan', 'rowspan'],
                    'ol' => ['start'],
                    'ul' => ['class'],
                    'li' => [],
                ],
                'allow_relative_links' => true,
                'allow_relative_medias' => true,
                'allowed_link_schemes' => ['http', 'https', 'mailto', 'tel'],
                'max_input_length' => 500000,
            ],
            'static_site.inline' => [
                'allow_elements' => [
                    // class: a colour of the site's (<strong class="bad">)
                    'strong' => ['class'],
                    'b' => [],
                    'em' => ['class'],
                    'i' => [],
                    'br' => [],
                    'span' => ['class'],
                    'abbr' => ['title'],
                    'a' => ['href', 'title', 'target', 'rel', 'class'],
                    'small' => [],
                    'time' => ['datetime'],
                    'data' => ['value'],
                    'p' => [],
                ],
                'allow_relative_links' => true,
                'allowed_link_schemes' => ['http', 'https', 'mailto', 'tel'],
                'max_input_length' => 20000,
            ],
        ]];
        if ($builder->hasExtension('html_sanitizer')) {
            $builder->prependExtensionConfig('html_sanitizer', $sanitizers);
        } else {
            $builder->prependExtensionConfig('framework', ['html_sanitizer' => $sanitizers]);
        }
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
