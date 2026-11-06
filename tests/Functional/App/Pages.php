<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Byfareska\StaticSite\Page\PageProvider;
use Byfareska\StaticSite\Page\RenderContext;
use Byfareska\StaticSite\Page\SchedulesChanges;
use Byfareska\StaticSite\Page\SitePage;
use Byfareska\StaticSite\Redirect\Redirect;
use Byfareska\StaticSite\Redirect\RedirectProvider;

/**
 * The home page, two articles, a page without a Markdown version, a gallery of uploaded files (Uploads), a page
 * with the contact form (only while $withContact) and a moved article; $scheduledAt: a change to come (an article dated ahead).
 */
final class Pages implements PageProvider, RedirectProvider, SchedulesChanges
{
    public bool $withContact = false;

    public ?\DateTimeImmutable $scheduledAt = null;

    public function pages(RenderContext $context): iterable
    {
        yield new SitePage('index.html', 'home.html.twig', ['title' => 'Start']);
        yield new SitePage('artykuly/pierwszy.html', 'home.html.twig', ['title' => 'Pierwszy']);
        yield new SitePage('artykuly/drugi.html', 'article.html.twig', ['title' => 'Drugi'], lastModified: new \DateTimeImmutable('2026-11-01'));
        yield new SitePage('bez-markdown.html', 'home.html.twig', ['title' => 'Bez Markdown'], markdown: false);
        yield new SitePage('galeria/index.html', 'gallery.html.twig', markdown: false);
        if ($this->withContact) {
            yield new SitePage('kontakt/index.html', 'contact.html.twig');
        }
    }

    public function redirects(): iterable
    {
        yield new Redirect('pierwszy.html', 'artykuly/pierwszy.html');
    }

    public function nextChangeAfter(\DateTimeImmutable $after): ?\DateTimeImmutable
    {
        return $this->scheduledAt !== null && $this->scheduledAt > $after ? $this->scheduledAt : null;
    }
}
