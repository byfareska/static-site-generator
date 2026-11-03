<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

/**
 * What an endpoint returns: the script of the site shows $message, puts $errors at their fields and gives $data to
 * the page (an event "site-endpoint:done"). A form sent without the script is redirected back with ?wyslano=<name>
 * (or ?blad=<name>).
 */
final readonly class EndpointResult
{
    /**
     * @param array<string, string> $errors field => message
     * @param array<string, mixed>  $data
     */
    public function __construct(
        public bool $ok = true,
        public ?string $message = null,
        public array $errors = [],
        public array $data = [],
        public int $status = 200,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function done(?string $message = null, array $data = []): self
    {
        return new self(true, $message, [], $data);
    }

    /** @param array<string, string> $errors */
    public static function refused(string $message, array $errors = [], int $status = 422): self
    {
        return new self(false, $message, $errors, [], $status);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'ok' => $this->ok,
            'message' => $this->message,
            'errors' => $this->errors === [] ? null : $this->errors,
            'data' => $this->data === [] ? null : $this->data,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
