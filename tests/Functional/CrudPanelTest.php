<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\StaticSite\Content\ContentChanges;
use Byfareska\StaticSite\Content\SiteSettings;
use Byfareska\StaticSite\Integration\Crud\TextsController;
use Byfareska\StaticSite\Publication\PublicationStatus;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Tests\Functional\App\TestKernel;
use Byfareska\StaticSite\Text\Texts;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The panel of byfareska/crud with its files: the storage "public" (/uploads) published as uploads/ - every file of
 * its library (as the panel says), or ("crud_used", the storage's own publish_all: false) only those the pages use.
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

    public function testEveryFileOfTheLibraryIsPublished(): void
    {
        self::bootKernel(['environment' => 'crud']);
        $this->addToTheLibrary();

        $log = $this->publish();

        $dir = TestKernel::varDir() . '/plain';
        self::assertSame('UNUSED', file_get_contents($dir . '/uploads/ab/unused.png'), 'A file no page uses.');
        self::assertFileDoesNotExist($dir . '/uploads/ab/fresh.png', 'Uploaded a moment ago, no record uses it: a form not saved.');
        self::assertFileDoesNotExist($dir . '/uploads/ab/gone.png');
        self::assertFileDoesNotExist($dir . '/uploads/ab/cennik.pdf', 'Not in the library.');
        self::assertStringContainsString('Pliki z biblioteki: 3 (używane przez strony: 2)', $log, 'media/ of the test resolver, and unused.png.');
        self::assertStringContainsString('Pominięto plik z biblioteki uploads/ab/gone.png', $log);
    }

    public function testAStorageOfItsOwnMindPublishesOnlyTheFilesThePagesUse(): void
    {
        self::bootKernel(['environment' => 'crud_used']);
        $this->addToTheLibrary();
        self::service(Texts::class)->save(['gallery.image' => 'public://ab/hero.png']);

        $log = $this->publish();

        $dir = TestKernel::varDir() . '/plain';
        self::assertSame('PNG', file_get_contents($dir . '/uploads/ab/hero.png'));
        self::assertFileDoesNotExist($dir . '/uploads/ab/unused.png');
        self::assertStringContainsString('Pliki z biblioteki: 3' . "\n", $log, 'media/ of the test resolver, and hero.png.');
        self::assertStringNotContainsString('gone.png', $log);
    }

    public function testThePanelTurnsThePublicationOfEveryFileOff(): void
    {
        $client = self::createClient(['environment' => 'crud']);
        $client->request('GET', '/admin/ustawienia-strony');
        self::assertResponseIsSuccessful();
        self::assertTrue($this->isTicked((string) $client->getResponse()->getContent()), 'On by default.');
        $before = self::service(ContentChanges::class)->lastChange();

        $client->request('POST', '/admin/ustawienia-strony', []);
        self::assertResponseRedirects('/admin/ustawienia-strony', 303);
        self::assertFalse(self::service(SiteSettings::class)->publishAllFiles());
        self::assertNotEquals($before, self::service(ContentChanges::class)->lastChange(), 'A change to publish.');
        $client->request('GET', '/admin/ustawienia-strony');
        self::assertFalse($this->isTicked((string) $client->getResponse()->getContent()));

        $this->addToTheLibrary();
        $this->publish();
        self::assertFileDoesNotExist(TestKernel::varDir() . '/plain/uploads/ab/unused.png');

        $client->request('POST', '/admin/ustawienia-strony', ['settings' => ['publishAllFiles' => '1']]);
        self::assertTrue(self::service(SiteSettings::class)->publishAllFiles());
    }

    public function testAStorageOfItsOwnMindHasNoSettings(): void
    {
        $client = self::createClient(['environment' => 'crud_used']);
        $client->request('GET', '/admin/ustawienia-strony');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAFileTheBrowserMadeUpIsRefused(): void
    {
        $client = self::createClient(['environment' => 'crud']);
        $client->request('POST', '/admin/teksty/gallery', ['texts' => ['t0' => json_encode([['ref' => 'public://ab/unused.png']])]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('img/port.png', self::service(Texts::class)->raw('gallery.image'));
    }

    /** unused.png uploaded long ago, fresh.png a moment ago, gone.png whose bytes are not in the storage. */
    private function addToTheLibrary(): void
    {
        (new Filesystem())->dumpFile(TestKernel::varDir() . '/uploads/ab/fresh.png', 'FRESH');
        $library = self::service(FileLibraries::class)->of('public');
        $old = new \DateTimeImmutable('-2 days');
        $library->register(new StoredFile(FileReference::parse('public://ab/unused.png'), 'unused.png', 'image/png', createdAt: $old));
        $library->register(new StoredFile(FileReference::parse('public://ab/gone.png'), 'gone.png', 'image/png', createdAt: $old));
        $library->register(new StoredFile(FileReference::parse('public://ab/fresh.png'), 'fresh.png', 'image/png'));
    }

    private function isTicked(string $html): bool
    {
        self::assertSame(1, preg_match('/<input[^>]*name="settings\[publishAllFiles\]"[^>]*>/', $html, $input), 'The checkbox.');

        return str_contains($input[0], 'checked');
    }

    /** Publishes to "plain"; its log. */
    private function publish(): string
    {
        $publisher = self::service(Publisher::class);
        $publication = $publisher->request(['plain'], 'test');
        $publisher->run($publication);
        self::service(EntityManagerInterface::class)->refresh($publication);
        self::assertSame(PublicationStatus::Done, $publication->status, $publication->log);

        return (string) $publication->log;
    }
}
