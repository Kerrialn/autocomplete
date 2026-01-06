<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Form\DataTransformer\EntityToIdentifierTransformer;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AutocompleteFormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TemplateResolver $templates,
        private readonly ?EntityProviderFactory $providerFactory = null,
    ) {}

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'autocomplete' => false,

            // IMPORTANT: make provider optional
            'provider' => null,

            'placeholder' => 'Search...',
            'min_chars' => 1,
            'debounce' => 300,
            'limit' => 10,
            'theme' => null,
        ]);

        $resolver->setAllowedTypes('autocomplete', 'bool');
        $resolver->setAllowedTypes('provider', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
        $resolver->setAllowedTypes('theme', ['null', 'string']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        $inner = $builder->getType()->getInnerType();

        // Add EntityToIdentifierTransformer for EntityType with autocomplete
        if ($inner instanceof EntityType && $this->providerFactory !== null) {
            if (!$this->hasEntityTransformer($builder)) {
                // Extract choice_value if it's a string, otherwise use null (defaults to 'id')
                $choiceValue = $options['choice_value'] ?? null;
                if (!is_string($choiceValue) && !is_callable($choiceValue) && $choiceValue !== null) {
                    // EntityType has already processed choice_value into a ChoiceValue object
                    // Default to 'id' property
                    $choiceValue = null;
                }

                $builder->addViewTransformer(
                    new EntityToIdentifierTransformer(
                        registry: $this->providerFactory->getRegistry(),
                        class: $options['class'] ?? throw new \InvalidArgumentException('EntityType requires "class" option'),
                        choiceValue: $choiceValue,
                        multiple: $options['multiple'] ?? false,
                    )
                );
            }
        }
    }

    private function hasEntityTransformer(FormBuilderInterface $builder): bool
    {
        foreach ($builder->getViewTransformers() as $transformer) {
            if ($transformer instanceof EntityToIdentifierTransformer) {
                return true;
            }
        }
        return false;
    }

    private function resolveEntityProvider(FormInterface $form, array $options): string
    {
        if ($this->providerFactory === null) {
            throw new \LogicException(
                'EntityType autocomplete requires Doctrine ORM. ' .
                'Install: composer require doctrine/orm doctrine/doctrine-bundle'
            );
        }

        $class = $options['class'] ?? null;

        if (!$class) {
            throw new \InvalidArgumentException('EntityType requires "class" option.');
        }

        // Extract choice_label if it's valid, otherwise use null
        $choiceLabel = $options['choice_label'] ?? null;
        if (!is_string($choiceLabel) && !is_callable($choiceLabel) && $choiceLabel !== null) {
            $choiceLabel = null;
        }

        // Extract choice_value if it's valid, otherwise use null
        $choiceValue = $options['choice_value'] ?? null;
        if (!is_string($choiceValue) && !is_callable($choiceValue) && $choiceValue !== null) {
            $choiceValue = null;
        }

        // Get or create auto-generated provider
        $provider = $this->providerFactory->createProvider(
            class: $class,
            queryBuilder: $options['query_builder'] ?? null,
            choiceLabel: $choiceLabel,
            choiceValue: $choiceValue,
        );

        return $provider->getName();
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        // Don't hijack expanded choice (checkboxes/radios)
        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        // Resolve provider: explicit > inferred
        $provider = $options['provider'];

        if ($provider === null || $provider === '' || $provider === 'default') {
            $inner = $form->getConfig()->getType()->getInnerType();

            $provider = match (true) {
                $inner instanceof CountryType => 'symfony_countries',
                $inner instanceof EntityType => $this->resolveEntityProvider($form, $options),
                default => null,
            };
        }

        if ($provider === null || $provider === '' || $provider === 'default') {
            throw new \InvalidArgumentException(sprintf(
                'Autocomplete is enabled but no provider could be resolved for field "%s" (type: %s). ' .
                'Either set the "provider" option explicitly or add an inference rule.',
                $view->vars['full_name'] ?? '(unknown)',
                $form->getConfig()->getType()->getInnerType()::class
            ));
        }

        $view->vars['provider'] = $provider;
        $view->vars['placeholder'] = $options['placeholder'];
        $view->vars['min_chars'] = $options['min_chars'];
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['limit'] = $options['limit'];
        $view->vars['theme'] = $this->templates->theme($options['theme']);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        // Put `autocomplete` where Twig will try it BEFORE country/choice widgets
        $prefixes = $view->vars['block_prefixes'];
        $unique = array_pop($prefixes);
        $prefixes[] = 'autocomplete';
        $prefixes[] = $unique;

        $view->vars['block_prefixes'] = array_values(array_unique($prefixes));
    }
}
