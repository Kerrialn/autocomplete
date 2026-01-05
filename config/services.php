<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Kerrialnewham\Autocomplete\Controller\AutocompleteController;
use Kerrialnewham\Autocomplete\Form\Extension\AutocompleteFormTypeExtension;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CountryProvider;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Load all classes from src/ except DI, Tests, and route files
    $services->load('Kerrialnewham\\Autocomplete\\', __DIR__.'/../src/')
        ->exclude([
            __DIR__.'/../src/DependencyInjection/',
            __DIR__.'/../src/**/Tests/',
            __DIR__.'/../src/Example/demo_routes.php',
            __DIR__.'/../src/Example/*.md',
        ]);

    // Explicitly register ProviderRegistry
    $services->set(ProviderRegistry::class)
        ->autowire()
        ->public()
        ->args([tagged_iterator('autocomplete.provider')]);

    // Explicitly register controllers as public
    $services->set(AutocompleteController::class)
        ->autowire()
        ->public()
        ->tag('controller.service_arguments');

    $services->set(CountryProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');


    $services->set(TemplateResolver::class)
        ->args([
            '%autocomplete.default_theme%',
            '%autocomplete.allowed_themes%',
        ]);

    $services->set(AutocompleteFormTypeExtension::class)
        ->autowire()
        ->autoconfigure()
        ->tag('form.type_extension');
};
