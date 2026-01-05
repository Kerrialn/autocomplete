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
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.php');

        // Set configuration parameters
        $container->setParameter('autocomplete.default_theme', $config['theme']);
        $container->setParameter('autocomplete.allowed_themes', $config['allowed_themes']);

        // Register _instanceof configuration to auto-tag all providers globally
        $container->registerForAutoconfiguration(AutocompleteProviderInterface::class)
            ->addTag('autocomplete.provider');
    }
}
