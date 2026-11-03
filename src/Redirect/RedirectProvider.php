<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Redirect;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::REDIRECT_PROVIDER_TAG)]
interface RedirectProvider
{
    public const string REDIRECT_PROVIDER_TAG = 'static_site.redirect_provider';

    /** @return iterable<Redirect> */
    public function redirects(): iterable;
}
