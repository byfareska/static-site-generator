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

        $client->request('GET', '/admin/podglad/live/nie-ma.html');
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
