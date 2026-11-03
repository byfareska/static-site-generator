<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Functional\App;

use Byfareska\StaticSite\Text\Placeholder;
use Byfareska\StaticSite\Text\PlaceholderProvider;
use Byfareska\StaticSite\Text\TextCatalog;
use Byfareska\StaticSite\Text\TextGroup;
use Byfareska\StaticSite\Text\TextItem;
use Byfareska\StaticSite\Text\TextType;

final class Catalog implements TextCatalog, PlaceholderProvider
{
    public function groups(): iterable
    {
        yield new TextGroup('home', 'Strona główna', [
            new TextItem('home.title', 'Tytuł', default: 'Witaj'),
            new TextItem('home.lead', 'Wstęp', TextType::Inline, 'Pisz na <strong>{email}</strong>'),
        ]);
    }

    public function placeholders(): iterable
    {
        yield new Placeholder('email', 'a&b@example.com', 'Adres', '<a href="mailto:a&amp;b@example.com">a&amp;b@example.com</a>');
    }
}
