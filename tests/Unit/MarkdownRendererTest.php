<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Build\SiteRenderer;
use Byfareska\StaticSite\Markdown\LlmsTxt;
use Byfareska\StaticSite\Markdown\MarkdownPage;
use Byfareska\StaticSite\Markdown\MarkdownRenderer;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class MarkdownRendererTest extends TestCase
{
    private const string HTML = <<<'HTML'
        <!doctype html>
        <html lang="pl"><head><title>Port  &amp; morze</title><meta name="description" content="O porcie
        w Gdyni"></head>
        <body><header><a href="../">Logo</a></header><nav><a href="x">Menu</a></nav>
        <main>
            <h1>Port</h1>
            <p>Zobacz <a href="sasiedni">sąsiedni</a>, <a href="../">start</a>, <a href="../#faq">FAQ</a>,
            <a href="/kontakt/">kontakt</a>, <a href="mailto:a@b.pl">pocztę</a> i <a href="https://other.pl/a">inny</a>.</p>
            <img src="../img/port.png" alt="Port">
            <form><input name="q"></form>
            <aside data-markdown-skip>Reklama</aside>
            <script>alert(1)</script>
            <table><thead><tr><th>Statek</th><th>Długość</th></tr></thead><tbody><tr><td>Dar</td><td>108 m</td></tr></tbody></table>
        </main>
        <footer>Stopka</footer></body></html>
        HTML;

    public function testTheContentOfThePageWithAbsoluteLinks(): void
    {
        $page = new SitePage('artykuly/port.html', 'x', lastModified: new \DateTimeImmutable('2026-11-01'));

        $markdown = $this->renderer()->render($page, RenderContext::build(), self::HTML);

        self::assertSame('artykuly/port.md', $markdown->path);
        self::assertSame('https://example.com/artykuly/port.md', $markdown->url);
        self::assertSame('Port & morze', $markdown->title);
        self::assertSame('O porcie w Gdyni', $markdown->description);
        self::assertStringStartsWith(<<<'MD'
            ---
            title: "Port & morze"
            description: "O porcie w Gdyni"
            url: https://example.com/artykuly/port
            updated: 2026-11-01
            ---
            [Strona główna](https://example.com/) · [Spis stron](https://example.com/llms.txt)

            # Port

            MD, $markdown->content);
        foreach ([
            '[sąsiedni](https://example.com/artykuly/sasiedni)',
            '[start](https://example.com/)',
            '[FAQ](https://example.com/#faq)',
            '[kontakt](https://example.com/kontakt/)',
            '[pocztę](mailto:a@b.pl)',
            '[inny](https://other.pl/a)',
            '![Port](https://example.com/img/port.png)',
            '| Dar | 108 m |',
        ] as $expected) {
            self::assertStringContainsString($expected, $markdown->content);
        }
        foreach (['Logo', 'Menu', 'Stopka', 'Reklama', 'alert', '<'] as $left) {
            self::assertStringNotContainsString($left, $markdown->content);
        }
        self::assertStringEndsWith("\n", $markdown->content);
        self::assertStringNotContainsString("\n\n\n", $markdown->content);
    }

    public function testTheBodyWithoutTheSelector(): void
    {
        $html = '<html><head><title>Bez main</title></head><body><nav>Menu</nav><h2>Tytuł</h2><p>Treść <strong>ważna</strong></p></body></html>';

        $markdown = $this->renderer()->render(new SitePage('index.html', 'x'), RenderContext::build(), $html);

        self::assertStringContainsString("## Tytuł\n\nTreść **ważna**\n", $markdown->content);
        self::assertStringNotContainsString('Menu', $markdown->content);
        self::assertStringContainsString("url: https://example.com/\n", $markdown->content);
        self::assertSame('index.md', $markdown->path);
    }

    public function testItsOwnTemplate(): void
    {
        $page = new SitePage('artykuly/port.html', 'x', ['name' => 'Dar Pomorza'], markdown: 'port.md.twig');

        $markdown = $this->renderer()->render($page, RenderContext::build(), self::HTML);

        self::assertStringContainsString("---\n[Strona główna](https://example.com/)", $markdown->content);
        self::assertStringEndsWith("\n# Dar Pomorza\n\nNa stronie artykuly/port.html\n", $markdown->content);
    }

    public function testAPageWithoutMarkdown(): void
    {
        $renderer = $this->renderer();
        $page = new SitePage('artykuly/port.html', 'x', markdown: false);

        self::assertNull($renderer->pathOf($page));
        self::assertSame(self::HTML, $renderer->withAlternate($page, self::HTML));
        self::assertNull($renderer->pathOf(new SitePage('feed.xml', 'x')));
        self::assertNull($this->renderer(enabled: false)->pathOf(new SitePage('a.html', 'x')));
    }

    public function testTheAlternateLink(): void
    {
        $renderer = $this->renderer();

        self::assertStringContainsString(
            '<link rel="alternate" type="text/markdown" href="port.md"></head>',
            $renderer->withAlternate(new SitePage('artykuly/port.html', 'x'), self::HTML),
        );
        self::assertStringContainsString(
            'href="index.md"></head>',
            $renderer->withAlternate(new SitePage('index.html', 'x'), self::HTML),
        );
    }

    /** @return iterable<string, array{string, bool}> */
    public static function accepts(): iterable
    {
        yield 'only Markdown' => ['text/markdown', true];
        yield 'an agent' => ['text/markdown, text/html;q=0.9, */*;q=0.8', true];
        yield 'a browser' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false];
        yield 'HTML first' => ['text/html, text/markdown', false];
        yield 'refused' => ['text/markdown;q=0, */*', false];
        yield 'refused last' => ['*/*, text/markdown; q=0.0', false];
        yield 'a low q' => ['text/markdown;q=0.5, */*', true];
        yield 'none' => ['', false];
    }

    #[DataProvider('accepts')]
    public function testWanted(string $accept, bool $wanted): void
    {
        self::assertSame($wanted, MarkdownRenderer::wanted($accept));
    }

    /** @return iterable<string, array{string, string}> */
    public static function links(): iterable
    {
        yield 'sibling' => ['b', 'https://x.pl/a/b'];
        yield 'up' => ['../', 'https://x.pl/'];
        yield 'up too far' => ['../../../c', 'https://x.pl/c'];
        yield 'current directory' => ['./', 'https://x.pl/a/'];
        yield 'fragment' => ['#faq', 'https://x.pl/a/strona#faq'];
        yield 'root' => ['/c/d?e=1', 'https://x.pl/c/d?e=1'];
        yield 'protocol-relative' => ['//cdn.pl/a.js', 'https://cdn.pl/a.js'];
        yield 'scheme' => ['tel:+48123', 'tel:+48123'];
    }

    #[DataProvider('links')]
    public function testResolve(string $href, string $expected): void
    {
        self::assertSame($expected, MarkdownRenderer::resolve($href, 'https://x.pl/a/strona'));
    }

    public function testLlmsTxt(): void
    {
        $text = $this->renderer()->llmsTxt(
            '<html><head><title>Port Gdynia</title><meta name="description" content="Wszystko o porcie"></head></html>',
            [
                new MarkdownPage('index.md', 'https://example.com/index.md', 'Start', '', ''),
                new MarkdownPage('a.md', 'https://example.com/a.md', 'Statki [lista]', 'Jakie są', ''),
            ],
        );

        self::assertSame(<<<'TXT'
            # Port Gdynia

            > Wszystko o porcie

            ## Strony

            - [Start](https://example.com/index.md)
            - [Statki \[lista\]](https://example.com/a.md): Jakie są

            TXT, $text);
        self::assertStringStartsWith("# example.com\n\n## Strony", $this->renderer()->llmsTxt(null, []));
        self::assertSame('llms.txt', LlmsTxt::PATH);
    }

    private function renderer(bool $enabled = true): MarkdownRenderer
    {
        $paths = new SitePaths('https://example.com', cleanUrls: true);
        $twig = new Environment(new ArrayLoader([
            'port.md.twig' => "# {{ name }}\n\nNa stronie {{ site_page.path }}\n",
        ]));

        return new MarkdownRenderer(
            new SiteRenderer($twig, $paths, new EventDispatcher()),
            $paths,
            $enabled,
            'main',
            ['nav', 'header', 'footer', 'form', 'script', 'style', '[data-markdown-skip]'],
            true,
        );
    }
}
