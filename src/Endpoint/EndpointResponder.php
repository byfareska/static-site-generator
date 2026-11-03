<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The answers of the endpoints, one form for all: JSON {ok, message, errors, data} for the script of the site, a
 * redirect back to the page (303, ?wyslano=<name> or ?blad=<name>) for a form sent without it. An EndpointResult
 * returned by the controller and a failed #[MapRequestPayload] (422, the messages at their fields) both end here.
 */
final readonly class EndpointResponder
{
    public function __construct(
        private SiteEndpoints $endpoints,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    #[AsEventListener(KernelEvents::VIEW)]
    public function onView(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        $name = $event->getRequest()->attributes->get('_site_endpoint');
        if ($result instanceof EndpointResult && is_string($name)) {
            $event->setResponse($this->respond($result, $event->getRequest(), $name));
        }
    }

    #[AsEventListener(KernelEvents::EXCEPTION, priority: 8)]
    public function onException(ExceptionEvent $event): void
    {
        $name = $event->getRequest()->attributes->get('_site_endpoint');
        if (!is_string($name)) {
            return;
        }
        $e = $event->getThrowable();
        $violations = $e instanceof ValidationFailedException ? $e : $e->getPrevious();
        if ($violations instanceof ValidationFailedException) {
            $errors = [];
            foreach ($violations->getViolations() as $violation) {
                $errors[$violation->getPropertyPath() ?: '_'] ??= (string) $violation->getMessage();
            }
            $result = EndpointResult::refused('Popraw zaznaczone pola.', $errors);
        } elseif ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            $result = EndpointResult::refused('Nie udało się wysłać.', [], $e->getStatusCode());
        } else {
            $this->logger->error('The endpoint {endpoint} failed: {message}', ['endpoint' => $name, 'message' => $e->getMessage(), 'exception' => $e]);
            $result = EndpointResult::refused('Coś poszło nie tak. Spróbuj ponownie później.', [], 500);
        }
        $event->setResponse($this->respond($result, $event->getRequest(), $name));
    }

    public function respond(EndpointResult $result, Request $request, string $name): Response
    {
        if ($this->wantsJson($request)) {
            $response = new JsonResponse($result->toArray(), $result->status);
            $maxAge = $name === SiteEndpoints::TOKEN ? null : $this->endpoints->get($name)['cacheMaxAge'];
            if ($maxAge !== null && $result->ok && $request->isMethodCacheable()) {
                $response->setPublic();
                $response->setMaxAge($maxAge);
            }

            return $response;
        }

        return new RedirectResponse($this->back($request, ($result->ok ? 'wyslano=' : 'blad=') . rawurlencode($name)), 303);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->headers->has('X-Site-Endpoint')
            || $request->getPreferredFormat() === 'json'
            || str_contains((string) $request->headers->get('Content-Type'), 'json')
            || $request->isMethodCacheable();
    }

    /** The page the form was on (only one of this site), with $query and the fragment of the form. */
    private function back(Request $request, string $query): string
    {
        $referer = (string) $request->headers->get('Referer');
        $host = parse_url($referer, PHP_URL_HOST);
        if ($referer === '' || !is_string($host) || strcasecmp($host, $request->getHost()) !== 0) {
            return '/?' . $query;
        }
        $referer = (string) preg_replace('/#.*$/', '', $referer);
        $referer = (string) preg_replace('/([?&])(wyslano|blad)=[^&]*&?/', '$1', $referer);
        $referer = rtrim($referer, '?&');

        return $referer . (str_contains($referer, '?') ? '&' : '?') . $query;
    }
}
