<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

/**
 * A place the site is published to (static_site.targets).
 */
final readonly class Target
{
    public const string SYNC = 'sync';
    public const string SYMLINK = 'symlink';

    public function __construct(
        public string $name,
        public string $label,
        public ?string $dsn,
        public ?string $service = null,
        public string $strategy = self::SYNC,
        public bool $backend = false,
    ) {
        if (($dsn === null) === ($service === null)) {
            throw new \InvalidArgumentException(sprintf('The target "%s" needs either "dsn" or "service".', $name));
        }
        if ($strategy === self::SYMLINK && !str_starts_with((string) $dsn, 'local://')) {
            throw new \InvalidArgumentException(sprintf(
                'The target "%s": the strategy "symlink" is for a local:// directory only.',
                $name,
            ));
        }
    }

    /** The directory of a local:// target. */
    public function localPath(): ?string
    {
        return $this->dsn !== null && str_starts_with($this->dsn, 'local://')
            ? rtrim(substr($this->dsn, strlen('local://')), '/')
            : null;
    }

    /** The address without its password, for the panel and the logs. */
    public function safeDsn(): string
    {
        if ($this->dsn === null) {
            return 'service: ' . $this->service;
        }

        return (string) preg_replace('#^([a-z0-9+.-]+://[^:/@]*):[^@]*@#i', '$1:***@', $this->dsn);
    }
}
