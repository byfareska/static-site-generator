<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\StorageFileUrls;
use Byfareska\StaticSite\Integration\Crud\CrudStorageFiles;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CrudStorageFilesTest extends TestCase
{
    private string $dir;

    private FileStorages $storages;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ssg-crud-files-' . bin2hex(random_bytes(4));
        $public = new Filesystem(new LocalFilesystemAdapter($this->dir . '/public'));
        $public->write('ab/cd/x.jpg', 'JPEG');
        $private = new Filesystem(new LocalFilesystemAdapter($this->dir . '/private'));
        $private->write('ab/secret.pdf', 'PDF');
        $cdn = new Filesystem(new LocalFilesystemAdapter($this->dir . '/public'));
        $this->storages = new FileStorages(
            [
                'public' => ['url' => 'public', 'public_url' => '/uploads'],
                'cdn' => ['url' => 'public', 'public_url' => 'https://cdn.example.com/media'],
                'private' => ['url' => 'signed'],
            ],
            new ServiceLocator([
                'public' => static fn () => new FlysystemFileStorage($public),
                'cdn' => static fn () => new FlysystemFileStorage($cdn),
                'private' => static fn () => new FlysystemFileStorage($private),
            ]),
        );
    }

    protected function tearDown(): void
    {
        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->dir);
    }

    public function testAReferenceOfAPublishedStorageIsAFileOfTheSite(): void
    {
        $file = $this->files()->fromReference('public://ab/cd/x.jpg');

        self::assertNotNull($file);
        self::assertSame('uploads/ab/cd/x.jpg', $file->path);
        self::assertSame('JPEG', $file->read());
        self::assertSame(hash('sha256', 'JPEG'), $file->hash());
    }

    public function testTheAddressesTheEditorWritesAreFilesOfTheSite(): void
    {
        self::assertSame('uploads/ab/cd/x.jpg', $this->files()->fromReference('/uploads/ab/cd/x.jpg')?->path);
        $cdn = $this->files()->fromReference('https://cdn.example.com/media/ab/cd/x.jpg');
        self::assertSame('pliki/ab/cd/x.jpg', $cdn?->path);
        self::assertSame('JPEG', $cdn->read());
    }

    public function testWhatTheSiteDoesNotPublishIsNone(): void
    {
        $files = $this->files();

        self::assertNull($files->fromReference('private://ab/secret.pdf'), 'A storage outside static_site.files.storages.');
        self::assertNull($files->fromReference('/kontakt/'));
        self::assertNull($files->fromReference('https://example.org/x.jpg'));
        self::assertNull($files->fromReference('mailto:a@example.com'));
        self::assertNull((new CrudStorageFiles(['public' => 'uploads']))->fromReference('public://ab/cd/x.jpg'), 'Without the files of crud.');
    }

    public function testThePreviewFindsAFileByItsPath(): void
    {
        $files = $this->files();

        self::assertSame('JPEG', $files->fromPath('uploads/ab/cd/x.jpg')?->read());
        self::assertNull($files->fromPath('uploads/ab/cd/none.jpg'));
        self::assertNull($files->fromPath('uploads/../private/ab/secret.pdf'));
        self::assertNull($files->fromPath('css/style.css'));
    }

    public function testAStorageCrudDoesNotHaveIsAMistake(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"gone"');
        (new CrudStorageFiles(['gone' => 'old'], $this->storages))->fromReference('gone://a.jpg');
    }

    private function files(): CrudStorageFiles
    {
        $urls = new StorageFileUrls(
            $this->storages,
            new ServiceLocator([]),
            $this->createStub(UrlGeneratorInterface::class),
            new UriSigner('secret'),
            new RequestStack(),
        );

        return new CrudStorageFiles(['public' => 'uploads', 'cdn' => 'pliki'], $this->storages, $urls);
    }
}
