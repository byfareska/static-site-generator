<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Endpoint;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The token a form of the static site gets from the script before it is sent: the time it was given, signed. It
 * stands in for the CSRF token of a session (a static page has none) and tells a bot that sends at once.
 */
final readonly class FormToken
{
    public const int MAX_AGE = 7200;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function issue(?int $at = null): string
    {
        $at ??= time();

        return $at . '.' . $this->sign((string) $at);
    }

    /** @return string|null why the token is refused, null for a good one */
    public function refusal(?string $token, int $minSeconds, ?int $now = null): ?string
    {
        if ($token === null || preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $m) !== 1) {
            return 'missing';
        }
        if (!hash_equals($this->sign($m[1]), $m[2])) {
            return 'forged';
        }
        $age = ($now ?? time()) - (int) $m[1];

        return match (true) {
            $age < $minSeconds => 'too_fast',
            $age > self::MAX_AGE => 'expired',
            default => null,
        };
    }

    private function sign(string $value): string
    {
        return hash_hmac('sha256', 'static_site_form|' . $value, $this->secret);
    }
}
