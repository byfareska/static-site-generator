<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Target;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The targets of static_site.targets, in their order.
 */
final class Targets
{
    /** @var array<string, Target>|null */
    private ?array $targets = null;

    /**
     * @param array<string, array{label: string|null, dsn: string|null, service: string|null, strategy: string, backend: bool}> $config
     */
    public function __construct(
        #[Autowire('%static_site.targets%')]
        private readonly array $config,
    ) {
    }

    /** @return array<string, Target> */
    public function all(): array
    {
        if ($this->targets === null) {
            $this->targets = [];
            foreach ($this->config as $name => $target) {
                $this->targets[$name] = new Target(
                    $name,
                    $target['label'] ?? $name,
                    $target['dsn'] === '' ? null : $target['dsn'],
                    $target['service'],
                    $target['strategy'],
                    $target['backend'],
                );
            }
        }

        return $this->targets;
    }

    public function get(string $name): Target
    {
        return $this->all()[$name] ?? throw new \InvalidArgumentException(sprintf(
            'There is no target "%s". The targets: %s.',
            $name,
            implode(', ', array_keys($this->all())) ?: 'none',
        ));
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->all());
    }
}
