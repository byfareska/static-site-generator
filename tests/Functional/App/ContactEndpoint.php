<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Byfareska\StaticSite\Endpoint\AsSiteEndpoint;
use Byfareska\StaticSite\Endpoint\EndpointResult;
use Byfareska\StaticSite\Endpoint\SiteSubmissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsSiteEndpoint('contact', '/kontakt', rateLimit: '3/10min')]
final readonly class ContactEndpoint
{
    public function __construct(private SiteSubmissions $submissions, private MessageBusInterface $bus)
    {
    }

    public function __invoke(Request $request, #[MapRequestPayload] ContactRequest $contact): EndpointResult
    {
        $submission = $this->submissions->record('contact', $request, ['email' => $contact->email, 'message' => $contact->message]);
        $this->bus->dispatch(new ContactReceived((int) $submission->id));

        return EndpointResult::done('Dziękujemy, odpiszemy wkrótce.');
    }
}
