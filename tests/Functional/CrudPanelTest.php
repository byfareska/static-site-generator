<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\StaticSite\Integration\Crud\TextsController;
use Byfareska\StaticSite\Publication\PublicationStatus;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Tests\Functional\App\TestKernel;
use Byfareska\StaticSite\Text\Texts;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The panel of byfareska/crud with its files: the storage "public" (/uploads) published as uploads/.
 */
final class CrudPanelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $fs = new Filesystem();
        foreach (['builds', 'plain', 'app', 'uploads', 'test.db'] as $path) {
            $fs->remove(TestKernel::varDir() . '/' . $path);
        }
        $fs->dumpFile(TestKernel::varDir() . '/uploads/ab/cennik.pdf', 'PDF');
        $fs->dumpFile(TestKernel::varDir() . '/uploads/ab/hero.png', 'PNG');
        $fs->dumpFile(TestKernel::varDir() . '/uploads/ab/unused.png', 'UNUSED');
        self::bootKernel(['environment' => 'crud']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    public function testTheTextsAreFieldsOfCrud(): void
    {
        $client = self::createClient(['environment' => 'crud']);

        $client->request('GET', '/admin/teksty/home');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-controller="rich-text"', $html);
        self::assertStringContainsString('data-rich-text-editor-value="static-site"', $html);
        self::assertStringContainsString('data-rich-text-upload-url-value=', $html, 'The editor uploads into the storage the site publishes.');
        self::assertStringContainsString('&quot;static_site_mode&quot;:&quot;inline&quot;', $html);

        $client->request('GET', '/admin/teksty/gallery');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-controller="file-field"', $html);
        self::assertStringContainsString('accept="image/', $html);
    }

    public function testTheFilesOfTheTextsArePublishedAndKeptInTheIndexOfUsages(): void
    {
        $client = self::createClient(['environment' => 'crud']);
        $client->request('POST', '/admin/teksty/home', ['texts' => [
            't0' => 'Witaj',
            't1' => 'Zobacz <a href="/uploads/ab/cennik.pdf">cennik</a>',
        ]]);
        self::assertResponseRedirects('/admin/teksty/home', 303);

        $usages = self::service(FileUsages::class);
        $used = $usages->of(FileReference::parse('public://ab/cennik.pdf'));
        self::assertCount(1, $used);
        self::assertSame(TextsController::USAGE_RESOURCE, $used[0]->resource);
        self::assertSame('home.lead', $used[0]->id);

        self::service(Texts::class)->save(['gallery.image' => 'public://ab/hero.png']);
        $publisher = self::service(Publisher::class);
        $publication = $publisher->request(['plain'], 'test');
        $publisher->run($publication);
        self::service(EntityManagerInterface::class)->refresh($publication);
        self::assertSame(PublicationStatus::Done, $publication->status, $publication->log);

        $dir = TestKernel::varDir() . '/plain';
        self::assertSame('PDF', file_get_contents($dir . '/uploads/ab/cennik.pdf'));
        self::assertSame('PNG', file_get_contents($dir . '/uploads/ab/hero.png'));
        self::assertFileDoesNotExist($dir . '/uploads/ab/unused.png');
        self::assertStringContainsString('<a href="../uploads/ab/cennik.pdf">cennik</a>', (string) file_get_contents($dir . '/artykuly/pierwszy.html'));
        self::assertStringContainsString('<img src="../uploads/ab/hero.png" alt="Z tekstu">', (string) file_get_contents($dir . '/galeria/index.html'));
    }

    public function testAFileTheBrowserMadeUpIsRefused(): void
    {
        $client = self::createClient(['environment' => 'crud']);
        $client->request('POST', '/admin/teksty/gallery', ['texts' => ['t0' => json_encode([['ref' => 'public://ab/unused.png']])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('img/port.png', self::service(Texts::class)->raw('gallery.image'));
    }
}
