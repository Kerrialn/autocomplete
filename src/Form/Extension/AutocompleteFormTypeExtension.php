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

        if ($inner instanceof EntityType && $this->providerFactory !== null) {
            if (!$this->hasEntityTransformer($builder)) {
                $choiceValue = $options['choice_value'] ?? null;

                // if Symfony has already normalized this into an object, ignore it here (transformer uses Doctrine id fallback)
                if (!is_string($choiceValue) && !is_callable($choiceValue) && $choiceValue !== null) {
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

    private function normalizeChoiceOption(mixed $opt): string|\Closure|null
    {
        if (is_string($opt) && $opt !== '') {
            return $opt;
        }

        // Symfony often converts "title" into a PropertyPath-like object that stringifies
        if (is_object($opt) && method_exists($opt, '__toString')) {
            $s = (string) $opt;
            if ($s !== '') {
                return $s;
            }
        }

        if ($opt !== null && is_callable($opt)) {
            return \Closure::fromCallable($opt);
        }

        return null;
    }

    private function resolveEntityProvider(FormInterface $form, array $options): string
    {
        if ($this->providerFactory === null) {
            throw new \LogicException(
                'EntityType autocomplete requires Doctrine ORM. Install: composer require doctrine/orm doctrine/doctrine-bundle'
            );
        }

        $class = $options['class'] ?? null;
        if (!$class) {
            throw new \InvalidArgumentException('EntityType requires "class" option.');
        }

        $choiceLabel = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $choiceValue = $this->normalizeChoiceOption($options['choice_value'] ?? null);

        // ProviderFactory signature wants string|callable|null, Closure is callable.
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

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

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
                'Autocomplete is enabled but no provider could be resolved for field "%s".',
                $view->vars['full_name'] ?? '(unknown)',
            ));
        }

        $view->vars['provider'] = $provider;
        $view->vars['placeholder'] = $options['placeholder'];
        $view->vars['min_chars'] = $options['min_chars'];
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['limit'] = $options['limit'];
        $view->vars['theme'] = $this->templates->theme($options['theme']);

        $cl = $this->normalizeChoiceOption($options['choice_label'] ?? null);
        $cv = $this->normalizeChoiceOption($options['choice_value'] ?? null);

        $view->vars['autocomplete_choice_label'] = is_string($cl) ? $cl : null;
        $view->vars['autocomplete_choice_value'] = is_string($cv) ? $cv : null;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['autocomplete']) {
            return;
        }

        if (($view->vars['expanded'] ?? false) === true) {
            return;
        }

        $prefixes = $view->vars['block_prefixes'];
        $unique = array_pop($prefixes);
        $prefixes[] = 'autocomplete';
        $prefixes[] = $unique;

        $view->vars['block_prefixes'] = array_values(array_unique($prefixes));
    }
}
