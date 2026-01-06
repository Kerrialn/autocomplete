<?php

namespace Kerrialnewham\Autocomplete\Form\Type;

use Doctrine\Persistence\ManagerRegistry;
use Kerrialnewham\Autocomplete\Form\DataTransformer\EntityToIdentifierTransformer;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutocompleteEntityType extends AbstractType
{
    private ?string $providerName = null;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly EntityProviderFactory $providerFactory,
        private readonly TemplateResolver $templates,
    ) {
        if (!interface_exists(ManagerRegistry::class)) {
            throw new \LogicException(
                'AutocompleteEntityType requires Doctrine ORM. ' .
                'Install: composer require doctrine/orm doctrine/doctrine-bundle'
            );
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $class = $options['class'];

        if (!$class) {
            throw new \InvalidArgumentException('The "class" option is required for AutocompleteEntityType.');
        }

        // Determine provider: custom or auto-generated
        if ($options['provider'] !== null) {
            $this->providerName = $options['provider'];
        } else {
            // Auto-generate provider
            $provider = $this->providerFactory->createProvider(
                class: $class,
                queryBuilder: $options['query_builder'],
                choiceLabel: $options['choice_label'],
                choiceValue: $options['choice_value'],
            );

            $this->providerName = $provider->getName();
        }

        // Add data transformer to convert entity ↔ ID
        $builder->addViewTransformer(
            new EntityToIdentifierTransformer(
                registry: $this->registry,
                class: $class,
                choiceValue: $options['choice_value'],
                multiple: $options['multiple'],
            )
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Set autocomplete-specific view vars
        $view->vars['provider'] = $this->providerName;
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['placeholder'] = $options['placeholder'];
        $view->vars['min_chars'] = $options['min_chars'];
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['limit'] = $options['limit'];
        $view->vars['theme'] = $this->templates->theme($options['theme']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Inherit all EntityType options
        parent::configureOptions($resolver);

        // Add autocomplete-specific options
        $resolver->setDefaults([
            'provider' => null,
            'theme' => null,
            'placeholder' => 'Search...',
            'min_chars' => 1,
            'debounce' => 300,
            'limit' => 10,
            'compound' => false,
        ]);

        $resolver->setAllowedTypes('provider', ['null', 'string']);
        $resolver->setAllowedTypes('theme', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
    }

    public function getBlockPrefix(): string
    {
        return 'autocomplete';
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
