<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

use Byfareska\StaticSite\File\UploadedFiles;
use Byfareska\StaticSite\Page\SitePaths;

/**
 * The links of HTML written in the panel, as the page being drawn needs them: an uploaded file
 * (<img src="/uploads/ab/cd/x.jpg">, of a storage of the panel) becomes the file of the site it is published as, and a
 * path from the root ("/propozycje.png", "/#faq") a link relative to the page - so they work in the preview, in a
 * subdirectory and from file:// too.
 */
final readonly class SiteLinks
{
    private const string ATTRIBUTES = '/\b(href|src|poster|srcset)=(["\'])(.*?)\2/is';

    public function __construct(
        private SitePaths $paths,
        private UploadedFiles $files,
    ) {
    }

    public function rewrite(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        return (string) preg_replace_callback(self::ATTRIBUTES, function (array $m): string {
            $value = strtolower($m[1]) === 'srcset'
                ? implode(', ', array_map($this->candidate(...), explode(',', $m[3])))
                : $this->link($m[3]);

            return $m[1] . '=' . $m[2] . $value . $m[2];
        }, $html);
    }

    /** "x.jpg 2x" of a srcset. */
    private function candidate(string $candidate): string
    {
        $parts = preg_split('/\s+/', trim($candidate), 2) ?: [''];

        return trim($this->link($parts[0]) . ' ' . ($parts[1] ?? ''));
    }

    private function link(string $value): string
    {
        $address = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        $file = $this->files->use($address);
        if ($file !== null) {
            return htmlspecialchars($this->paths->relative($file->path), ENT_QUOTES | ENT_HTML5);
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            return $this->paths->relative($value);
        }

        return $value;
    }
}
