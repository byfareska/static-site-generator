<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Navigation\NavigationSource;
use Byfareska\StaticSite\Text\Texts;

/** "Teksty" in the menu, with a page per group under it. */
final readonly class TextsNavigation implements NavigationSource
{
    public function __construct(
        private Panel $panel,
        private Texts $texts,
    ) {
    }

    public function items(): iterable
    {
        $groups = $this->texts->groups();
        if ($groups === []) {
            return;
        }
        yield new NavigationItem($this->panel->textsSection, 'static_site_texts', 'static_site.nav.texts', 'fonts', $this->panel->role, -100, $this->panel->scope);
        $priority = 0;
        foreach ($groups as $group) {
            yield new NavigationItem(
                $this->panel->textsSection,
                'static_site_texts_group',
                $group->label,
                $group->icon ?? 'dot',
                $this->panel->role,
                $priority++,
                $this->panel->scope,
                ['group' => $group->key],
                parent: 'static_site_texts',
                translate: false,
            );
        }
    }

}
