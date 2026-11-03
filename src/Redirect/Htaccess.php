<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Redirect;

use Byfareska\StaticSite\Page\SitePaths;

/**
 * The block of rules the build puts into the .htaccess of the site (Apache): the redirects as 301/302 and, for a site
 * with endpoints, the requests of /api handed to the front controller of the application. The block is replaced on
 * every build; the rest of the file is the site's own.
 */
final class Htaccess
{
    public const string BEGIN = '# BEGIN static-site';
    public const string END = '# END static-site';

    /**
     * @param list<Redirect> $redirects
     */
    public static function block(array $redirects, SitePaths $paths, ?string $endpointPrefix): string
    {
        $lines = [self::BEGIN, '# Written by the generator on every publication. Changes here are overwritten.'];
        if ($redirects !== [] || $endpointPrefix !== null) {
            $lines[] = '<IfModule mod_rewrite.c>';
            $lines[] = 'RewriteEngine On';
        }
        if ($endpointPrefix !== null) {
            $prefix = trim($endpointPrefix, '/');
            $lines[] = '# The endpoints of the site go to the application (same server, same domain).';
            $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
            $lines[] = sprintf('RewriteRule ^%s(/.*)?$ index.php [QSA,L]', preg_quote($prefix, '#'));
        }
        foreach ($redirects as $redirect) {
            $to = $redirect->isExternal() ? $redirect->to : $paths->absolute($redirect->to);
            $lines[] = sprintf(
                'RewriteRule ^%s$ %s [R=%d,L,NE]',
                preg_quote($redirect->fromPath(), '#'),
                $to,
                $redirect->permanent ? 301 : 302,
            );
        }
        if ($redirects !== [] || $endpointPrefix !== null) {
            $lines[] = '</IfModule>';
        }
        $lines[] = self::END;

        return implode("\n", $lines) . "\n";
    }

    /** $existing with the block put in place of the old one, or at its top. */
    public static function merge(string $existing, string $block): string
    {
        $pattern = '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . "\n?/s";
        if (preg_match($pattern, $existing) === 1) {
            return (string) preg_replace($pattern, addcslashes($block, '\\$'), $existing, 1);
        }

        return $block . ($existing === '' ? '' : "\n" . $existing);
    }
}
