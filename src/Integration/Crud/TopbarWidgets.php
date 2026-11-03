<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Navigation\TopbarWidget;
use Byfareska\Crud\Navigation\TopbarWidgetSource;

/** The "publish" button in the top bar of the panel of static_site.panel. */
final readonly class TopbarWidgets implements TopbarWidgetSource
{
    public function __construct(private Panel $panel)
    {
    }

    public function widgets(): iterable
    {
        yield new TopbarWidget('StaticSite:PublishButton', -100, $this->panel->scope, $this->panel->role);
    }
}
