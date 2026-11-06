<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Navigation\NavigationSource;

/** "Ustawienia strony" in the menu, when the site has something to set. */
final readonly class SettingsNavigation implements NavigationSource
{
    public function __construct(
        private Panel $panel,
        private SettingsController $settings,
    ) {
    }

    public function items(): iterable
    {
        if ($this->settings->hasSettings()) {
            yield new NavigationItem($this->panel->section, 'static_site_settings', 'static_site.nav.settings', 'gear', $this->panel->role, 1000, $this->panel->scope);
        }
    }
}
