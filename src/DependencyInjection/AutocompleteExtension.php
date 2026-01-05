<?php

namespace Kerrialnewham\Autocomplete\DependencyInjection;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class AutocompleteExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.php');

        // Register _instanceof configuration to auto-tag all providers globally
        $container->registerForAutoconfiguration(AutocompleteProviderInterface::class)
            ->addTag('autocomplete.provider');
    }
}
