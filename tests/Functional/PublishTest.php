<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\StaticSite\Entity\Publication;
use Byfareska\StaticSite\Integration\Scheduler\PublicationSchedule;
use Byfareska\StaticSite\Integration\Scheduler\PublishScheduled;
use Byfareska\StaticSite\Publication\PublicationStatus;
use Byfareska\StaticSite\Publication\Publisher;
use Byfareska\StaticSite\Publication\ScheduledPublications;
use Byfareska\StaticSite\Publication\SiteStatus;
use Byfareska\StaticSite\Tests\Functional\App\Pages;
use Byfareska\StaticSite\Tests\Functional\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class PublishTest extends FunctionalTestCase
{
    public function testASiteIsBuiltAndPublished(): void
    {
        self::bootKernel();
        $publication = $this->publish(['plain']);

        self::assertSame(PublicationStatus::Done, $publication->status, $publication->log);
        $dir = TestKernel::varDir() . '/plain';
        $home = (string) file_get_contents($dir . '/index.html');
        $article = (string) file_get_contents($dir . '/artykuly/pierwszy.html');

        self::assertMatchesRegularExpression('~href="css/style\.css\?v=[a-f0-9]{8}"~', $home);
        self::assertStringContainsString('<link rel="canonical" href="https://example.com/">', $home);
        self::assertStringContainsString('href="../css/style.css?v=', $article);
        self::assertStringContainsString('<a href="../">Start</a>', $article);
        self::assertStringContainsString('<a href="pierwszy.html">w treści</a>', $article);

        $htaccess = (string) file_get_contents($dir . '/.htaccess');
        self::assertStringContainsString("# END static-site\n\nAddDefaultCharset UTF-8", $htaccess, 'The block goes first, the own rules stay.');
        self::assertStringContainsString('RewriteRule ^pierwszy\.html$ https://example.com/artykuly/pierwszy.html [R=301,L,NE]', $htaccess);
        self::assertStringNotContainsString('index.php', $htaccess);
        self::assertStringContainsString('url=artykuly/pierwszy.html', (string) file_get_contents($dir . '/pierwszy.html'));
        self::assertStringContainsString('<loc>https://example.com/artykuly/pierwszy.html</loc>', (string) file_get_contents($dir . '/sitemap.xml'));
        self::assertStringContainsString('Sitemap: https://example.com/sitemap.xml', (string) file_get_contents($dir . '/robots.txt'));

        self::assertFalse(self::service(SiteStatus::class)->anyNeedsPublication(['plain']));
    }

    public function testASecondPublicationSendsOnlyTheDifferences(): void
    {
        self::bootKernel();
        $this->publish(['plain']);
        file_put_contents(TestKernel::varDir() . '/plain/.htpasswd', 'admin:x');
        self::service(Pages::class)->withContact = true;
        $publication = $this->publish(['app']);
        self::assertSame(PublicationStatus::Done, $publication->status, $publication->log);

        self::service(Pages::class)->withContact = false;
        $second = $this->publish(['plain']);

        self::assertSame(0, $second->uploaded, $second->log);
        self::assertFileExists(TestKernel::varDir() . '/plain/.htpasswd');
    }

    public function testAPageWithAnEndpointGoesOnlyToATargetWithABackend(): void
    {
        self::bootKernel();
        self::service(Pages::class)->withContact = true;

        $refused = $this->publish(['plain']);
        self::assertSame(PublicationStatus::Failed, $refused->status);
        self::assertStringContainsString('contact', $refused->log);
        self::assertStringContainsString('plain', $refused->log);
        self::assertFileDoesNotExist(TestKernel::varDir() . '/plain/index.html');

        $done = $this->publish(['app']);
        self::assertSame(PublicationStatus::Done, $done->status, $done->log);
        $dir = TestKernel::varDir() . '/app';
        $page = (string) file_get_contents($dir . '/kontakt/index.html');
        self::assertStringContainsString('action="/api/kontakt"', $page);
        self::assertStringContainsString('src="../_site/endpoints.js', $page);
        self::assertStringContainsString('data-token-url="/api/_token"', $page);
        self::assertFileExists($dir . '/_site/endpoints.js');
        self::assertStringContainsString('RewriteRule ^api(/.*)?$ index.php [QSA,L]', (string) file_get_contents($dir . '/.htaccess'));
    }

    public function testARollbackSendsAnOlderBuild(): void
    {
        self::bootKernel();
        $first = $this->publish(['app']);
        self::service(Pages::class)->withContact = true;
        $this->publish(['app']);
        self::assertFileExists(TestKernel::varDir() . '/app/kontakt/index.html');

        $rollback = $this->publish(['app'], $first);

        self::assertSame(PublicationStatus::Done, $rollback->status, $rollback->log);
        self::assertSame($first->buildId, $rollback->buildId);
        self::assertFileDoesNotExist(TestKernel::varDir() . '/app/kontakt/index.html');
    }

    /** @param list<string> $targets */
    public function testScheduledContentIsPublishedWhenItsDateComes(): void
    {
        self::bootKernel();
        $this->publish(['plain']);
        $pages = self::service(Pages::class);
        $scheduled = self::service(ScheduledPublications::class);

        $pages->scheduledAt = new \DateTimeImmutable('+1 hour');
        self::assertSame([], $scheduled->due(['plain']));
        self::assertSame(['plain'], $scheduled->due(['plain'], new \DateTimeImmutable('+2 hours')));

        // A change dated now: after the last build began, and due.
        $pages->scheduledAt = new \DateTimeImmutable();
        self::service(MessageBusInterface::class)->dispatch(new PublishScheduled());
        $publication = self::service(EntityManagerInterface::class)->getRepository(Publication::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($publication);
        self::assertSame(ScheduledPublications::REQUESTED_BY, $publication->requestedBy);
        self::assertContains('plain', $publication->targets);

        // A worker of the application consumes it: messenger:consume scheduler_static_site.
        self::assertTrue(self::getContainer()->has('messenger.transport.scheduler_' . PublicationSchedule::NAME));
        self::assertCount(1, (new PublicationSchedule('5 minutes'))->getSchedule()->getRecurringMessages());
    }

    private function publish(array $targets, ?Publication $rollbackOf = null): Publication
    {
        $publisher = self::service(Publisher::class);
        $publication = $publisher->request($targets, 'test', $rollbackOf);
        $publisher->run($publication);
        self::service(EntityManagerInterface::class)->refresh($publication);

        return $publication;
    }
}
