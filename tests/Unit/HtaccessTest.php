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

    public function testWithoutCleanUrlsThereAreNoRulesOfThem(): void
    {
        $block = Htaccess::block([], new SitePaths('https://x.pl'), null, markdown: true);

        self::assertStringNotContainsString('RewriteEngine', $block);
        self::assertStringNotContainsString('.md', $block);
    }

    public function testCleanUrlsAndMarkdown(): void
    {
        $block = Htaccess::block(
            [new Redirect('stary.html', 'artykuly/x.html'), new Redirect('dzial/index.html', 'artykuly/'), new Redirect('a.php', 'index.html', false)],
            new SitePaths('https://lk5.pl/strona', cleanUrls: true),
            '/api',
            markdown: true,
        );

        self::assertStringContainsString("Options -MultiViews\nAddType text/markdown .md\nAddCharset UTF-8 .md\n", $block);
        self::assertStringContainsString('Header always merge Vary Accept', $block);
        self::assertStringContainsString('RewriteBase /strona/', $block);
        self::assertStringContainsString('RewriteRule ^stary(?:\.html)?$ https://lk5.pl/strona/artykuly/x [R=301,L,NE]', $block);
        self::assertStringContainsString('RewriteRule ^dzial/(?:index\.html)?$ https://lk5.pl/strona/artykuly/ [R=301,L,NE]', $block);
        self::assertStringContainsString('RewriteRule ^a\.php$ https://lk5.pl/strona/ [R=302,L,NE]', $block);
        self::assertSame(2, substr_count($block, 'RewriteCond %{HTTP_ACCEPT} text/markdown [NC]'));

        $order = [
            'RewriteRule ^api(/.*)?$ index.php',
            'RewriteRule ^stary(?:',
            'RewriteRule ^ /%1 [R=301,L,NE]',
            'RewriteRule ^(.*/)?$ $1index.md [END]',
            'RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]',
            'RewriteRule ^((?:.*/)?[^/.]+)$ $1.html [END]',
            '</IfModule>',
        ];
        $at = -1;
        foreach ($order as $rule) {
            $next = strpos($block, $rule, $at + 1);
            self::assertNotFalse($next, $rule);
            self::assertGreaterThan($at, $next, sprintf('"%s" comes later.', $rule));
            $at = $next;
        }
        self::assertStringEndsWith("RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]\n</IfModule>\n" . Htaccess::END . "\n", $block);
    }

    public function testCleanUrlsWithoutMarkdown(): void
    {
        $block = Htaccess::block([], new SitePaths('https://lk5.pl', cleanUrls: true), null);

        self::assertStringContainsString('RewriteBase /', $block);
        self::assertStringContainsString('$1.html [END]', $block);
        self::assertStringNotContainsString('.md', $block);
        self::assertStringNotContainsString('Vary', $block);
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
