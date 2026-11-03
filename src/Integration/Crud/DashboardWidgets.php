<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\DashboardWidgetSource;
use Byfareska\Crud\Dashboard\WidgetSize;

/** The state of the targets on the dashboard of the panel of static_site.panel. */
final readonly class DashboardWidgets implements DashboardWidgetSource
{
    public function __construct(private Panel $panel)
    {
    }

    public function widgets(): iterable
    {
        yield new DashboardWidget(
            'static_site.status',
            'StaticSite:StatusWidget',
            'static_site.widget.title',
            'static_site.widget.description',
            'cloud-upload',
            accent: '#0ea5e9',
            size: new WidgetSize(4, 3),
            maxSize: new WidgetSize(8, 5),
            role: $this->panel->role,
            default: true,
            priority: -100,
            lazy: false,
            boards: [$this->panel->scope],
        );
    }
}
