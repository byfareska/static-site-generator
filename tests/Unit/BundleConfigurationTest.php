<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\StaticSiteBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BundleConfigurationTest extends TestCase
{
    public function testCleanUrlsAndMarkdownAreOff(): void
    {
        $config = $this->process(['base_url' => 'https://x.pl']);

        self::assertFalse($config['clean_urls']);
        self::assertFalse($config['markdown']['enabled']);
        self::assertSame('main', $config['markdown']['selector']);
        self::assertContains('[data-markdown-skip]', $config['markdown']['exclude']);
        self::assertTrue($config['markdown']['llms_txt']);
    }

    public function testMarkdownNeedsCleanUrls(): void
    {
        self::assertTrue($this->process(['base_url' => 'https://x.pl', 'clean_urls' => true, 'markdown' => ['enabled' => true]])['markdown']['enabled']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('clean_urls');
        $this->process(['base_url' => 'https://x.pl', 'markdown' => ['enabled' => true]]);
    }

    public function testFilesOfThePanel(): void
    {
        $files = $this->process(['base_url' => 'https://x.pl'])['files'];
        self::assertSame([], $files['storages']);
        self::assertTrue($files['publish_all'], 'Every file of the storages is published by default.');
        self::assertTrue($files['editor']);

        $files = $this->process(['base_url' => 'https://x.pl', 'files' => ['storages' => ['public' => '/uploads/'], 'editor' => ['storage' => 'public', 'accept' => ['images']]]])['files'];
        self::assertSame(['public' => ['dir' => 'uploads', 'publish_all' => null]], $files['storages']);
        self::assertSame(['storage' => 'public', 'accept' => ['images']], $files['editor']);
    }

    public function testEachStorageMayPublishAllItsFilesWhateverThePanelSays(): void
    {
        $container = $this->load(['base_url' => 'https://x.pl', 'files' => ['storages' => [
            'public' => 'uploads',
            'docs' => ['dir' => '/pliki/', 'publish_all' => false],
        ]]]);
        self::assertSame(['public' => 'uploads', 'docs' => 'pliki'], $container->getParameter('static_site.files.storages'));
        self::assertTrue($container->getParameter('static_site.files.publish_all'), 'The default of the panel.');
        self::assertSame(['public' => null, 'docs' => false], $container->getParameter('static_site.files.storages_publish_all'));

        $container = $this->load(['base_url' => 'https://x.pl', 'files' => ['publish_all' => false, 'storages' => ['public' => 'uploads']]]);
        self::assertFalse($container->getParameter('static_site.files.publish_all'));
    }

    public function testADirectoryOfFilesIsOneOfTheSite(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['base_url' => 'https://x.pl', 'files' => ['storages' => ['public' => '../www']]]);
    }

    public function testADirectoryOfTheLongFormIsOneOfTheSite(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['base_url' => 'https://x.pl', 'files' => ['storages' => ['public' => ['dir' => '../www']]]]);
    }

    /** @param array<string, mixed> $config */
    private function load(array $config): ContainerBuilder
    {
        $extension = (new StaticSiteBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension->load([$config], $container);

        return $container;
    }

    public function testTheEditorUploadsIntoAStorageTheSitePublishes(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('static_site.files.storages');
        $this->process(['base_url' => 'https://x.pl', 'files' => ['storages' => ['public' => 'uploads'], 'editor' => ['storage' => 'private']]]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new StaticSiteBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return (new Processor())->processConfiguration($configuration, [$config]);
    }
}
