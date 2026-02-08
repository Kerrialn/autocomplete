<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Controller\AutocompleteController;
use Kerrialnewham\Autocomplete\Form\Extension\AutocompleteFormTypeExtension;
use Kerrialnewham\Autocomplete\Form\Type\AutocompleteEntityType;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CountryProvider;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;
use Kerrialnewham\Autocomplete\Security\AutocompleteSigner;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Kerrialnewham\Autocomplete\Twig\Extension\AutocompleteTwigExtension;
use Symfony\Bundle\SecurityBundle\Security;

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
            __DIR__.'/../src/Provider/Doctrine/DoctrineEntityProvider.php',
            __DIR__.'/../src/Form/DataTransformer/',
        ]);

    // Signing secret param (must be set in host app env)
    $container->parameters()->set('kerrialnewham.autocomplete.signing_secret', env('AUTOCOMPLETE_SIGNING_SECRET'));

    // Signer
    $services->set(AutocompleteSigner::class)
        ->args([
            '$secret' => '%kerrialnewham.autocomplete.signing_secret%',
        ]);

    // Twig function: autocomplete_sig(...)
    // (requires SecurityBundle)
    $services->set(AutocompleteTwigExtension::class)
        ->args([
            service(AutocompleteSigner::class),
            service(Security::class),
        ])
        ->tag('twig.extension');

    // Explicitly register ProviderRegistry
    $services->set(ProviderRegistry::class)
        ->autowire()
        ->public()
        ->args([tagged_iterator('autocomplete.provider')]);

    // Controllers as public + inject signing secret
    $services->set(AutocompleteController::class)
        ->autowire()
        ->public()
        ->tag('controller.service_arguments')
        ->args([
            '$signingSecret' => '%kerrialnewham.autocomplete.signing_secret%',
        ]);

    $services->set(CountryProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(TemplateResolver::class);

    $services->set(AutocompleteFormTypeExtension::class)
        ->autowire()
        ->autoconfigure()
        ->tag('form.type_extension');

    // Doctrine entity support (conditional on Doctrine availability)
    if (interface_exists(ManagerRegistry::class)) {
        $services->set(EntityProviderFactory::class)
            ->autowire()
            ->args([
                service(ManagerRegistry::class),
                service(ProviderRegistry::class),
            ]);

        $services->set(AutocompleteEntityType::class)
            ->autowire()
            ->tag('form.type');
    }
};
