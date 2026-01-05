<?php

namespace Kerrialnewham\Autocomplete\DependencyInjection\Compiler;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AutocompleteProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Find all services that implement AutocompleteProviderInterface
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$class) {
                continue;
            }

            // Resolve any parameter placeholders
            $class = $container->getParameterBag()->resolveValue($class);

            // Check if class exists without triggering autoload errors
            try {
                if (!class_exists($class, false)) {
                    // Try to load the class safely
                    if (!class_exists($class)) {
                        continue;
                    }
                }

                $reflection = new \ReflectionClass($class);

                if ($reflection->implementsInterface(AutocompleteProviderInterface::class)) {
                    $definition->addTag('autocomplete.provider');
                }
            } catch (\Throwable $e) {
                // Skip classes that can't be loaded
                continue;
            }
        }
    }
}
