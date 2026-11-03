<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::PLACEHOLDER_PROVIDER_TAG)]
interface PlaceholderProvider
{
    public const string PLACEHOLDER_PROVIDER_TAG = 'static_site.placeholder_provider';

    /** @return iterable<Placeholder> */
    public function placeholders(): iterable;
}
