<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Byfareska\StaticSite\File\SiteFile;
use Byfareska\StaticSite\File\SiteFileResolver;

/**
 * Files "uploaded in the panel": test://<name>, at the address /media/<name>, published as media/<name>.
 */
final class Uploads implements SiteFileResolver
{
    public const array FILES = ['hero.png' => 'HERO', 'logo.png' => 'LOGO', 'unused.png' => 'UNUSED'];

    public function fromReference(string $value): ?SiteFile
    {
        $name = match (true) {
            str_starts_with($value, 'test://') => substr($value, strlen('test://')),
            str_starts_with($value, '/media/') => substr($value, strlen('/media/')),
            default => null,
        };

        return $name === null ? null : $this->file($name);
    }

    public function fromPath(string $path): ?SiteFile
    {
        return str_starts_with($path, 'media/') ? $this->file(substr($path, strlen('media/'))) : null;
    }

    private function file(string $name): ?SiteFile
    {
        $data = self::FILES[$name] ?? null;

        return $data === null ? null : SiteFile::stream('media/' . $name, static function () use ($data) {
            $stream = fopen('php://memory', 'r+');
            assert(is_resource($stream));
            fwrite($stream, $data);
            rewind($stream);

            return $stream;
        });
    }
}
