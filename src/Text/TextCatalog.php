<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The texts of the site: their keys, labels, types and the values they start with. The database keeps only the
 * values changed in the panel; a text never changed is its default, so a default changed in the code shows on the
 * next publication.
 */
#[AutoconfigureTag(self::TEXT_CATALOG_TAG)]
interface TextCatalog
{
    public const string TEXT_CATALOG_TAG = 'static_site.text_catalog';

    /** @return iterable<TextGroup> */
    public function groups(): iterable;
}
