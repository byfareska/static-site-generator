<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Page;

enum RenderMode: string
{
    /** Files written for a target. */
    case Build = 'build';

    /** A page drawn in the panel, from the same templates. */
    case Preview = 'preview';
}
