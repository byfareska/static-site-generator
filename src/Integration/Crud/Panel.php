<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Where the pages of the site go in the panel of byfareska/crud (static_site.panel).
 */
final readonly class Panel
{
    public const string PUBLICATION = 'site_publication';
    public const string SUBMISSION = 'site_submission';

    public string $scope;

    public function __construct(
        #[Autowire('%static_site.panel.scope%')]
        ?string $scope,
        #[Autowire('%static_site.panel.section%')]
        public string $section,
        #[Autowire('%static_site.panel.texts_section%')]
        public string $textsSection,
        #[Autowire('%static_site.panel.role%')]
        public ?string $role,
    ) {
        $this->scope = $scope ?? AsCrud::DEFAULT_SCOPE;
    }
}
