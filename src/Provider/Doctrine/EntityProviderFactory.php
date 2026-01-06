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

        // Check cache first (before checking registry)
        $cacheKey = $this->getCacheKey($class, $queryBuilder, $choiceLabel, $choiceValue);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Check if a DoctrineEntityProvider already exists in registry
        if ($this->providerRegistry->has($providerName)) {
            $existingProvider = $this->providerRegistry->get($providerName);

            if ($existingProvider instanceof DoctrineEntityProvider) {
                // Cache it for future calls with same config
                $this->cache[$cacheKey] = $existingProvider;
                return $existingProvider;
            }

            // Custom provider exists - don't override it, just create a new instance for our use
            // but don't register it (let the custom one handle the provider name)
            $provider = new DoctrineEntityProvider(
                registry: $this->registry,
                class: $class,
                providerName: $providerName . '.auto', // Different name to avoid conflict
                queryBuilder: $queryBuilder,
                choiceLabel: $choiceLabel,
                choiceValue: $choiceValue,
            );

            $this->cache[$cacheKey] = $provider;
            return $provider;
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
