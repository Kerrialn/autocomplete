<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Controller\AutocompleteController;
use Kerrialnewham\Autocomplete\Form\Extension\AutocompleteChoiceTypeExtension;
use Kerrialnewham\Autocomplete\Form\Extension\AutocompleteEntityTypeExtension;
use Kerrialnewham\Autocomplete\Form\Extension\AutocompleteFormTypeExtension;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Form\Type\InternationalDialCodeType;
use Kerrialnewham\Autocomplete\Form\Type\PhoneNumberType;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CountryProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\DialCodeProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\CurrencyProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\LanguageProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\LocaleProvider;
use Kerrialnewham\Autocomplete\Provider\Provider\Symfony\TimezoneProvider;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;
use Kerrialnewham\Autocomplete\Security\AutocompleteSigner;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Kerrialnewham\Autocomplete\Twig\Extension\AutocompleteTwigExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Load all classes from src/ except DI, Tests, route files, and runtime-only providers
    $services->load('Kerrialnewham\\Autocomplete\\', __DIR__.'/../src/')
        ->exclude([
            __DIR__.'/../src/DependencyInjection/',
            __DIR__.'/../src/**/Tests/',
            __DIR__.'/../src/Example/demo_routes.php',
            __DIR__.'/../src/Example/*.md',
            __DIR__.'/../src/Provider/Doctrine/DoctrineEntityProvider.php',
            __DIR__.'/../src/Provider/Choice/',
            __DIR__.'/../src/Form/DataTransformer/',
        ]);

    // Signing secret param (must be set in host app env)
    $container->parameters()->set('kerrialnewham.autocomplete.signing_secret', env('APP_SECRET'));


    // Twig function: autocomplete_sig(...)
    // (requires SecurityBundle)
    $services->set(AutocompleteTwigExtension::class)
        ->args([
            '$signingSecret' => '%kerrialnewham.autocomplete.signing_secret%',
            '$security' => service(Security::class),
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
            '$translator' => service(TranslatorInterface::class)->nullOnInvalid(),
        ]);

    // Built-in Symfony Intl providers
    $services->set(CountryProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(LanguageProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(LocaleProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(CurrencyProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(TimezoneProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(DialCodeProvider::class)
        ->autowire()
        ->autoconfigure()
        ->tag('autocomplete.provider');

    $services->set(InternationalDialCodeType::class)
        ->tag('form.type');

    $services->set(PhoneNumberType::class)
        ->tag('form.type');

    $services->set(TemplateResolver::class);

    // Shared options (autocomplete, provider, min_chars, etc.) + block prefix injection
    $services->set(AutocompleteFormTypeExtension::class)
        ->autowire()
        ->tag('form.type_extension');

    // Choice-based types (EnumType, CountryType, LanguageType, LocaleType, CurrencyType, TimezoneType)
    $services->set(AutocompleteChoiceTypeExtension::class)
        ->autowire()
        ->args([
            '$translator' => service(TranslatorInterface::class)->nullOnInvalid(),
        ])
        ->tag('form.type_extension');

    // Doctrine DBAL phone_number type (conditional on Doctrine DBAL availability)
    if (class_exists(DBALType::class) && !DBALType::hasType(\Kerrialnewham\Autocomplete\Doctrine\Type\PhoneNumberType::NAME)) {
        DBALType::addType(
            \Kerrialnewham\Autocomplete\Doctrine\Type\PhoneNumberType::NAME,
            \Kerrialnewham\Autocomplete\Doctrine\Type\PhoneNumberType::class
        );
    }

    // Doctrine entity support (conditional on Doctrine availability)
    if (interface_exists(ManagerRegistry::class)) {
        $services->set(EntityProviderFactory::class)
            ->autowire()
            ->args([
                service(ManagerRegistry::class),
                service(ProviderRegistry::class),
            ]);

        $services->set(AutocompleteEntityTypeExtension::class)
            ->autowire()
            ->tag('form.type_extension');

    }
};
