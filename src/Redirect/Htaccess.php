<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Redirect;

use Byfareska\StaticSite\Page\SitePaths;

/**
 * The block of rules the build puts into the .htaccess of the site (Apache): the redirects as 301/302 and, for a site
 * with endpoints, the requests of /api handed to the front controller of the application, and with clean URLs the
 * mapping of the addresses to the files (and to their Markdown versions). The block is replaced on every build; the
 * rest of the file is the site's own.
 */
final class Htaccess
{
    public const string BEGIN = '# BEGIN static-site';
    public const string END = '# END static-site';

    /** The conditions of a request that wants the Markdown (MarkdownRenderer::wanted()). */
    private const array WANTS_MARKDOWN = [
        'RewriteCond %{HTTP_ACCEPT} text/markdown [NC]',
        'RewriteCond %{HTTP_ACCEPT} !^\s*text/html [NC]',
        'RewriteCond %{HTTP_ACCEPT} !text/markdown\s*;\s*q=0(\.0*)?\s*(,|$) [NC]',
    ];

    /**
     * With the clean URLs of $paths: /x is served from x.html, /x.html is moved to /x for good; with $markdown, /x
     * and /a/ are served from x.md and a/index.md to a request that wants Markdown.
     *
     * @param list<Redirect> $redirects
     */
    public static function block(array $redirects, SitePaths $paths, ?string $endpointPrefix, bool $markdown = false): string
    {
        $clean = $paths->cleanUrls();
        $markdown = $markdown && $clean;
        $rewrites = $redirects !== [] || $endpointPrefix !== null || $clean;
        $lines = [self::BEGIN, '# Written by the generator on every publication. Changes here are overwritten.'];
        if ($clean) {
            $lines[] = '# Clean URLs: the address /x is the file x.html' . ($markdown ? ' (or x.md, to "Accept: text/markdown").' : '.');
            $lines[] = 'Options -MultiViews';
        }
        if ($markdown) {
            $lines[] = 'AddType text/markdown .md';
            $lines[] = 'AddCharset UTF-8 .md';
            $lines[] = '<IfModule mod_headers.c>';
            $lines[] = '    <FilesMatch "\.(html|md)$">';
            $lines[] = '        Header always merge Vary Accept';
            $lines[] = '    </FilesMatch>';
            $lines[] = '</IfModule>';
        }
        if ($rewrites) {
            $lines[] = '<IfModule mod_rewrite.c>';
            $lines[] = 'RewriteEngine On';
        }
        if ($clean) {
            $lines[] = 'RewriteBase ' . $paths->basePath() . '/';
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
                self::fromPattern($redirect->fromPath(), $clean),
                $to,
                $redirect->permanent ? 301 : 302,
            );
        }
        if ($clean) {
            $lines[] = '# /x.html -> /x, /a/index.html -> /a/ (the requests of the client only, not the rewrites below)';
            $lines[] = 'RewriteCond %{THE_REQUEST} \s/+((?:[^?\s]*/)?)index\.html[\s?]';
            $lines[] = 'RewriteRule ^ /%1 [R=301,L,NE]';
            $lines[] = 'RewriteCond %{THE_REQUEST} \s/+([^?\s]+)\.html[\s?]';
            $lines[] = 'RewriteRule ^ /%1 [R=301,L,NE]';
        }
        if ($markdown) {
            $lines[] = '# Markdown to a client that wants it (not to a browser, not with q=0)';
            array_push($lines, ...self::WANTS_MARKDOWN);
            $lines[] = 'RewriteCond %{REQUEST_FILENAME}index.md -f';
            $lines[] = 'RewriteRule ^(.*/)?$ $1index.md [END]';
            array_push($lines, ...self::WANTS_MARKDOWN);
            $lines[] = 'RewriteCond %{REQUEST_FILENAME}.md -f';
            $lines[] = 'RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]';
        }
        if ($clean) {
            $lines[] = '# The HTML by default' . ($markdown ? ', the Markdown of a page without one' : '');
            $lines[] = 'RewriteCond %{REQUEST_FILENAME}.html -f';
            $lines[] = 'RewriteRule ^((?:.*/)?[^/.]+)$ $1.html [END]';
        }
        if ($markdown) {
            $lines[] = 'RewriteCond %{REQUEST_FILENAME}.md -f';
            $lines[] = 'RewriteRule ^((?:.*/)?[^/.]+)$ $1.md [END]';
        }
        if ($rewrites) {
            $lines[] = '</IfModule>';
        }
        $lines[] = self::END;

        return implode("\n", $lines) . "\n";
    }

    /** The address of a redirect; with clean URLs both "x.html" and "x" (and "a/" for "a/index.html"). */
    private static function fromPattern(string $from, bool $clean): string
    {
        if (!$clean || !str_ends_with($from, '.html')) {
            return preg_quote($from, '#');
        }
        if ($from === 'index.html' || str_ends_with($from, '/index.html')) {
            return preg_quote(substr($from, 0, -strlen('index.html')), '#') . '(?:index\.html)?';
        }

        return preg_quote(substr($from, 0, -strlen('.html')), '#') . '(?:\.html)?';
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
