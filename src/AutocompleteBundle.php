<?php

namespace Kerrialnewham\Autocomplete;

use Kerrialnewham\Autocomplete\DependencyInjection\Compiler\AutocompleteProviderPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AutocompleteBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AutocompleteProviderPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('theme')->defaultValue('default')->end()
            ->arrayNode('allowed_themes')
            ->scalarPrototype()->end()
            ->defaultValue(['default', 'dark', 'cards', 'bootstrap5'])
            ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
        $container->parameters()->set('autocomplete.default_theme', $config['theme']);
        $container->parameters()->set('autocomplete.allowed_themes', $config['allowed_themes']);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig', [
            'form_themes' => [
                '@Autocomplete/form/autocomplete_widget.html.twig',
            ],
        ]);
    }
}
