<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Byfareska\StaticSite\Page\SitePaths;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Checks a request of an endpoint before its controller: the size, the origin, the rate limit, the honeypot and
 * the token (AsSiteEndpoint::$protection). A request that fails is answered here.
 */
final readonly class EndpointGuard
{
    public const string HONEYPOT_FIELD = '_hp';
    public const string TOKEN_FIELD = '_token';

    public function __construct(
        private SiteEndpoints $endpoints,
        private FormToken $tokens,
        private RateLimits $limits,
        private SiteSubmissions $submissions,
        private EndpointResponder $responder,
        private SitePaths $paths,
    ) {
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: 16)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $name = $request->attributes->get('_site_endpoint');
        if (!is_string($name) || $name === SiteEndpoints::TOKEN || !$event->isMainRequest()) {
            return;
        }
        $endpoint = $this->endpoints->get($name);
        $refuse = function (string $message, int $status) use ($event, $request, $name): void {
            $event->setResponse($this->responder->respond(EndpointResult::refused($message, [], $status), $request, $name));
        };

        if (strlen((string) $request->getContent()) > $endpoint['maxBodySize']) {
            $refuse('Za dużo danych.', 413);

            return;
        }
        $protection = $endpoint['protection'];
        $isWrite = !in_array($request->getMethod(), ['GET', 'HEAD'], true);
        if (in_array(AsSiteEndpoint::ORIGIN, $protection, true) && $isWrite && !$this->isOwnOrigin($request)) {
            $refuse('Niedozwolone źródło żądania.', 403);

            return;
        }
        if ($endpoint['rateLimit'] !== null
            && !$this->limits->consume($name, $endpoint['rateLimit'], (string) $request->getClientIp())) {
            $refuse('Za dużo prób. Spróbuj ponownie za kilka minut.', 429);

            return;
        }
        if (!$isWrite) {
            return;
        }
        $fields = $request->request->all() ?: (json_decode((string) $request->getContent(), true) ?: []);
        $fields = is_array($fields) ? $fields : [];
        if (in_array(AsSiteEndpoint::HONEYPOT, $protection, true) && trim((string) ($fields[self::HONEYPOT_FIELD] ?? '')) !== '') {
            // A bot: told it worked, so it does not try another way; kept as spam to be seen in the panel.
            unset($fields[self::HONEYPOT_FIELD], $fields[self::TOKEN_FIELD]);
            $this->submissions->record($name, $request, $fields, SubmissionStatus::Spam);
            $event->setResponse($this->responder->respond(EndpointResult::done(), $request, $name));

            return;
        }
        if (in_array(AsSiteEndpoint::TOKEN, $protection, true)) {
            $token = $fields[self::TOKEN_FIELD] ?? $request->headers->get('X-Site-Token');
            $refusal = $this->tokens->refusal(is_string($token) ? $token : null, $endpoint['minSeconds']);
            if ($refusal !== null) {
                $refuse(match ($refusal) {
                    'too_fast' => 'Formularz wysłano zbyt szybko. Spróbuj jeszcze raz.',
                    'expired' => 'Formularz był otwarty zbyt długo. Odśwież stronę i spróbuj jeszcze raz.',
                    default => 'Nie udało się zweryfikować formularza. Odśwież stronę i spróbuj jeszcze raz.',
                }, 400);
            }
        }
    }

    private function isOwnOrigin(Request $request): bool
    {
        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');
        if ($origin === null || $origin === '') {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $allowed = array_filter([$request->getHost(), parse_url($this->paths->baseUrl(), PHP_URL_HOST)]);

        return is_string($host) && in_array(strtolower($host), array_map('strtolower', $allowed), true);
    }
}
