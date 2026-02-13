<?php

namespace Kerrialnewham\Autocomplete\Form\Type;

use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutocompleteType extends AbstractType
{
    public function __construct(
        private readonly TemplateResolver $templates)
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($options['provider'] !== null) {
            $view->vars['provider'] = $options['provider'];
        }
        $view->vars['multiple'] = $options['multiple'];
        $view->vars['placeholder'] = $options['placeholder'];
        $view->vars['min_chars'] = $options['min_chars'];
        $view->vars['debounce'] = $options['debounce'];
        $view->vars['limit'] = $options['limit'];
        $view->vars['attr'] = array_merge($view->vars['attr'], $options['attr']);
        $view->vars['theme'] = $this->templates->theme($options['theme']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'provider' => null,
            'multiple' => false,
            'placeholder' => 'Search...',
            'min_chars' => 1,
            'debounce' => 300,
            'limit' => 10,
            'attr' => [],
            'theme' => null,
            'compound' => false,
        ]);

        $resolver->setAllowedTypes('provider', ['string', 'null']);
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('min_chars', 'int');
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedTypes('limit', 'int');
        $resolver->setAllowedTypes('attr', 'array');
        $resolver->setAllowedTypes('theme', ['string', 'null']);
    }

    public function getBlockPrefix(): string
    {
        return 'autocomplete';
    }
}
