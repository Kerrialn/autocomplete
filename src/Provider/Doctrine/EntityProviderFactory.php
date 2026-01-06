<?php

namespace Kerrialnewham\Autocomplete\Provider\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;

class EntityProviderFactory
{
    private array $cache = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    public function createProvider(
        string $class,
        ?callable $queryBuilder = null,
        string|callable|null $choiceLabel = null,
        string|callable|null $choiceValue = null,
    ): DoctrineEntityProvider {
        $providerName = $this->getProviderName($class);

        // Check if custom provider already exists
        if ($this->providerRegistry->has($providerName)) {
            $existingProvider = $this->providerRegistry->get($providerName);

            if ($existingProvider instanceof DoctrineEntityProvider) {
                return $existingProvider;
            }

            // Custom provider exists, return it
            // Note: We can't return non-DoctrineEntityProvider instances, but that's ok
            // because the caller will use it through the registry anyway
        }

        // Check cache
        $cacheKey = $this->getCacheKey($class, $queryBuilder, $choiceLabel, $choiceValue);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Create new provider
        $provider = new DoctrineEntityProvider(
            registry: $this->registry,
            class: $class,
            providerName: $providerName,
            queryBuilder: $queryBuilder,
            choiceLabel: $choiceLabel,
            choiceValue: $choiceValue,
        );

        // Cache and register
        $this->cache[$cacheKey] = $provider;
        $this->providerRegistry->register($provider);

        return $provider;
    }

    public function getProviderName(string $class): string
    {
        return 'entity.' . $class;
    }

    public function hasProvider(string $class): bool
    {
        $providerName = $this->getProviderName($class);
        return $this->providerRegistry->has($providerName);
    }

    public function getRegistry(): ManagerRegistry
    {
        return $this->registry;
    }

    private function getCacheKey(
        string $class,
        ?callable $queryBuilder,
        string|callable|null $choiceLabel,
        string|callable|null $choiceValue
    ): string {
        // For callables, we can't create a reliable cache key, so use object hash
        $parts = [
            $class,
            $queryBuilder !== null ? spl_object_hash($queryBuilder) : 'null',
            is_callable($choiceLabel) ? spl_object_hash($choiceLabel) : (string) $choiceLabel,
            is_callable($choiceValue) ? spl_object_hash($choiceValue) : (string) $choiceValue,
        ];

        return implode('|', $parts);
    }
}
