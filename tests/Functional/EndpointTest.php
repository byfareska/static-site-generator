<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional;

use Byfareska\StaticSite\Endpoint\FormToken;
use Byfareska\StaticSite\Endpoint\SubmissionStatus;
use Byfareska\StaticSite\Entity\SiteSubmission;
use Byfareska\StaticSite\Tests\Functional\App\ContactReceived;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class EndpointTest extends FunctionalTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    public function testTheTokenEndpoint(): void
    {
        $this->client->request('GET', '/api/_token');

        self::assertResponseIsSuccessful();
        $token = json_decode((string) $this->client->getResponse()->getContent(), true)['token'] ?? null;
        self::assertIsString($token);
        self::assertNull(self::service(FormToken::class)->refusal($token, 0));
    }

    public function testASubmissionIsKeptAndHandedToAMessage(): void
    {
        $answer = $this->send(['email' => 'jan@example.com', 'message' => 'Dzień dobry']);

        self::assertResponseIsSuccessful();
        self::assertTrue($answer['ok']);
        self::assertSame('Dziękujemy, odpiszemy wkrótce.', $answer['message']);
        $submission = $this->submissions()[0];
        self::assertSame(['email' => 'jan@example.com', 'message' => 'Dzień dobry'], $submission->data);
        self::assertSame(SubmissionStatus::New, $submission->status);
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(ContactReceived::class, $sent[0]->getMessage());
    }

    public function testInvalidFieldsGetTheirMessages(): void
    {
        $answer = $this->send(['email' => 'nie-email', 'message' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertFalse($answer['ok']);
        self::assertSame('To nie jest adres e-mail.', $answer['errors']['email']);
        self::assertSame('Napisz wiadomość.', $answer['errors']['message']);
        self::assertSame([], $this->submissions());
    }

    public function testABotFillingTheHoneypotIsToldItWorked(): void
    {
        $answer = $this->send(['email' => 'bot@example.com', 'message' => 'spam', '_hp' => 'http://spam']);

        self::assertResponseIsSuccessful();
        self::assertTrue($answer['ok']);
        self::assertSame(SubmissionStatus::Spam, $this->submissions()[0]->status);
        self::assertCount(0, $this->transport()->getSent());
    }

    public function testTokens(): void
    {
        $tokens = self::service(FormToken::class);

        $this->send(['email' => 'a@example.com', 'message' => 'x'], $tokens->issue(time()));
        self::assertResponseStatusCodeSame(400, 'Sent at once.');
        $this->send(['email' => 'a@example.com', 'message' => 'x'], $tokens->issue(time() - FormToken::MAX_AGE - 10));
        self::assertResponseStatusCodeSame(400, 'Expired.');
        $this->send(['email' => 'a@example.com', 'message' => 'x'], '');
        self::assertResponseStatusCodeSame(400, 'Missing.');
    }

    public function testAForeignOriginIsRefused(): void
    {
        $this->send(['email' => 'a@example.com', 'message' => 'x'], origin: 'https://evil.example.org');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheRateLimit(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->send(['email' => 'a@example.com', 'message' => 'x' . $i]);
            self::assertResponseIsSuccessful();
        }
        $answer = $this->send(['email' => 'a@example.com', 'message' => 'x']);

        self::assertResponseStatusCodeSame(429);
        self::assertFalse($answer['ok']);
    }

    public function testAFormWithoutTheScriptGoesBackToItsPage(): void
    {
        $this->client->request('POST', '/api/kontakt', [
            'email' => 'jan@example.com',
            'message' => 'Bez JS',
            '_token' => self::service(FormToken::class)->issue(time() - 10),
        ], server: [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_REFERER' => 'http://localhost/kontakt/?blad=contact#formularz',
        ]);

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('http://localhost/kontakt/?wyslano=contact');
        self::assertCount(1, $this->submissions());
    }

    public function testTooMuchData(): void
    {
        $this->send(['email' => 'a@example.com', 'message' => str_repeat('x', 70000)]);

        self::assertResponseStatusCodeSame(413);
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    private function send(array $fields, ?string $token = null, string $origin = 'https://example.com'): array
    {
        $fields['_token'] = $token ?? self::service(FormToken::class)->issue(time() - 10);
        $this->client->request('POST', '/api/kontakt', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SITE_ENDPOINT' => '1',
            'HTTP_ORIGIN' => $origin,
        ], content: (string) json_encode($fields));
        $answer = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($answer) ? $answer : [];
    }

    /** @return list<SiteSubmission> */
    private function submissions(): array
    {
        $em = self::service(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(SiteSubmission::class)->findBy([], ['id' => 'ASC']);
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
