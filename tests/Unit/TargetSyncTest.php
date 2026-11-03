<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Build\Build;
use Byfareska\StaticSite\Target\AdapterFactory;
use Byfareska\StaticSite\Target\SymlinkReleases;
use Byfareska\StaticSite\Target\Target;
use Byfareska\StaticSite\Target\TargetSync;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

final class TargetSyncTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ssg-sync-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir([$this->dir . '/build', $this->dir . '/target']);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testSendsTheDifferencesAndLeavesForeignFiles(): void
    {
        $target = new Target('local', 'Local', 'local://' . $this->dir . '/target');
        file_put_contents($this->dir . '/target/.htpasswd', 'user:hash');

        $state = $this->sync($target, ['index.html' => 'v1', 'old/page.html' => 'x', 'css/style.css' => 'c'], []);
        self::assertFileExists($this->dir . '/target/old/page.html');

        $state = $this->sync($target, ['index.html' => 'v2', 'css/style.css' => 'c'], $state);

        self::assertSame('v2', file_get_contents($this->dir . '/target/index.html'));
        self::assertFileDoesNotExist($this->dir . '/target/old/page.html');
        self::assertDirectoryDoesNotExist($this->dir . '/target/old');
        self::assertFileExists($this->dir . '/target/.htpasswd');
        self::assertSame(['css/style.css', 'index.html'], array_keys($state));
        self::assertSame([], glob($this->dir . '/target/{,*/}.*.ssg-upload', GLOB_BRACE));
    }

    public function testASyncCutShortGoesOnWhereItStopped(): void
    {
        $target = new Target('local', 'Local', 'local://' . $this->dir . '/target');
        $files = [];
        for ($i = 1; $i <= 7; ++$i) {
            $files[sprintf('assets/%d.png', $i)] = 'image ' . $i;
        }
        $build = $this->build($files);
        $saved = [];
        $sync = new TargetSync(new AdapterFactory($this->createStub(ContainerInterface::class)), new SymlinkReleases(5), batch: 2);
        // The connection drops after the fifth file.
        $sent = 0;
        $checkpoint = static function (array $state) use (&$saved, &$sent): void {
            $saved = $state;
            if (count($state) >= 5 && $sent++ === 0) {
                throw new \RuntimeException('connection lost');
            }
        };
        try {
            $sync->sync($target, $build, [], $checkpoint, static fn () => null);
            self::fail('The sync did not stop.');
        } catch (\RuntimeException) {
        }
        self::assertCount(6, $saved, 'The state saved has every file sent before the failure.');

        $uploaded = $sync->sync($target, $build, $saved, static function (array $state) use (&$saved): void {
            $saved = $state;
        }, static fn () => null)->uploaded;

        self::assertSame(1, $uploaded);
        self::assertCount(7, $saved);
    }

    /**
     * @param array<string, string> $files
     * @param array<string, string> $state
     *
     * @return array<string, string>
     */
    private function sync(Target $target, array $files, array $state): array
    {
        $sync = new TargetSync(new AdapterFactory($this->createStub(ContainerInterface::class)), new SymlinkReleases(5));
        $sync->sync($target, $this->build($files), $state, static function (array $new) use (&$state): void {
            $state = $new;
        }, static fn () => null);

        return $state;
    }

    /** @param array<string, string> $files */
    private function build(array $files): Build
    {
        $fs = new Filesystem();
        $dir = $this->dir . '/build/' . bin2hex(random_bytes(3));
        $hashes = [];
        foreach ($files as $path => $contents) {
            $fs->dumpFile($dir . '/' . $path, $contents);
            $hashes[$path] = hash('sha256', $contents);
        }

        return new Build('b', $dir, $hashes, [], new \DateTimeImmutable());
    }
}
