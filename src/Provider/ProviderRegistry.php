<?php

namespace Kerrialnewham\Autocomplete\Provider;

use InvalidArgumentException;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;

final class ProviderRegistry
{
    /** @var array<string, AutocompleteProviderInterface> */
    private array $providers = [];

    public function __construct(iterable $providers)
    {
        foreach ($providers as $p) {
            $this->providers[get_class($p)] = $p;
        }
    }

    public function get(string $name): AutocompleteProviderInterface
    {
        return $this->providers[$name]
            ?? throw new InvalidArgumentException(sprintf(
                'Unknown provider "%s". Available providers: [%s]',
                $name,
                implode(', ', $this->names())
            ));
    }

    /** @return string[] */
    public function names(): array
    {
        $names = array_keys($this->providers);
        sort($names);
        return $names;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    public function register(AutocompleteProviderInterface $provider, ?string $name = null): void
    {
        $this->providers[$name ?? get_class($provider)] = $provider;
    }
}
