<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Build;

use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Page\SitePaths;

final class Sitemap
{
    /** @param list<SitePage> $pages */
    public static function xml(array $pages, SitePaths $paths, \DateTimeImmutable $now): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $page) {
            $xml .= "    <url>\n";
            $xml .= sprintf("        <loc>%s</loc>\n", htmlspecialchars($paths->absolute($page->path), ENT_XML1));
            $xml .= sprintf("        <lastmod>%s</lastmod>\n", ($page->lastModified ?? $now)->format('Y-m-d'));
            if ($page->changeFrequency !== null) {
                $xml .= sprintf("        <changefreq>%s</changefreq>\n", $page->changeFrequency);
            }
            if ($page->priority !== null) {
                $xml .= sprintf("        <priority>%.1f</priority>\n", $page->priority);
            }
            $xml .= "    </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    public static function robots(SitePaths $paths): string
    {
        return "User-agent: *\nAllow: /\n\nSitemap: " . $paths->absolute('sitemap.xml') . "\n";
    }
}
