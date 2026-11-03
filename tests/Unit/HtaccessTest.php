<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Page\SitePaths;
use Byfareska\StaticSite\Redirect\Htaccess;
use Byfareska\StaticSite\Redirect\Redirect;
use PHPUnit\Framework\TestCase;

final class HtaccessTest extends TestCase
{
    public function testRedirectsAndEndpoints(): void
    {
        $block = Htaccess::block(
            [new Redirect('port-polska.html', 'artykuly/port-polska.html')],
            new SitePaths('https://lk5.pl'),
            '/api',
        );

        self::assertStringContainsString('RewriteRule ^port\-polska\.html$ https://lk5.pl/artykuly/port-polska.html [R=301,L,NE]', $block);
        self::assertStringContainsString('RewriteRule ^api(/.*)?$ index.php [QSA,L]', $block);
    }

    public function testTheBlockIsReplacedAndTheRestKept(): void
    {
        $own = "AddDefaultCharset UTF-8\n";
        $first = Htaccess::merge($own, Htaccess::block([new Redirect('a.html', 'b.html')], new SitePaths('https://x.pl'), null));
        $second = Htaccess::merge($first, Htaccess::block([], new SitePaths('https://x.pl'), null));

        self::assertStringContainsString($own, $second);
        self::assertStringNotContainsString('a\.html', $second);
        self::assertSame(1, substr_count($second, Htaccess::BEGIN));
    }
}
