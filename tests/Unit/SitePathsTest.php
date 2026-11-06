<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Page\SitePaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SitePathsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function links(): iterable
    {
        yield 'asset from the home page' => ['index.html', 'css/style.css', 'css/style.css'];
        yield 'asset from an article' => ['artykuly/x.html', 'css/style.css', '../css/style.css'];
        yield 'leading slash' => ['artykuly/x.html', '/css/style.css', '../css/style.css'];
        yield 'home from the home page' => ['index.html', '', './'];
        yield 'home from an article' => ['artykuly/x.html', '', '../'];
        yield 'anchor of the home page from an article' => ['artykuly/x.html', 'index.html#faq', '../index.html#faq'];
        yield 'directory from the home page' => ['index.html', 'artykuly/', 'artykuly/'];
        yield 'directory from itself' => ['artykuly/index.html', 'artykuly/', './'];
        yield 'sibling' => ['artykuly/index.html', 'artykuly/x.html', 'x.html'];
        yield 'deeper' => ['a/b/c.html', 'a/d/e.png', '../d/e.png'];
        yield 'query kept' => ['a/b.html', 'c.html?x=1#y', '../c.html?x=1#y'];
    }

    #[DataProvider('links')]
    public function testRelative(string $from, string $to, string $expected): void
    {
        $paths = new SitePaths('https://lk5.pl');

        self::assertSame($expected, $paths->within($from, static fn (): string => $paths->relative($to)));
    }

    public function testAbsolute(): void
    {
        $paths = new SitePaths('https://lk5.pl/');

        self::assertSame('https://lk5.pl/', $paths->absolute('index.html'));
        self::assertSame('https://lk5.pl/artykuly/', $paths->absolute('artykuly/index.html'));
        self::assertSame('https://lk5.pl/artykuly/x.html', $paths->absolute('/artykuly/x.html'));
        self::assertSame('https://lk5.pl/#faq', $paths->absolute('index.html#faq'));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function cleanLinks(): iterable
    {
        yield 'page from the home page' => ['index.html', 'artykuly/x.html', 'artykuly/x'];
        yield 'sibling' => ['artykuly/x.html', 'artykuly/y.html', 'y'];
        yield 'index of a directory' => ['index.html', 'artykuly/index.html', 'artykuly/'];
        yield 'index of its own directory' => ['artykuly/x.html', 'artykuly/index.html', './'];
        yield 'home with a fragment' => ['artykuly/x.html', 'index.html#faq', '../#faq'];
        yield 'home from the home page' => ['index.html', 'index.html', './'];
        yield 'query and fragment kept' => ['a/b.html', 'c.html?x=1#y', '../c?x=1#y'];
        yield 'not HTML' => ['artykuly/x.html', 'css/style.css', '../css/style.css'];
        yield 'Markdown' => ['artykuly/x.html', 'artykuly/x.md', 'x.md'];
        yield 'clean already' => ['index.html', 'artykuly/x', 'artykuly/x'];
    }

    #[DataProvider('cleanLinks')]
    public function testRelativeWithCleanUrls(string $from, string $to, string $expected): void
    {
        $paths = new SitePaths('https://lk5.pl', cleanUrls: true);

        self::assertSame($expected, $paths->within($from, static fn (): string => $paths->relative($to)));
    }

    public function testAbsoluteWithCleanUrls(): void
    {
        $paths = new SitePaths('https://lk5.pl/strona/', cleanUrls: true);

        self::assertSame('https://lk5.pl/strona/', $paths->absolute('index.html'));
        self::assertSame('https://lk5.pl/strona/artykuly/', $paths->absolute('artykuly/index.html'));
        self::assertSame('https://lk5.pl/strona/artykuly/x', $paths->absolute('/artykuly/x.html'));
        self::assertSame('https://lk5.pl/strona/artykuly/x?a=1#b', $paths->absolute('artykuly/x.html?a=1#b'));
        self::assertSame('https://lk5.pl/strona/#faq', $paths->absolute('index.html#faq'));
        self::assertSame('https://lk5.pl/strona/sitemap.xml', $paths->absolute('sitemap.xml'));
        self::assertSame('https://lk5.pl/strona/artykuly/x.md', $paths->absolute('artykuly/x.md'));
    }

    public function testBasePath(): void
    {
        self::assertSame('', (new SitePaths('https://lk5.pl'))->basePath());
        self::assertSame('/strona', (new SitePaths('https://example.com/strona/'))->basePath());
    }

    public function testFileOf(): void
    {
        self::assertSame('index.html', SitePaths::fileOf(''));
        self::assertSame('artykuly/index.html', SitePaths::fileOf('artykuly/'));
        self::assertSame('artykuly/x.html', SitePaths::fileOf('/artykuly/x.html'));
    }

    public function testInvalidPathsAreRefused(): void
    {
        foreach (['/a.html', '../a.html', 'a/../b.html', 'dir/', ''] as $path) {
            try {
                SitePaths::assertValid($path);
                self::fail(sprintf('"%s" was taken.', $path));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
