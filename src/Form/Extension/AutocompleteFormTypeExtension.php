<?php

namespace Kerrialnewham\Autocomplete\Form\Extension;

use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AutocompleteFormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TemplateResolver $templates,
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
        ]);

        $resolver->setAllowedTypes('autocomplete', 'bool');
        $resolver->setAllowedTypes('provider', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
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
        $view->vars['theme'] = $this->templates->theme(null);
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
