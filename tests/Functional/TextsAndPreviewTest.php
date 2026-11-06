<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\StaticSite\Entity\SiteText;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Publication\SiteStatus;
use Byfareska\StaticSite\Tests\Functional\App\TestKernel;
use Byfareska\StaticSite\Text\Texts;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class TextsAndPreviewTest extends FunctionalTestCase
{
    public function testTextsFollowTheCodeUntilChanged(): void
    {
        self::bootKernel();
        $texts = self::service(Texts::class);

        self::assertSame('Witaj', $texts->get('home.title'));
        self::assertSame('Pisz na <strong><a href="mailto:a&amp;b@example.com">a&amp;b@example.com</a></strong>', $texts->get('home.lead'));

        $texts->save(['home.title' => 'Cześć {email}', 'home.lead' => 'Pisz na <strong>{email}</strong>']);
        self::assertSame('Cześć a&b@example.com', $texts->get('home.title'), 'A plain text gets the value (Twig escapes it).');
        self::assertCount(1, self::service(EntityManagerInterface::class)->getRepository(SiteText::class)->findAll(), 'A default is not kept.');

        $texts->save(['home.title' => 'Witaj']);
        self::assertCount(0, self::service(EntityManagerInterface::class)->getRepository(SiteText::class)->findAll());
    }

    public function testAChangedTextIsSomethingToPublish(): void
    {
        self::bootKernel();
        $publisher = self::service(Publisher::class);
        $publisher->run($publisher->request(['plain']));
        $status = self::service(SiteStatus::class);
        self::assertFalse($status->anyNeedsPublication(['plain']));

        sleep(1); // the dates are kept to the second
        self::service(Texts::class)->save(['home.title' => 'Nowy']);

        self::assertTrue($status->anyNeedsPublication(['plain']));
    }

    public function testThePreviewDrawsThePagesAsTheyWillBe(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/podglad/live/artykuly/pierwszy.html');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('href="../css/style.css?v=', $html);
        self::assertStringContainsString('Podgląd</div></body>', $html);

        $client->request('GET', '/admin/podglad/live/css/style.css');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/css; charset=UTF-8');

        $client->request('GET', '/admin/podglad/live');
        self::assertResponseRedirects('/admin/podglad/live/', 301);

        $client->request('GET', '/admin/podglad/live/pierwszy.html');
        self::assertResponseRedirects('artykuly/pierwszy.html');

        $client->request('GET', '/admin/podglad/live/sitemap.xml');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/admin/podglad/live/media/hero.png');
        self::assertResponseIsSuccessful();
        self::assertSame('HERO', $client->getResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'image/png');

        $client->request('GET', '/admin/podglad/live/galeria/');
        self::assertStringContainsString('<img src="../media/hero.png" alt="Hero">', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/podglad/live/media/nie-ma.png');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/admin/podglad/live/nie-ma.html');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePreviewWithCleanUrlsAndMarkdown(): void
    {
        $client = self::createClient(['environment' => 'markdown']);

        $client->request('GET', '/admin/podglad/live/artykuly/drugi');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertResponseHeaderSame('Vary', 'Accept');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<link rel="alternate" type="text/markdown" href="drugi.md"></head>', $html);
        self::assertStringContainsString('href="pierwszy"', $html);

        $client->request('GET', '/admin/podglad/live/artykuly/drugi.md');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertStringContainsString('url: https://example.com/artykuly/drugi', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/podglad/live/artykuly/drugi', server: ['HTTP_ACCEPT' => 'text/markdown, text/html;q=0.9']);
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertStringContainsString('[pierwszy](https://example.com/artykuly/pierwszy)', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/podglad/live/', server: ['HTTP_ACCEPT' => 'text/markdown']);
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertStringContainsString('# Witaj', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/podglad/live/artykuly/drugi.html', server: ['HTTP_ACCEPT' => 'text/html,*/*']);
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');

        $client->request('GET', '/admin/podglad/live/llms.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('- [Drugi](https://example.com/artykuly/drugi.md)', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/podglad/live/bez-markdown');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/admin/podglad/live/bez-markdown.md');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/admin/podglad/live/artykuly');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePublishCommand(): void
    {
        $kernel = self::bootKernel();
        $tester = new CommandTester((new Application($kernel))->find('static-site:publish'));

        $tester->execute(['target' => ['plain']]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
        self::assertFileExists(TestKernel::varDir() . '/plain/index.html');
        self::assertStringContainsString('<h1>Witaj</h1>', (string) file_get_contents(TestKernel::varDir() . '/plain/index.html'));

        $tester->execute(['target' => ['nieznany']]);
        self::assertSame(1, $tester->getStatusCode());
    }
}
