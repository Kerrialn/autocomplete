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
            $this->providers[$p->getName()] = $p;
        }
    }

    public function get(string $name): AutocompleteProviderInterface
    {
        if ($name === '' || $name === 'default') {
            throw new InvalidArgumentException(sprintf(
                'Invalid provider "%s". Provider must be set on the form field. Available providers: [%s]',
                $name,
                implode(', ', $this->names())
            ));
        }

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

    public function register(AutocompleteProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }
}
